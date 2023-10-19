<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Offer\Page;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountOfferPageLoader
{
    private GenericPageLoaderInterface $genericLoader;

    public function __construct(
        GenericPageLoaderInterface $genericLoader
    ) {
        $this->genericLoader = $genericLoader;
    }

    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountOfferPage
    {
        if ($salesChannelContext->getCustomer() === null) {
            throw new CustomerNotLoggedInException();
        }

        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountOfferPage::createFrom($page);

        $page->getMetaInformation()?->setRobots('noindex,follow');

        return $page;
    }
}
