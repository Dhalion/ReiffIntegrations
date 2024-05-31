<?php declare(strict_types=1);

namespace ReiffIntegrations\Components\Cart\Validator;

use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Shopware\Core\Checkout\Cart\Error\GenericCartError;
use ReiffIntegrations\Installer\CustomFieldInstaller;
use ReiffIntegrations\Components\Cart\Validator\Error\ProductPriceRequestRequiredError;

class PriceRequestProductValidator implements CartValidatorInterface {

    public function __construct(
        private RequestStack $requestStack
    ){
    }

    public function validate(Cart $cart, ErrorCollection $errorCollection, SalesChannelContext $salesChannelContext): void
    {
        foreach ($cart->getLineItems() as $lineItem) {
            $isPriceRequestProduct = $lineItem->getPayloadValue('customFields')[CustomFieldInstaller::PRODUCT_ANFRAGE] ?? false;
            if ($isPriceRequestProduct) {
                // display error and remove product from cart
                $errorCollection->add(new ProductPriceRequestRequiredError($lineItem->getId()));
                $cart->remove($lineItem->getId());
            }

        }
    }
}
