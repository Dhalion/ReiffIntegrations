<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ContextFactoryCustomerCriteriaSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            EntitySearchedEvent::class => 'addCustomerFeaturesAssociation'
        ];
    }

    public function addCustomerFeaturesAssociation(EntitySearchedEvent $event): void
    {
        $criteria = $event->getCriteria();
        if ($criteria->getTitle() !== 'context-factory::customer') {
            return;
        }

        $criteria->addAssociation('language.translationCode');
    }
}
