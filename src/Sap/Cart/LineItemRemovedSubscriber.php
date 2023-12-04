<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Cart;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Event\AfterLineItemRemovedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Fix for PriceCartProcessor, because we are setting there extension, that prevents basket from the deletion from the database.
 *
 * $original->addExtensions([DeliveryProcessor::MANUAL_SHIPPING_COSTS => new CalculatedPrice(
 * $this->cashRounding->cashRound($sapShippingData->getTotalPrice(), $roundingConfig),
 * $this->cashRounding->cashRound($sapShippingData->getTotalPrice(), $roundingConfig),
 * new CalculatedTaxCollection(),
 * $oldShippingCosts->getTaxRules(),
 * 1
 * )]);
 */
class LineItemRemovedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AbstractCartPersister $cartPersister
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterLineItemRemovedEvent::class => ['onAfterLineItemRemoved', -55555],
        ];
    }

    public function onAfterLineItemRemoved(AfterLineItemRemovedEvent $event): void
    {
        try {
            if ($event->getCart()->getLineItems()->count() === 0) {
                $this->cartPersister->delete($event->getSalesChannelContext()->getToken(), $event->getSalesChannelContext());
            }
        } catch (\Exception $exception) {
        }
    }
}
