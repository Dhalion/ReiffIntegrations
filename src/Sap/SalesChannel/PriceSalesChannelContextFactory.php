<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\SalesChannel;

use ReiffIntegrations\Sap\Cart\PriceCartProcessor;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;

class PriceSalesChannelContextFactory extends SalesChannelContextFactory
{
    private const INVALIDATE_CART_CACHE_ROUTES = [
        'frontend.checkout.confirm.page',
        'frontend.checkout.cart.page',
        'frontend.cart.offcanvas',
        'frontend.checkout.finish.order',
    ];

    public function __construct(
        private readonly AbstractSalesChannelContextFactory $baseService,
        private readonly ?RequestStack $requestStack,
    ) {
    }

    public function getDecorated(): AbstractSalesChannelContextFactory
    {
        return $this->baseService;
    }

    public function create(string $token, string $salesChannelId, array $options = []): SalesChannelContext
    {
        $permissions                                                       = $options[SalesChannelContextService::PERMISSIONS] ?? [];
        $permissions[ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES] = true;
        $options[SalesChannelContextService::PERMISSIONS]                  = $permissions;

        $context = $this->baseService->create($token, $salesChannelId, $options);

        if ($this->requestStack) {
            $request = $this->requestStack->getMainRequest();

            if ($request) {
                $route = $request->get('_route', '');

                if (in_array($route, self::INVALIDATE_CART_CACHE_ROUTES)) {
                    $context->addState(PriceCartProcessor::CART_STATE_INVALIDATE_CART_CACHE);
                }
            }
        }

        return $context;
    }
}
