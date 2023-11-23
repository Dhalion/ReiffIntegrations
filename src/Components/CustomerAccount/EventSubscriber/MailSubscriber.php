<?php

declare(strict_types=1);

namespace ReiffIntegrations\Components\CustomerAccount\EventSubscriber;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MailSubscriber implements EventSubscriberInterface
{
    public const DOUBLE_OPT_IN_EVENT = 'checkout.customer.double_opt_in_registration';

    public function __construct(
        private readonly EntityRepository $customerAddressRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MailBeforeValidateEvent::class => 'onMailBeforeValidate',
        ];
    }

    public function onMailBeforeValidate(MailBeforeValidateEvent $event): void
    {
        try {
            $templateData = $event->getTemplateData();
            $eventName    = $templateData['eventName'] ?? null;

            if ($eventName !== self::DOUBLE_OPT_IN_EVENT) {
                return;
            }

            /** @var CustomerEntity $customer */
            $customer = $templateData['customer'] ?? null;

            if (!$customer) {
                return;
            }

            if ($customer->getDefaultBillingAddressId() && !$customer->getDefaultBillingAddress()) {
                $customer->setDefaultBillingAddress($this->getAddressById($customer->getDefaultBillingAddressId(), $event->getContext()));
            }

            if ($customer->getDefaultShippingAddressId() && !$customer->getDefaultShippingAddress()) {
                $customer->setDefaultShippingAddress($this->getAddressById($customer->getDefaultShippingAddressId(), $event->getContext()));
            }

            $templateData['customer'] = $customer;

            $event->setTemplateData($templateData);
        } catch (\Exception) {
        }
    }

    private function getAddressById(string $addressId, Context $context): ?CustomerAddressEntity
    {
        $criteria = new Criteria([$addressId]);
        $criteria->addAssociation('country');
        $criteria->addAssociation('countryState');
        $criteria->addAssociation('salutation');

        return $this->customerAddressRepository->search($criteria, $context)->first();
    }
}
