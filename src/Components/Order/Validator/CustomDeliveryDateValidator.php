<?php

declare(strict_types=1);

namespace ReiffIntegrations\Components\Order\Validator;

use ReiffIntegrations\Components\Order\Validator\Error\InvalidDeliveryDateError;
use Shopware\B2B\Order\BridgePlatform\OrderServiceDecorator;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;

class CustomDeliveryDateValidator implements CartValidatorInterface
{
    public function __construct(private RequestStack $requestStack)
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

        /** Is formatted like `2022-10-31T00:00:00+00:00` */
        /** @phpstan-ignore-next-line */
        $deliveryDate = (string) $currentRequest->get(OrderServiceDecorator::REQUESTED_DELIVERY_DATE_KEY);

        if (empty($deliveryDate)) {
            return;
        }

        try {
            if ($this->isDateInFuture($deliveryDate)) {
                return;
            }
        } catch (\Throwable $t) {
            // silentfail -> error is added later
        }

        $errorCollection->add(new InvalidDeliveryDateError($deliveryDate));
    }

    /**
     * @throws \Exception
     */
    private function isDateInFuture(string $deliveryDate): bool
    {
        $convertedDeliveryDate = (new \DateTimeImmutable($deliveryDate));
        $today                 = (new \DateTimeImmutable('today'))->setTime(23, 59, 59, 59);

        return $convertedDeliveryDate > $today;
    }
}
