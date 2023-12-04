<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Offer\Page;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Sap\DataAbstractionLayer\CustomerExtension;
use ReiffIntegrations\Sap\DataAbstractionLayer\ReiffCustomerEntity;
use ReiffIntegrations\Sap\Offer\ApiClient\OfferReadApiClient;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\HttpFoundation\Request;

class OfferDetailPageLoader
{
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly OfferReadApiClient $apiClient,
        private readonly LoggerInterface $logger
    ) {
    }

    public function load(string $offerNumber, Request $request, SalesChannelContext $salesChannelContext): OfferDetailPage
    {
        $customer = $salesChannelContext->getCustomer();

        if ($customer === null) {
            throw new CustomerNotLoggedInException(404, '404', 'Customer not logged in');
        }

        $page = $this->genericLoader->load($request, $salesChannelContext);
        $page->getMetaInformation()?->setRobots('noindex,follow');
        /** @var \ReiffIntegrations\Sap\Offer\Page\OfferDetailPage $page */
        $page = OfferDetailPage::createFrom($page);

        try {
            /** @var null|ReiffCustomerEntity $customerData */
            $customerData = $customer->getExtension(CustomerExtension::EXTENSION_NAME);

            if ($customerData === null) {
                return $page;
            }

            $apiResponse = $this->apiClient->readOffers($customerData);
        } catch (\Throwable $throwable) {
            // TODO: discuss about direct information to REIFF
            $this->logger->error(
                'Offer detail could not be read.',
                [
                    'exception'   => $throwable,
                    'offerNumber' => $offerNumber,
                ]
            );

            $page->addError($throwable);

            return $page;
        }

        if (!$apiResponse->isSuccess()) {
            return $page;
        }

        $offer = $apiResponse->getDocuments()->getOfferByNumber($offerNumber);
        $page->setOffer($offer);

        return $page;
    }
}
