<?php

declare(strict_types=1);

namespace ReiffIntegrations\Components\CustomerAccount\Helper;

use Psr\Log\LoggerInterface;
use Shopware\B2B\Acl\Framework\AclAccessWriterInterface;
use Shopware\B2B\Acl\Framework\AclGrantContext;
use Shopware\B2B\Acl\Framework\AclGrantContextProvider;
use Shopware\B2B\Address\Framework\AddressCrudService;
use Shopware\B2B\Address\Framework\AddressEntity;
use Shopware\B2B\Address\Framework\AddressService;
use Shopware\B2B\Common\IdValue;
use Shopware\B2B\Common\UuidIdValue;
use Shopware\B2B\Debtor\Framework\DebtorRepositoryInterface;
use Shopware\B2B\EasyMode\BridgePlatform\EasyModeService;
use Shopware\B2B\Role\Framework\RoleEntity;
use Shopware\B2B\Role\Framework\RoleRepository;
use Shopware\B2B\StoreFrontAuthentication\Framework\LoginContextService;
use Shopware\B2B\StoreFrontAuthentication\Framework\OwnershipContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

class AddressHelper
{
    public function __construct(
        private readonly AddressCrudService $addressCrudService,
        private readonly LoginContextService $loginContextService,
        private readonly DebtorRepositoryInterface $debtorRepository,
        private readonly RoleRepository $roleRepository,
        private readonly EasyModeService $easyModeService,
        private readonly AclGrantContextProvider $grantContextProviderChain,
        private readonly AddressService $addressService,
        private readonly AclAccessWriterInterface $aclAccessWriter,
        private readonly LoggerInterface $logger,
        private readonly EntityRepository $customerRepository,
    ) {
    }

    public function createAddresses(string $customerId, Context $context): void
    {
        $customer = $this->getCustomer($customerId, $context);

        if (!$customer) {
            throw new \RuntimeException(sprintf('Could not find the customer with ID %s', $customerId));
        }

        $debtorIdentity = $this->debtorRepository->fetchIdentityById(UuidIdValue::create($customer->getId()), $this->loginContextService);
        $easyModeRole   = $this->getOrInsertEasyModeRole($debtorIdentity->getOwnershipContext());

        if ($easyModeRole === null) {
            $this->logger->error(sprintf('The address could not be created due to missing EasyModeRole for user: %s', $customer->getCustomerNumber()));

            return;
        }

        $ownershipContext = $debtorIdentity->getOwnershipContext();
        $baseAclContext   = $this->grantContextProviderChain->fetchOneByIdentifier(
            RoleEntity::class . '::' . $easyModeRole->id->getValue(),
            $ownershipContext
        );

        $billingAddressId  = $customer->getDefaultBillingAddressId();
        $shippingAddressId = $customer->getDefaultShippingAddressId();

        if ($billingAddressId === $shippingAddressId) {
            $shippingAddressId = $this->createShippingAddress($customer, $ownershipContext, $baseAclContext);
        }

        /* Should not be possible to happen */
        if ($shippingAddressId === null) {
            throw new \RuntimeException('Could not create the shipping address');
        }

        $shippingAddressIdValue = IdValue::create($shippingAddressId);
        $billingAddressIdValue  = IdValue::create($billingAddressId);

        $this->addressService->setAddressAsB2bType($shippingAddressIdValue, $billingAddressIdValue, $ownershipContext);
        $this->aclAccessWriter->addNewSubject($ownershipContext, $baseAclContext, $shippingAddressIdValue, true);
        $this->aclAccessWriter->addNewSubject($ownershipContext, $baseAclContext, $billingAddressIdValue, true);
    }

    private function createShippingAddress(CustomerEntity $customer, OwnershipContext $ownershipContext, AclGrantContext $aclGrantContext): ?string
    {
        $shippingAddressCreated = null;

        if ($customer->getDefaultShippingAddress() !== null) {
            $shippingAddress = $this->getAddressData($customer->getDefaultShippingAddress(), $customer->getVatIds());

            $shippingAddressCreated = $this->upsertCustomerAddressToB2b(
                $shippingAddress,
                AddressEntity::TYPE_SHIPPING,
                $ownershipContext,
                $aclGrantContext
            );
        }

        /* Fallback to billing into shipping address */
        if ($shippingAddressCreated === null && $customer->getDefaultBillingAddress() !== null) {
            $shippingAddress = $this->getAddressData($customer->getDefaultBillingAddress(), $customer->getVatIds());

            $shippingAddressCreated = $this->upsertCustomerAddressToB2b(
                $shippingAddress,
                AddressEntity::TYPE_SHIPPING,
                $ownershipContext,
                $aclGrantContext
            );
        }

        return $shippingAddressCreated?->getValue();
    }

    private function getAddressData(CustomerAddressEntity $address, ?array $vatIds): array
    {
        $usedVatId     = !empty($vatIds) ? implode(', ', $vatIds) : '';
        $salutation    = $address->getSalutation();
        $salutationKey = $salutation !== null ? $salutation->getSalutationKey() : 'not_specified';

        return [
            'id'                       => Uuid::randomHex(),
            'salutation'               => $salutationKey,
            'company'                  => $address->getCompany() ?? 'not_specified',
            'department'               => $address->getDepartment() ?? '',
            'firstname'                => $address->getFirstName(),
            'lastname'                 => $address->getLastName(),
            'phone'                    => $address->getPhoneNumber() ?? '',
            'ustid'                    => $usedVatId,
            'additional_address_line1' => $address->getAdditionalAddressLine1() ?? '',
            'additional_address_line2' => $address->getAdditionalAddressLine2() ?? '',
            'country_id'               => $address->getCountryId(),
            'zipcode'                  => $address->getZipcode(),
            'city'                     => $address->getCity(),
            'street'                   => $address->getStreet(),
        ];
    }

    private function upsertCustomerAddressToB2b(array $hydratedAddressData, string $addressType, OwnershipContext $ownershipContext, AclGrantContext $aclGrantContext): ?IdValue
    {
        $newRecord = $this->addressCrudService->createNewRecordRequest($hydratedAddressData);

        try {
            $addressEntity = $this->addressCrudService->create($newRecord, $ownershipContext, $addressType, $aclGrantContext);

            return $addressEntity->id;
        } catch (\Throwable $t) {
            $this->logger->error(sprintf('The address could not be created due to an error: %s', $t->getMessage()), [
                'code' => $t->getCode(),
                'file' => $t->getFile(),
                'line' => $t->getLine(),
            ]);

            return null;
        }
    }

    private function getOrInsertEasyModeRole(OwnershipContext $ownershipContext): ?RoleEntity
    {
        return $this->easyModeService->getEasyModeRole($ownershipContext, $this->roleRepository);
    }

    private function getCustomer(string $customerId, Context $context): ?CustomerEntity
    {
        $criteria = new Criteria([$customerId]);
        $criteria
            ->addAssociation('salutation')
            ->addAssociation('addresses.salutation')
            ->addAssociation('defaultBillingAddress.salutation')
            ->addAssociation('defaultShippingAddress.salutation');

        return $this->customerRepository->search($criteria, $context)->first();
    }
}
