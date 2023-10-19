<?php

declare(strict_types=1);

namespace ReiffIntegrations\Components\Order\EventSubscriber;

use ReiffIntegrations\Installer\CustomFieldInstaller;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class OrderPlacedEventSubscriber implements EventSubscriberInterface
{
    public const CONTEXT_STATE = 'ignoreEventListeners';
    public const FORM_KEY_COMPLETE_DELIVERY  = 'orderCompleteDeliveryIndicator';
    public const FORM_KEY_ORDER_COMMISSION   = 'orderCommissionText';
    public const FORM_KEY_PRODUCT_COMMISSION = 'productCommissionText';

    public function __construct(
        private RequestStack $requestStack,
        private EntityRepository $orderRepository,
        private EntityRepository $lineItemRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
            EntityWrittenContainerEvent::class => ['onOrderWritten', 50000],
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $orderPlacedEvent): void
    {
        $currentRequest = $this->requestStack->getCurrentRequest();

        if ($currentRequest === null) {
            $currentRequest = $this->requestStack->getMainRequest();

            if ($currentRequest === null) {
                return;
            }
        }

        $isCompleteDelivery            = false;
        $isCompleteDeliveryRequestData = $currentRequest->get(self::FORM_KEY_COMPLETE_DELIVERY, false);

        if ($isCompleteDeliveryRequestData === 'on') {
            $isCompleteDelivery = true;
        }
        $orderCommission    = $currentRequest->get(self::FORM_KEY_ORDER_COMMISSION, '');
        $productCommissions = $currentRequest->get(self::FORM_KEY_PRODUCT_COMMISSION, false);

        if (is_array($productCommissions) && !empty($productCommissions)) {
            $this->handleProductCommissions($productCommissions, $orderPlacedEvent);
        }

        $orderNewCustomFields = [
            CustomFieldInstaller::ORDER_COMPLETE_DELIVERY => $isCompleteDelivery,
            CustomFieldInstaller::ORDER_COMMISSION        => $orderCommission,
        ];

        $context = $orderPlacedEvent->getContext();

        $context->addState(self::CONTEXT_STATE);

        $this->orderRepository->upsert([
            [
                'id'           => $orderPlacedEvent->getOrderId(),
                'customFields' => $orderNewCustomFields,
            ],
        ], $context);

        $context->removeState(self::CONTEXT_STATE);

        $orderPlacedEvent->getOrder()->setCustomFields(array_merge(
            $orderPlacedEvent->getOrder()->getCustomFields() ?? [],
            $orderNewCustomFields
        ));
    }

    public function onOrderWritten(EntityWrittenContainerEvent $event): void
    {
        if($event->getContext()->hasState(self::CONTEXT_STATE)) {
            $event->stopPropagation();
        }
    }

    private function handleProductCommissions(array $productCommissions, CheckoutOrderPlacedEvent $orderPlacedEvent): void
    {
        if (empty($productCommissions)) {
            return;
        }

        $lineItems      = $orderPlacedEvent->getOrder()->getLineItems();
        $lineItemUpsert = [];

        if ($lineItems === null) {
            return;
        }

        foreach ($lineItems as $lineItem) {
            $productId = $lineItem->getProductId();

            if ($productId == null || !array_key_exists($productId, $productCommissions)) {
                continue;
            }

            $lineItemUpsert[] = [
                'id'           => $lineItem->getId(),
                'customFields' => [
                    CustomFieldInstaller::ORDER_LINE_ITEM_COMMISSION => $productCommissions[$lineItem->getProductId()],
                ],
            ];

            $lineItem->setCustomFields(array_merge(
                $lineItem->getCustomFields() ?? [],
                [
                    CustomFieldInstaller::ORDER_LINE_ITEM_COMMISSION => $productCommissions[$lineItem->getProductId()],
                ]
            ));
        }

        if (empty($lineItemUpsert)) {
            return;
        }

        $this->lineItemRepository->upsert($lineItemUpsert, $orderPlacedEvent->getContext());
    }
}
