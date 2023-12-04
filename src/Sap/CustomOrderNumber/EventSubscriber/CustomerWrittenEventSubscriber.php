<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\CustomOrderNumber\EventSubscriber;

use ReiffIntegrations\Sap\CustomOrderNumber\MessageHandler\OrderNumberUpdateMessageHandler;
use ReiffIntegrations\Sap\CustomOrderNumber\Struct\OrderNumberUpdateStruct;
use ReiffIntegrations\Sap\DataAbstractionLayer\ReiffCustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class CustomerWrittenEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly OrderNumberUpdateMessageHandler $messageHandler,
        private readonly MessageBusInterface $messageBus,
        private readonly EntityRepository $reiffCustomerRepository,
        private readonly EntityRepository $customerRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten',
        ];
    }

    public function onCustomerWritten(EntityWrittenEvent $event): void
    {
        if ($event->getEntityName() !== CustomerDefinition::ENTITY_NAME) {
            return;
        }

        foreach ($event->getPayloads() as $payload) {
            if (!array_key_exists('id', $payload) || empty($payload['id'])) {
                continue;
            }

            $debtorData = $this->getDebtorByCustomerId($payload['id'], $event->getContext());

            if (!$debtorData && $event->getContext()->getScope() === Context::CRUD_API_SCOPE) {
                $this->createCustomerExtension($payload['id'], $event->getContext());
            }

            if (isset($payload['active']) && $payload['active'] === true && $event->getContext()->getScope() === Context::CRUD_API_SCOPE) {
                $this->confirmDoubleOptIn($payload['id'], $event->getContext());
            }

            if ($debtorData === null || empty($debtorData->getDebtorNumber()) || empty($debtorData->getCustomerId())) {
                continue;
            }

            $messageStruct = new OrderNumberUpdateStruct($debtorData->getDebtorNumber(), $debtorData->getCustomerId());
            $message       = $this->messageHandler->getMessage($messageStruct, $event->getContext());

            $this->messageBus->dispatch($message);
        }
    }

    private function getDebtorByCustomerId(string $customerId, Context $context): ?ReiffCustomerEntity
    {
        $customerSearchCriteria = new Criteria();
        $customerSearchCriteria->addFilter(new NotFilter(NotFilter::CONNECTION_OR, [
            new EqualsFilter('debtorNumber', null),
            new EqualsFilter('customerId', null),
        ]));
        $customerSearchCriteria->addFilter(new EqualsFilter('customerId', $customerId));

        return $this->reiffCustomerRepository->search($customerSearchCriteria, $context)->first();
    }

    private function createCustomerExtension(string $customerId, Context $context): void
    {
        $this->reiffCustomerRepository->upsert([
            [
                'customerId'   => $customerId,
                'debtorNumber' => null,
            ],
        ], $context);
    }

    private function confirmDoubleOptIn(string $customerId, Context $context): void
    {
        $this->customerRepository->upsert([
            [
                'id'                     => $customerId,
                'doubleOptInConfirmDate' => new \DateTimeImmutable(),
            ],
        ], $context);
    }
}
