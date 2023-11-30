<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract;

use ReiffIntegrations\Sap\Contract\ApiClient\ContractListClient;
use ReiffIntegrations\Sap\DataAbstractionLayer\CustomerExtension;
use ReiffIntegrations\Sap\DataAbstractionLayer\ReiffCustomerEntity;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\Page;
use Symfony\Component\HttpFoundation\Request;

class ContractListingPageLoader
{
    public const PARAMETER_FROM_DATE = 'fromDate';
    public const PARAMETER_TO_DATE   = 'toDate';

    public function __construct(
        private readonly GenericPageLoaderInterface $genericPageLoader,
        private readonly ContractListClient $contractListClient
    ) {
    }

    public function load(Request $request, SalesChannelContext $salesChannelContext): ContractListingPage
    {
        $page = $this->getBasicPage($request, $salesChannelContext);
        /** @var \ReiffIntegrations\Sap\Contract\ContractListingPage $page */
        $page = ContractListingPage::createFrom($page);

        /** @var \Shopware\Core\Checkout\Customer\CustomerEntity $customer See $this::getBasicPage() */
        $customer = $salesChannelContext->getCustomer();

        /** @var null|ReiffCustomerEntity $customerData */
        $customerData = $customer->getExtension(CustomerExtension::EXTENSION_NAME);

        if ($customerData === null || empty($customerData->getDebtorNumber())) {
            return $page;
        }

        if ($request->query->has(self::PARAMETER_FROM_DATE)) {
            try {
                $fromDate = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->query->get(self::PARAMETER_FROM_DATE));

                if ($fromDate) {
                    $page->setFromDate($fromDate);
                }
            } catch (\Exception $e) {
                // No-op, invalid date results in default case
            }
        }

        if ($request->query->has(self::PARAMETER_TO_DATE)) {
            try {
                $toDate = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->query->get(self::PARAMETER_TO_DATE));

                if ($toDate) {
                    $page->setToDate($toDate);
                }
            } catch (\Exception $e) {
                // No-op, invalid date results in default case
            }
        }

        $contractResult = $this->contractListClient->getContracts(
            $customerData,
            $page->getFromDate(),
            $page->getToDate()
        );

        $page->setSuccess($contractResult->isSuccess());
        $page->setContracts($contractResult->getContracts());

        return $page;
    }

    private function getBasicPage(Request $request, SalesChannelContext $salesChannelContext): Page
    {
        if (!$salesChannelContext->getCustomer()) {
            throw new CustomerNotLoggedInException(404, '404', 'Customer not logged in');
        }

        $page = $this->genericPageLoader->load($request, $salesChannelContext);
        $page->getMetaInformation()?->setRobots('noindex,follow');

        return $page;
    }
}
