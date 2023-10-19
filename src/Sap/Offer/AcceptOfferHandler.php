<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Offer;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Sap\DataAbstractionLayer\CustomerExtension;
use ReiffIntegrations\Sap\DataAbstractionLayer\ReiffCustomerEntity;
use ReiffIntegrations\Sap\Offer\ApiClient\OfferReadApiClient;
use ReiffIntegrations\Sap\Struct\OfferDocumentPositionCollection;
use ReiffIntegrations\Sap\Struct\OfferDocumentStruct;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\OrderPersisterInterface;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AcceptOfferHandler
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly LineItemFactoryInterface $customLineItemFactory,
        private readonly EntityRepository $productRepository,
        private readonly AbsolutePriceCalculator $absolutePriceCalculator,
        private readonly Processor $processor,
        private readonly OrderPersisterInterface $orderPersister,
        private readonly OfferReadApiClient $apiClient,
        private readonly LoggerInterface $logger
    ) {
    }

    public function acceptOffer(string $offerNumber, SalesChannelContext $salesChannelContext): bool
    {
        $customer = $salesChannelContext->getCustomer();

        if (!$customer) {
            return false;
        }

        /** @var null|ReiffCustomerEntity $customerData */
        $customerData = $customer->getExtension(CustomerExtension::EXTENSION_NAME);

        if ($customerData === null) {
            return false;
        }

        try {
            $apiResponse = $this->apiClient->readOffers((string) $customerData->getDebtorNumber());
        } catch (\Throwable $throwable) {
            // TODO: discuss about direct information to REIFF
            $this->logger->error(
                'Offer could not be accepted.',
                [
                    'exception' => $throwable,
                ]
            );

            return false;
        }

        if (!$apiResponse->isSuccess()) {
            return false;
        }

        $offer = $apiResponse->getDocuments()->getOfferByNumber($offerNumber);

        if ($offer === null) {
            return false;
        }

        $cart    = $this->convert($offer, $salesChannelContext);
        $orderId = $this->orderPersister->persist($cart, $salesChannelContext);

        return !empty($orderId);
    }

    public function convert(OfferDocumentStruct $offerDocumentStruct, SalesChannelContext $context): Cart
    {
        $customer = $context->getCustomer();

        if (!$customer) {
            throw new CustomerNotLoggedInException();
        }

        $offerNumber = $offerDocumentStruct->getNumber();

        if ($offerNumber === null) {
            throw new \RuntimeException('Offer has no offer number. Order creation failed.');
        }

        $cart = $this->cartService->createNew(md5($offerNumber), sprintf('offer-%s', $offerNumber));
        $cart->addExtension(OrderConverter::ORIGINAL_ORDER_NUMBER, new IdStruct(sprintf('OFFER-%s', $offerNumber)));
        $positions = $offerDocumentStruct->getPositions();

        $productsByNumber = $this->getProductsByNumber($positions, $context);
        $this->addLineItemsToCart($cart, $positions, $productsByNumber, $context);

        if ($offerDocumentStruct->getAdditionalCosts() !== null && $offerDocumentStruct->getAdditionalCosts() > 0.0) {
            $lineItem = $this->customLineItemFactory->create(
                [
                    'id'              => Uuid::randomHex(),
                    'type'            => LineItem::CUSTOM_LINE_ITEM_TYPE,
                    'stackable'       => true,
                    'removable'       => false,
                    'label'           => 'Zusatzkosten', // TODO: translate
                    'quantity'        => 1,
                    'priceDefinition' => [
                        'type'     => QuantityPriceDefinition::TYPE,
                        'price'    => $offerDocumentStruct->getAdditionalCosts(),
                        'quantity' => 1,
                    ],
                ],
                $context
            );

            $cart->add($lineItem);
        }

        $cart->addExtension(
            DeliveryProcessor::MANUAL_SHIPPING_COSTS,
            $this->absolutePriceCalculator->calculate($offerDocumentStruct->getOrderFee() ?? 0.0, new PriceCollection(), $context)
        );

        return $this->processor->process($cart, $context, new CartBehavior([
            ProductCartProcessor::SKIP_PRODUCT_STOCK_VALIDATION => true,
        ]));
    }

    private function addLineItemsToCart(
        Cart $cart,
        OfferDocumentPositionCollection $positions,
        array $productsByNumber,
        SalesChannelContext $context,
    ): void {
        foreach ($positions as $position) {
            $product      = $productsByNumber[$position->getNumber()] ?? null;
            $id           = Uuid::randomHex();
            $type         = LineItem::CUSTOM_LINE_ITEM_TYPE;
            $referencedId = $id;

            if ($product !== null) {
                $type         = LineItem::PRODUCT_LINE_ITEM_TYPE;
                $referencedId = $product->getId();
            }

            $lineItem = $this->customLineItemFactory->create(
                [
                    'id'              => $id,
                    'type'            => $type,
                    'stackable'       => true,
                    'removable'       => false,
                    'label'           => $position->getDescription(),
                    'referencedId'    => $referencedId,
                    'quantity'        => (int) ($position->getOrderQuantity() ?? 1),
                    'priceDefinition' => [
                        'type' => QuantityPriceDefinition::TYPE,
                        // AbsolutePriceDefinition would maybe be better but leads to removal of the items during CustomCartProcessor
                        'price' => $position->getItemPrice(),
                        // TODO: Check prices. Do we even need them?
                        'quantity' => (int) ($position->getOrderQuantity() ?? 1),
                        'taxRules' => [],
                    ],
                ],
                $context
            );

            $cart->add($lineItem);
        }
    }

    private function getProductsByNumber(OfferDocumentPositionCollection $positions, SalesChannelContext $context): array
    {
        $productNumbers = [];
        foreach ($positions as $position) {
            $productNumber = $position->getNumber();

            if ($productNumber !== null) {
                $productNumbers[] = $position->getNumber();
            }
        }

        $productCriteria = new Criteria();
        $productCriteria->addFilter(new EqualsAnyFilter('productNumber', $productNumbers));
        $products = $this->productRepository->search($productCriteria, $context->getContext())->getEntities();

        $productsByNumber = [];
        /** @var ProductEntity $product */
        foreach ($products as $product) {
            $productsByNumber[$product->getProductNumber()] = $product;
        }

        return $productsByNumber;
    }
}
