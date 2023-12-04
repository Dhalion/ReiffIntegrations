<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\DeliveryInformation;

use ReiffIntegrations\Installer\CustomFieldInstaller;
use ReiffIntegrations\Sap\DeliveryInformation\Struct\DeliveryCostsNotComputable;
use ReiffIntegrations\Sap\DeliveryInformation\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartDeliveryInformationProcessor implements CartDataCollectorInterface, CartProcessorInterface
{
    private const NATIONAL_COUNTRY_ISO = 'DE';

    public function __construct(
        private readonly AvailabilityService $availabilityService
    ) {
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $productLineItems = $this->getProductLineItem($original);

        if ($productLineItems->count() === 0) {
            return;
        }

        $productNumbers = [];
        foreach ($productLineItems as $lineItem) {
            $productId = $lineItem->getReferencedId();

            if (is_null($productId)) {
                continue;
            }

            $key = $this->buildKey($productId);

            if (!$data->has($key)) {
                continue;
            }

            $product = $data->get($key);

            if (!$product instanceof SalesChannelProductEntity) {
                continue;
            }

            $payload       = $lineItem->getPayload();
            $productNumber = $payload['productNumber'] ?? null;

            if (!empty($productNumber)) {
                $productNumbers[] = $productNumber;
            }

            $productCustomFields = $product->getCustomFields() ?? [];
            $deliveryCode        = null;

            if (array_key_exists(CustomFieldInstaller::PRODUCT_SHIPPING_TIME, $productCustomFields) && $productCustomFields[CustomFieldInstaller::PRODUCT_SHIPPING_TIME] !== null) {
                $deliveryCode = (int) $productCustomFields[CustomFieldInstaller::PRODUCT_SHIPPING_TIME];
            }

            $lineItem->addExtension(
                DeliveryInformation::NAME,
                new DeliveryInformation($deliveryCode, (bool) $product->getShippingFree(), (bool) $product->getActive())
            );
        }

        $filtered            = array_unique($productNumbers);
        $availabilityResults = $this->availabilityService->fetchAvailabilities($filtered);

        foreach ($productLineItems as $lineItem) {
            /** @var null|DeliveryInformation $deliveryExtension */
            $deliveryExtension = $lineItem->getExtension(DeliveryInformation::NAME);

            if ($deliveryExtension === null) {
                continue;
            }

            $payload       = $lineItem->getPayload();
            $productNumber = $payload['productNumber'] ?? null;

            if (empty($productNumber)) {
                continue;
            }

            $availability = $availabilityResults->getAvailabilityByNumber($productNumber);

            if ($availability === null) {
                continue;
            }

            $deliveryExtension->setDeliveryCode($availability->getCode());
        }
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $shippingCountry = $context->getShippingLocation()->getCountry();

        if ($shippingCountry->getIso() === self::NATIONAL_COUNTRY_ISO) {
            return;
        }

        $zeroCosts = new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection());

        foreach ($toCalculate->getDeliveries() as $delivery) {
            $delivery->setShippingCosts($zeroCosts);
        }

        $context->addExtension(DeliveryCostsNotComputable::NAME, new DeliveryCostsNotComputable());
    }

    private function getProductLineItem(Cart $cart): LineItemCollection
    {
        return $cart->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);
    }

    private function buildKey(string $id): string
    {
        return 'product-' . $id;
    }
}
