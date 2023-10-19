<?php

declare(strict_types=1);

namespace ReiffIntegrations\Components\Order\Validator;

use ReiffIntegrations\Components\Order\EventSubscriber\OrderPlacedEventSubscriber;
use ReiffIntegrations\Components\Order\Validator\Error\InvalidCommissionOrderError;
use ReiffIntegrations\Components\Order\Validator\Error\InvalidCommissionProductError;
use ReiffIntegrations\Components\Order\Validator\Error\InvalidCustomOrderNumberError;
use ReiffIntegrations\Seeburger\DataConverter\OrderIdocConverter;
use Shopware\B2B\Order\BridgePlatform\OrderServiceDecorator;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGenerator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;

class MaxLengthValidator implements CartValidatorInterface
{
    public function __construct(private RequestStack $requestStack, private NumberRangeValueGenerator $numberRangeValueGenerator)
    {
    }

    public function validate(Cart $cart, ErrorCollection $errorCollection, SalesChannelContext $salesChannelContext): void
    {
        $currentRequest = $this->requestStack->getCurrentRequest();

        if ($currentRequest === null) {
            $currentRequest = $this->requestStack->getMainRequest();

            if ($currentRequest === null) {
                return;
            }
        }

        /** @phpstan-ignore-next-line */
        $this->validateOrderNumberLength((string) ($currentRequest->get(OrderServiceDecorator::ORDER_REFERENCE_KEY) ?? ''), $salesChannelContext, $errorCollection);
        /** @phpstan-ignore-next-line */
        $this->validateCommissionOrder((string) ($currentRequest->get(OrderPlacedEventSubscriber::FORM_KEY_ORDER_COMMISSION) ?? ''), $errorCollection);
        /** @phpstan-ignore-next-line */
        $this->validateCommissionProduct($currentRequest->get(OrderPlacedEventSubscriber::FORM_KEY_PRODUCT_COMMISSION) ?? [], $errorCollection);
    }

    private function validateOrderNumberLength(
        string $customOrderNumber,
        SalesChannelContext $salesChannelContext,
        ErrorCollection $errorCollection
    ): void {
        if (empty($customOrderNumber)) {
            return;
        }

        $generatedOrderNumber = $this->numberRangeValueGenerator->getValue(
            OrderDefinition::ENTITY_NAME,
            $salesChannelContext->getContext(),
            $salesChannelContext->getSalesChannelId(),
            true
        );
        $combinedData        = sprintf(OrderIdocConverter::ORDERNUMBER_FORMAT, $generatedOrderNumber, '');
        $calculatedMaxLength = OrderIdocConverter::I_DOC_LENGTH_ORDER_NUMBER - strlen($combinedData);

        if (strlen($customOrderNumber) > $calculatedMaxLength) {
            $errorCollection->add(new InvalidCustomOrderNumberError($customOrderNumber, $calculatedMaxLength));
        }
    }

    private function validateCommissionOrder(
        string $orderCommission,
        ErrorCollection $errorCollection
    ): void {
        if (empty($orderCommission)) {
            return;
        }

        if (strlen($orderCommission) > OrderIdocConverter::I_DOC_LENGTH_COMMISSION_ORDER) {
            $errorCollection->add(new InvalidCommissionOrderError($orderCommission, OrderIdocConverter::I_DOC_LENGTH_COMMISSION_ORDER));
        }
    }

    private function validateCommissionProduct(
        array $productCommissions,
        ErrorCollection $errorCollection
    ): void {
        if (empty($productCommissions)) {
            return;
        }

        foreach ($productCommissions as $itemKey => $productCommission) {
            if (strlen($productCommission) > OrderIdocConverter::I_DOC_LENGTH_COMMISSION_PRODUCT) {
                $errorCollection->add(new InvalidCommissionProductError($itemKey, $productCommission, OrderIdocConverter::I_DOC_LENGTH_COMMISSION_PRODUCT));
            }
        }
    }
}
