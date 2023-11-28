<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Cart;

use ReiffIntegrations\Sap\Api\Client\Cart\CartApiClient;
use ReiffIntegrations\Sap\CartError\SapNotAvavilableError;
use ReiffIntegrations\Sap\DataAbstractionLayer\CustomerExtension;
use ReiffIntegrations\Sap\DataAbstractionLayer\ReiffCustomerEntity;
use ReiffIntegrations\Sap\Struct\CartHashStruct;
use ReiffIntegrations\Sap\Struct\Price\ItemStruct;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class PriceCartProcessor implements CartDataCollectorInterface, CartProcessorInterface
{
    public const SAP_CART_HASH                    = 'SAP_API_CART_HASH';
    public const SAP_CART_SHIPPING_ITEM_KEY       = 'SAP_API_CART_SHIPPING_ITEM';
    public const SAP_NOT_ACCESSIBLE_CIRCUIT_TAG   = '#reiff#soap#prices#disabled';
    public const CACHE_TAG_CACHED_CART            = 'REIFF_INVALIDATE_CART_CACHE';
    public const CART_STATE_INVALIDATE_CART_CACHE = 'REIFF_INVALIDATE_CART_CACHE_STATE';

    private const CART_CACHE_EXPIRATION_IN_SECONDS = 60 * 15;

    public function __construct(
        private readonly CartApiClient $client,
        private readonly TagAwareAdapterInterface $cache,
        private readonly CashRounding $cashRounding,
    ) {
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        if ($original->getLineItems()->count() === 0) {
            return;
        }

        $customer = $context->getCustomer();

        if (!$customer) {
            $this->removeSapCartData($original, $data);

            return;
        }

        /** @var null|ReiffCustomerEntity $reiffCustomer */
        $reiffCustomer = $customer->getExtension(CustomerExtension::EXTENSION_NAME);

        if (!$reiffCustomer) {
            $this->removeSapCartData($original, $data);

            return;
        }

        $circuitBreaker = $this->cache->getItem(self::SAP_NOT_ACCESSIBLE_CIRCUIT_TAG);

        if ($circuitBreaker->isHit()) {
            $this->removeSapCartData($original, $data);

            return;
        }

        if (!$reiffCustomer->getDebtorNumber()) {
            $this->removeSapCartData($original, $data);

            return;
        }

        /** @var null|CartHashStruct $previousCartHash */
        $previousCartHash = $original->getExtension(CartHashStruct::NAME);
        $cartCached       = $this->cache->getItem($this->getCartCacheKey($reiffCustomer->getDebtorNumber()));
        $cartHash         = $this->getCartHash($original);

        if (
            $cartHash !== ''
            && (
                (!$data->has(self::SAP_CART_HASH) && !$previousCartHash) // No price fetching happened yet
                || ($data->has(self::SAP_CART_HASH) && $data->get(self::SAP_CART_HASH) !== $cartHash) // Not the first cart processor iteration, but cart contents have changed
                || ($previousCartHash && $previousCartHash->getHash() !== $cartHash) // Cart was calculated in a previous request, but has changed since
                || $context->hasState(self::CART_STATE_INVALIDATE_CART_CACHE) /** @see \ReiffIntegrations\Sap\ShopPricing\PriceSubscriber */
                || !$cartCached->isHit() // in any case, only skip price fetching if we have an active cache flag
            )
        ) {
            try {
                $sapCart = $this->client->getPrices($original, $reiffCustomer, $context);
                $data->set(self::SAP_CART_HASH, $cartHash);
                $roundingConfig = $context->getItemRounding();

                foreach ($original->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $lineItem) {
                    if ($sapCart->has($lineItem->getPayloadValue('productNumber'))) {
                        /** @var ItemStruct $sapItem */
                        $sapItem = $sapCart->get($lineItem->getPayloadValue('productNumber'));

                        $oldPriceDefinition = $lineItem->getPriceDefinition();

                        if (!($oldPriceDefinition instanceof QuantityPriceDefinition)) {
                            continue;
                        }

                        $lineItem->setPriceDefinition(
                            $this->buildPriceDefinition(
                                new CalculatedPrice(
                                    $this->cashRounding->cashRound($sapItem->getTotalPrice() / $sapItem->getQuantity(), $roundingConfig),
                                    $this->cashRounding->cashRound($sapItem->getTotalPrice(), $roundingConfig),
                                    new CalculatedTaxCollection(),
                                    $oldPriceDefinition->getTaxRules()
                                ),
                                $sapItem->getQuantity(),
                                $oldPriceDefinition->getReferencePriceDefinition()
                            )
                        );
                        $lineItem->addArrayExtension(ProductCartProcessor::CUSTOM_PRICE, []);
                    } else {
                        $lineItem->removeExtension(ProductCartProcessor::CUSTOM_PRICE);
                    }
                }

                $sapShippingData = $sapCart->get(self::SAP_CART_SHIPPING_ITEM_KEY);

                if ($sapShippingData !== null) {
                    foreach ($original->getDeliveries()->getIterator() as $delivery) {
                        $oldShippingCosts = $delivery->getShippingCosts();

                        $original->addExtensions([DeliveryProcessor::MANUAL_SHIPPING_COSTS => new CalculatedPrice(
                            $this->cashRounding->cashRound($sapShippingData->getTotalPrice(), $roundingConfig),
                            $this->cashRounding->cashRound($sapShippingData->getTotalPrice(), $roundingConfig),
                            new CalculatedTaxCollection(),
                            $oldShippingCosts->getTaxRules(),
                            1
                        )]);
                    }
                }
                $context->removeState(self::CART_STATE_INVALIDATE_CART_CACHE);

                $cartCached->set(true);
                $cartCached->expiresAfter(self::CART_CACHE_EXPIRATION_IN_SECONDS);
                $this->cache->save($cartCached);
            } catch (\Throwable $t) {
                // silentfail - maybe api is missing
                $this->removeSapCartData($original, $data);

                $circuitBreaker->set(true);
                $circuitBreaker->expiresAfter(60);

                $this->cache->save($circuitBreaker);
            }
        }
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        if ($data->has(self::SAP_CART_HASH)) {
            /** @var string $cartHash */
            $cartHash = $data->get(self::SAP_CART_HASH);

            $toCalculate->addExtension(CartHashStruct::NAME, new CartHashStruct($cartHash));
        }
    }

    private function buildPriceDefinition(CalculatedPrice $price, int $quantity, ?ReferencePriceDefinition $referencePriceDefinition): QuantityPriceDefinition
    {
        $definition = new QuantityPriceDefinition($price->getUnitPrice(), $price->getTaxRules(), $quantity);

        if ($price->getListPrice() !== null) {
            $definition->setListPrice($price->getListPrice()->getPrice());
        }

        if ($referencePriceDefinition) {
            $definition->setReferencePriceDefinition($referencePriceDefinition);
        }

        return $definition;
    }

    private function getCartHash(Cart $cart): string
    {
        $hashData = '';

        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            $hashData .= sprintf('%s-%d-', $lineItem->getId(), $lineItem->getQuantity());
        }

        if (empty($hashData)) {
            return '';
        }

        return hash('sha1', $hashData);
    }

    private function removeSapCartData(Cart $cart, CartDataCollection $data): void
    {
        foreach ($cart->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $lineItem) {
            $lineItem->removeExtension(ProductCartProcessor::CUSTOM_PRICE);
        }

        $data->remove(self::SAP_CART_HASH);
        $cart->removeExtension(CartHashStruct::NAME);

        $cart->addErrors(
            new SapNotAvavilableError()
        );
    }

    private function getCartCacheKey(string $debtorNumber): string
    {
        return sprintf('%s-%s', self::CACHE_TAG_CACHED_CART, $debtorNumber);
    }
}
