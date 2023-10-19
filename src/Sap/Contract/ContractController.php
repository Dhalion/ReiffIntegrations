<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract;

use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class ContractController extends StorefrontController
{
    public function __construct(
        private readonly ContractListingPageLoader $contractsListingPageLoader,
        private readonly ContractStatusPageLoader $contractsStatusPageLoader
    ) {
    }

    #[Route('/contracts', name: 'frontend.b2b.k10r_reiff_integrations.contracts.index', options: ['seo' => false], defaults: ['_noStore' => true, '_loginRequired' => true], methods: ['GET'])]
    public function indexAction(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->contractsListingPageLoader->load($request, $context);

        return $this->renderStorefront(
            '@Storefront/storefront/page/contract/index.html.twig',
            [
                'page'   => $page,
                'locale' => $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE),
            ]
        );
    }

    #[Route('/contract/{contractNumber}', name: 'frontend.b2b.k10r_reiff_integrations.contracts.detail', options: ['seo' => false], defaults: ['_loginRequired' => true, 'XmlHttpRequest' => true], methods: ['GET'])]
    public function ajaxOfferList(string $contractNumber, Request $request, SalesChannelContext $context): Response
    {
        $page = $this->contractsStatusPageLoader->load($contractNumber, $request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/contract/detail.html.twig', ['page' => $page]);
    }
}
