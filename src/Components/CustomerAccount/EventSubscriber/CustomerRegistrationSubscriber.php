<?php

declare(strict_types=1);

namespace ReiffIntegrations\Components\CustomerAccount\EventSubscriber;

use ReiffIntegrations\Components\CustomerAccount\Helper\AddressHelper;
use ReiffIntegrations\Installer\CustomFieldInstaller;
use ReiffIntegrations\Sap\DataAbstractionLayer\CustomerExtension;
use Shopware\B2B\EasyMode\BridgePlatform\EasyModeService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\NotBlank;

class CustomerRegistrationSubscriber implements EventSubscriberInterface
{
    public const CUSTOMER_REGISTRATION_KEY_DEBTOR_NUMBER      = 'customerDebtorNumber';
    public const CUSTOMER_REGISTRATION_KEY_INDUSTRY           = 'customerIndustry';
    public const CUSTOMER_REGISTRATION_KEY_ELECTRONIC_INVOICE = 'emailElectronicInvoice';

    public const B2B_CUSTOMER_DATA_KEY = 'b2bCustomerData';

    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly AddressHelper $addressHelper,
        private readonly RequestStack $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'framework.validation.customer.create'      => 'onCustomerValidationCreation',
            CustomerDoubleOptInRegistrationEvent::class => 'onRegister',
        ];
    }

    public function onCustomerValidationCreation(BuildValidationEvent $event): void
    {
        $event->getDefinition()->add(self::CUSTOMER_REGISTRATION_KEY_INDUSTRY, new NotBlank());
    }

    public function onRegister(CustomerDoubleOptInRegistrationEvent $event): void
    {
        $this->upsertCustomerData($event->getCustomer(), $event->getContext());
        $this->addressHelper->createAddresses($event->getCustomerId(), $event->getContext());
    }

    private function upsertCustomerData(CustomerEntity $customer, Context $context): void
    {
        $customerId           = $customer->getId();
        $customerCustomFields = $this->getCustomFields();

        $this->customerRepository->upsert([
            [
                'id'                              => $customerId,
                'customFields'                    => $customerCustomFields,
                CustomerExtension::EXTENSION_NAME => [
                    'customerId' => $customerId,
                ],
                self::B2B_CUSTOMER_DATA_KEY => [
                    'isDebtor'                                   => true,
                    'isSalesRepresentative'                      => false,
                    EasyModeService::CUSTOMER_DATA_KEY_EASY_MODE => true,
                ],
            ],
        ], $context);

        $customer->setCustomFields(array_merge(
            $customer->getCustomFields() ?? [],
            $customerCustomFields,
            [
                self::B2B_CUSTOMER_DATA_KEY => [
                    'isDebtor'                                   => true,
                    'isSalesRepresentative'                      => false,
                    EasyModeService::CUSTOMER_DATA_KEY_EASY_MODE => true,
                ],
            ]
        ));
    }

    private function getCustomFields(): array
    {
        $additionalData = [];
        $currentRequest = $this->requestStack->getCurrentRequest();

        if ($currentRequest === null) {
            return $additionalData;
        }

        $providedDebtorNumber = $currentRequest->get(self::CUSTOMER_REGISTRATION_KEY_DEBTOR_NUMBER);

        if (!empty($providedDebtorNumber)) {
            $additionalData[CustomFieldInstaller::CUSTOMER_PROVIDED_DEBTOR_NUMBER] = $providedDebtorNumber;
        }

        $industry = $currentRequest->get(self::CUSTOMER_REGISTRATION_KEY_INDUSTRY);

        if (!empty($industry)) {
            $additionalData[CustomFieldInstaller::CUSTOMER_INDUSTRY] = $industry;
        }

        $invoiceMail = $currentRequest->get(self::CUSTOMER_REGISTRATION_KEY_ELECTRONIC_INVOICE);

        if (!empty($invoiceMail)) {
            $additionalData[CustomFieldInstaller::CUSTOMER_EMAIL_ELECTRONIC_INVOICE] = $invoiceMail;
        }

        return $additionalData;
    }
}
