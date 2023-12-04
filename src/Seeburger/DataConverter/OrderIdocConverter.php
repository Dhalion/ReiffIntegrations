<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\DataConverter;

use ReiffIntegrations\Installer\CustomFieldInstaller;
use ReiffIntegrations\Sap\DataAbstractionLayer\CustomerExtension;
use ReiffIntegrations\Sap\DataAbstractionLayer\ReiffCustomerEntity;
use ReiffIntegrations\Seeburger\Exception\InvalidDeliveryDateException;
use ReiffIntegrations\Seeburger\Exception\ProductNotFoundException;
use ReiffIntegrations\Seeburger\Struct\IdocColumn;
use ReiffIntegrations\Seeburger\Struct\IdocColumnCollection;
use ReiffIntegrations\Seeburger\Struct\IdocRow;
use ReiffIntegrations\Seeburger\Struct\IdocRowCollection;
use Shopware\B2B\Order\BridgePlatform\OrderServiceDecorator;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;

class OrderIdocConverter
{
    public const MANDATE_NUMBER                   = '100';
    public const DEFAULT_FORMAT_KEY               = '*';
    public const CUSTOM_FIELD_ORDER_REFERENCE_KEY = OrderServiceDecorator::ORDER_REFERENCE_KEY;
    public const CUSTOM_FIELD_DELIVERY_DATE_KEY   = OrderServiceDecorator::REQUESTED_DELIVERY_DATE_KEY;
    public const DATE_FORMAT                      = 'Ymd';
    public const ORDERNUMBER_FORMAT               = '%s/%s';
    public const I_DOC_LENGTH_ORDER_NUMBER        = 35;
    public const I_DOC_LENGTH_COMMISSION_PRODUCT  = 70;
    public const I_DOC_LENGTH_COMMISSION_ORDER    = 70;
    public const I_DOC_LENGTH_CUSTOMER_COMMENT    = 70;

    private const DEFAULT_UNIT = 'PCE';

    public function convert(OrderEntity $order): IdocRowCollection
    {
        $idoc = new IdocRowCollection();
        $this->addIdocHeader($idoc, $order);
        $this->addGeneralHeader($idoc, $order);
        $this->addOrganisationalData($idoc);
        $this->addDateRows($idoc, $order);
        $this->addPartnerInformation($idoc, $order);
        $this->addReferenceData($idoc, $order);
        $this->addPaymentHeader($idoc, $order);
        $this->addDocumentHead($idoc, $order);
        $this->addObjectIdentification($order, $idoc);
        $idoc->validate();

        return $idoc;
    }

    private function getBasicIdocColumns(string $identifier): IdocColumnCollection
    {
        return new IdocColumnCollection(
            [
                new IdocColumn('TABNAM', 30, $identifier),
                new IdocColumn('MANDT', 3, self::MANDATE_NUMBER),
                new IdocColumn('DOCNUM', 16, ''),
                new IdocColumn('SEGNUM', 6, ''),
                new IdocColumn('PSGNUM', 6, ''),
                new IdocColumn('HLEVEL', 2, ''),
            ]
        );
    }

    private function addIdocHeader(IdocRowCollection $idoc, OrderEntity $order): void
    {
        $idoc->add(
            new IdocRow(
                'EDI_DC40',
                new IdocColumnCollection([
                    new IdocColumn('TABNAM', 10, 'EDI_DC40'),
                    new IdocColumn('MANDT', 3, '100'),
                    new IdocColumn('---', 22, ''),
                    new IdocColumn('---', 4, '2'),
                    new IdocColumn('---', 60, 'ORDERS05'),
                    new IdocColumn('---', 59, 'ORDERS'),
                    new IdocColumn('---', 105, sprintf('KUAG%s', $this->getDebtorNumber($order))),
                    new IdocColumn('---', 10, 'SAPPRD'),
                    new IdocColumn('---', 4, 'LS'),
                    new IdocColumn('---', 235, 'PRDCLNT100'),
                ])
            )
        );
    }

    private function addGeneralHeader(IdocRowCollection $idoc, OrderEntity $order): void
    {
        $identifier        = 'E2EDK01005';
        $orderCustomFields = $order->getCustomFields() ?? [];

        $columns = $this->getBasicIdocColumns($identifier);
        $columns->add(new IdocColumn('ACTION', 3, ''));
        $columns->add(new IdocColumn('KZABS', 1, ''));
        $columns->add(new IdocColumn('CURCY', 3, ''));
        $columns->add(new IdocColumn('HWAER', 3, ''));
        $columns->add(new IdocColumn('WKURS', 12, ''));
        $columns->add(new IdocColumn('ZTERM', 17, ''));
        $columns->add(new IdocColumn('KUNDEUINR', 20, ''));
        $columns->add(new IdocColumn('EIGENUINR', 20, ''));
        $columns->add(new IdocColumn('BSART', 4, ''));
        $columns->add(new IdocColumn('BELNR', self::I_DOC_LENGTH_ORDER_NUMBER, $this->getOrderNumber($order)));
        $columns->add(new IdocColumn('NTGEW', 18, ''));
        $columns->add(new IdocColumn('BRGEW', 18, ''));
        $columns->add(new IdocColumn('GEWEI', 3, ''));
        $columns->add(new IdocColumn('FKART_RL', 4, ''));
        $columns->add(new IdocColumn('ABLAD', 25, ''));
        $columns->add(new IdocColumn('BSTZD', 4, ''));
        $columns->add(new IdocColumn('VSART', 2, ''));
        $columns->add(new IdocColumn('VSART_BEZ', 20, ''));
        $columns->add(new IdocColumn('RECIPNT_NO', 10, ''));
        $columns->add(new IdocColumn('KZAZU', 1, ''));
        $columns->add(new IdocColumn('AUTLF', 1, $this->isCompleteDelivery($orderCustomFields) ? 'X' : ''));

        $idoc->add(new IdocRow($identifier, $columns));
    }

    private function addPaymentHeader(IdocRowCollection $idoc, OrderEntity $order): void
    {
        $identifier = 'E2EDK18';

        $orderTransactions = $order->getTransactions();

        if (!$orderTransactions) {
            return;
        }

        $transaction = $orderTransactions->last();

        $paymentMethod = $transaction->getPaymentMethod();

        if (!$paymentMethod
            || !isset($paymentMethod->getCustomFields()[CustomFieldInstaller::PAYMENT_TERMS_OF_PAYMENT])
            || !$paymentMethod->getCustomFields()[CustomFieldInstaller::PAYMENT_TERMS_OF_PAYMENT]) {
            return;
        }

        $termsOfPayment = $paymentMethod->getCustomFields()[CustomFieldInstaller::PAYMENT_TERMS_OF_PAYMENT];
        $transactionId  = $transaction->getCustomFields()['crefo_pay_transaction_id'] ?? 'UNDEFINED';

        $columns = $this->getBasicIdocColumns($identifier);
        $columns->add(new IdocColumn('QUALF', 3, $termsOfPayment));
        $columns->add(new IdocColumn('TAGE', 8, ''));
        $columns->add(new IdocColumn('PRZNT', 8, ''));
        $columns->add(new IdocColumn('ZTERM_TXT', 70, $transactionId));

        $idoc->add(new IdocRow($identifier, $columns));
    }

    private function addDateRows(IdocRowCollection $idoc, OrderEntity $order): void
    {
        $deliveryDate = $this->getRequestedDeliveryDate($order);

        $identifier = 'E2EDK03';
        $columns    = $this->getBasicIdocColumns($identifier);
        $columns->add(new IdocColumn('IDDAT', 3, '002'));
        $columns->add(new IdocColumn('DATUM', 8, $deliveryDate === null ? $order->getOrderDate()->format(self::DATE_FORMAT) : $deliveryDate->format(self::DATE_FORMAT)));
        $idoc->add(new IdocRow($identifier, $columns));

        $columns = $this->getBasicIdocColumns($identifier);
        $columns->add(new IdocColumn('IDDAT', 3, '012'));
        $idoc->add(new IdocRow($identifier, $columns));

        $columns = $this->getBasicIdocColumns($identifier);
        $columns->add(new IdocColumn('IDDAT', 3, '041'));
        $idoc->add(new IdocRow($identifier, $columns));
    }

    private function addOrganisationalData(IdocRowCollection $idoc): void
    {
        $identifier = 'E2EDK14';
        $columns    = $this->getBasicIdocColumns($identifier);
        $columns->add(new IdocColumn('QUALF', 3, '006'));
        $columns->add(new IdocColumn('ORGID', 2, '00'));
        $idoc->add(new IdocRow($identifier, $columns));

        $columns = $this->getBasicIdocColumns($identifier);
        $columns->add(new IdocColumn('QUALF', 3, '007'));
        $columns->add(new IdocColumn('ORGID', 35, '10'));
        $idoc->add(new IdocRow($identifier, $columns));

        $columns = $this->getBasicIdocColumns($identifier);
        $columns->add(new IdocColumn('QUALF', 3, '008'));
        $columns->add(new IdocColumn('ORGID', 35, '1004'));
        $idoc->add(new IdocRow($identifier, $columns));

        $columns = $this->getBasicIdocColumns($identifier);
        $columns->add(new IdocColumn('QUALF', 3, '010'));
        $columns->add(new IdocColumn('ORGID', 35, 'SHP'));
        $idoc->add(new IdocRow($identifier, $columns));
    }

    private function addPartnerInformation(IdocRowCollection $idoc, OrderEntity $order): void
    {
        $identifier    = 'E2EDKA1003';
        $orderCustomer = $order->getOrderCustomer();
        $debtorNumber  = $this->getDebtorNumber($order);

        if (!$orderCustomer instanceof OrderCustomerEntity) {
            throw new \RuntimeException(sprintf('Order %s has no customer', $order->getOrderNumber()));
        }

        $billingAddress = $order->getBillingAddress();

        if (!$billingAddress instanceof OrderAddressEntity) {
            throw new \RuntimeException(sprintf('Order %s has no billing address', $order->getOrderNumber()));
        }

        $columns = $this->getBasicIdocColumns($identifier);
        $columns->add(new IdocColumn('PARVW', 3, 'AG'));
        $columns->add(new IdocColumn('PARTN', 17, $debtorNumber));
        $columns->add(new IdocColumn('LIFNR', 17, ''));
        $columns->add(new IdocColumn('NAME1', 35, $this->getTruncatedCompany($billingAddress->getCompany() ?? '')));
        $columns->add(new IdocColumn('NAME2', 35, ''));
        $columns->add(new IdocColumn('NAME3', 35, ''));

        $this->addAddressData($columns, $billingAddress);
        $columns->add(new IdocColumn('---', 529, ''));
        $columns->add(new IdocColumn('ILNNR', 70, $orderCustomer->getEmail()));
        $columns->add(new IdocColumn('PFORT', 35, ''));
        $columns->add(new IdocColumn('SPRAS_ISO', 2, $this->getOrderIsoCode($order)));
        $idoc->add(new IdocRow($identifier, $columns));

        $columns = $this->getBasicIdocColumns($identifier);
        $columns->add(new IdocColumn('PARVW', 3, 'WE'));
        $columns->add(new IdocColumn('PARTN', 17, $debtorNumber));
        $idoc->add(new IdocRow($identifier, $columns));

        $orderDeliveries = $order->getDeliveries();

        if ($orderDeliveries === null) {
            throw new \RuntimeException(sprintf('Order %s has no delivery information', $order->getOrderNumber()));
        }

        $orderDelivery = $orderDeliveries->first();

        if (!$orderDelivery instanceof OrderDeliveryEntity) {
            throw new \RuntimeException(sprintf('Order %s has no delivery information', $order->getOrderNumber()));
        }

        $shippingOrderAddress = $orderDelivery->getShippingOrderAddress();

        if (!$shippingOrderAddress instanceof OrderAddressEntity) {
            throw new \RuntimeException(sprintf('Order %s has no shipping address', $order->getOrderNumber()));
        }

        if ($billingAddress->getId() !== $shippingOrderAddress->getId()) {
            $columns->add(new IdocColumn('LIFNR', 17, ''));
            $columns->add(new IdocColumn('NAME1', 35, $this->getTruncatedCompany($shippingOrderAddress->getCompany() ?? '')));
            $columns->add(new IdocColumn('NAME2', 35, ''));
            $columns->add(new IdocColumn('NAME3', 35, ''));
            $this->addAddressData($columns, $shippingOrderAddress);
        }

        $columns = $this->getBasicIdocColumns($identifier);
        $columns->add(new IdocColumn('PARVW', 3, 'RE'));
        $columns->add(new IdocColumn('PARTN', 17, $debtorNumber));
        $columns->add(new IdocColumn('-----', 315, ''));
        $idoc->add(new IdocRow($identifier, $columns));
    }

    private function addReferenceData(IdocRowCollection $idoc, OrderEntity $order): void
    {
        $identifier = 'E2EDK02';

        $columns = $this->getBasicIdocColumns($identifier);
        $columns->add(new IdocColumn('QUALF', 3, '050'));
        $columns->add(new IdocColumn('-----', 35, ''));
        $idoc->add(new IdocRow($identifier, $columns));

        $columns = $this->getBasicIdocColumns($identifier);
        $columns->add(new IdocColumn('QUALF', 3, '001'));
        $columns->add(new IdocColumn('BELNR', self::I_DOC_LENGTH_ORDER_NUMBER, $this->getOrderNumber($order)));
        $idoc->add(new IdocRow($identifier, $columns));
    }

    private function addDocumentHead(IdocRowCollection $idoc, OrderEntity $order): void
    {
        $orderCustomFields = $order->getCustomFields() ?? [];

        if ($this->hasOrderCommission($orderCustomFields)) {
            $identifier = 'E2EDKT1002';
            $columns    = $this->getBasicIdocColumns($identifier);
            $columns->add(new IdocColumn('TDID', 4, 'Z013'));
            $idoc->add(new IdocRow($identifier, $columns));

            $identifier = 'E2EDKT2001';
            $columns    = $this->getBasicIdocColumns($identifier);
            $columns->add(new IdocColumn('TDLINE', self::I_DOC_LENGTH_COMMISSION_ORDER, $orderCustomFields[CustomFieldInstaller::ORDER_COMMISSION]));
            $columns->add(new IdocColumn('TDFORMAT', 2, self::DEFAULT_FORMAT_KEY));
            $idoc->add(new IdocRow($identifier, $columns));
        }

        if ($order->getCustomerComment() !== null) {
            $identifier = 'E2EDKT1002';
            $columns    = $this->getBasicIdocColumns($identifier);
            $columns->add(new IdocColumn('TDID', 4, 'Z014'));
            $idoc->add(new IdocRow($identifier, $columns));

            $identifier = 'E2EDKT2001';
            $columns    = $this->getBasicIdocColumns($identifier);
            $columns->add(new IdocColumn('TDLINE', self::I_DOC_LENGTH_CUSTOMER_COMMENT, $order->getCustomerComment()));
            $columns->add(new IdocColumn('TDFORMAT', 2, self::DEFAULT_FORMAT_KEY));
            $idoc->add(new IdocRow($identifier, $columns));
        }
    }

    private function addObjectIdentification(OrderEntity $order, IdocRowCollection $idoc): void
    {
        $lineItems    = $order->getLineItems();
        $deliveryDate = $this->getRequestedDeliveryDate($order);
        $orderNumber  = $order->getOrderNumber();

        if ($orderNumber === null) {
            throw new \RuntimeException(sprintf('Order %s has no order number.', $order->getId()));
        }

        if ($lineItems === null) {
            throw new \RuntimeException(sprintf('Order %s has no lineitems.', $orderNumber));
        }

        $lineItems->sortByPosition();
        $productLineItems = $lineItems->filterByType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        /** @var OrderLineItemEntity $lineItem */
        foreach ($productLineItems as $lineItem) {
            $identifier           = 'E2EDP01006';
            $product              = $lineItem->getProduct();
            $lineItemCustomFields = $lineItem->getCustomFields() ?? [];

            if (!$product instanceof ProductEntity) {
                throw new ProductNotFoundException($orderNumber, $lineItem->getId());
            }

            $columns = $this->getBasicIdocColumns($identifier);
            $columns->add(new IdocColumn('POSEX', 6, (string) ($lineItem->getPosition() * 10)));
            $columns->add(new IdocColumn('---', 5, ''));
            $columns->add(new IdocColumn('MENGE', 15, number_format($lineItem->getQuantity(), 2, '.', '')));
            $columns->add(new IdocColumn('MENEE', 3, $this->getUnitIdentifier($product)));
            $idoc->add(new IdocRow($identifier, $columns));

            $identifier = 'E2EDP02001';
            $columns    = $this->getBasicIdocColumns($identifier);
            $columns->add(new IdocColumn('QUALF', 3, '001'));
            $columns->add(new IdocColumn('BELNR', self::I_DOC_LENGTH_ORDER_NUMBER, $this->getOrderNumber($order)));
            $idoc->add(new IdocRow($identifier, $columns));

            $identifier = 'E2EDP03';
            $columns    = $this->getBasicIdocColumns($identifier);
            $columns->add(new IdocColumn('IDDAT', 3, '002'));
            $columns->add(new IdocColumn('DATUM', 8, $deliveryDate === null ? '' : $deliveryDate->format(self::DATE_FORMAT)));
            $columns->add(new IdocColumn('UZEIT', 6, ''));
            $idoc->add(new IdocRow($identifier, $columns));

            $identifier = 'E2EDPA1003';
            $columns    = $this->getBasicIdocColumns($identifier);
            $columns->add(new IdocColumn('PARVW', 3, 'WE'));
            $idoc->add(new IdocRow($identifier, $columns));

            $identifier = 'E2EDP19001';
            $columns    = $this->getBasicIdocColumns($identifier);
            $columns->add(new IdocColumn('QUALF', 3, '002'));
            $columns->add(new IdocColumn('IDTNR', 35, $product->getProductNumber()));
            $idoc->add(new IdocRow($identifier, $columns));

            if ($this->hasLineItemCommission($lineItemCustomFields)) {
                $identifier = 'E2EDPT1001';
                $columns    = $this->getBasicIdocColumns($identifier);
                $columns->add(new IdocColumn('TDID', 4, 'Z008'));
                $idoc->add(new IdocRow($identifier, $columns));

                $identifier = 'E2EDPT2001';
                $columns    = $this->getBasicIdocColumns($identifier);
                $columns->add(new IdocColumn('TDLINE', self::I_DOC_LENGTH_COMMISSION_PRODUCT, $lineItemCustomFields[CustomFieldInstaller::ORDER_LINE_ITEM_COMMISSION]));
                $columns->add(new IdocColumn('TDFORMAT', 2, self::DEFAULT_FORMAT_KEY));
                $idoc->add(new IdocRow($identifier, $columns));
            }
        }
    }

    private function addAddressData(IdocColumnCollection $columns, OrderAddressEntity $address): void
    {
        $columns->add(new IdocColumn('NAME4', 35, $this->getName($address)));
        $columns->add(new IdocColumn('STRAS', 35, $address->getStreet()));
        $columns->add(new IdocColumn('STRS2', 35, $address->getAdditionalAddressLine1() ?? ''));
        $columns->add(new IdocColumn('PFACH', 35, ''));
        $columns->add(new IdocColumn('ORT01', 35, $address->getCity()));
        $columns->add(new IdocColumn('COUNC', 9, ''));
        $columns->add(new IdocColumn('PSTLZ', 9, $address->getZipcode()));
        $columns->add(new IdocColumn('PSTL2', 9, ''));
    }

    private function hasReferenceNumber(array $orderCustomFields): bool
    {
        return isset($orderCustomFields[self::CUSTOM_FIELD_ORDER_REFERENCE_KEY]) && $orderCustomFields[self::CUSTOM_FIELD_ORDER_REFERENCE_KEY] !== '';
    }

    private function hasDeliveryDate(array $orderCustomFields): bool
    {
        return isset($orderCustomFields[self::CUSTOM_FIELD_DELIVERY_DATE_KEY]) && $orderCustomFields[self::CUSTOM_FIELD_DELIVERY_DATE_KEY] !== '';
    }

    private function getOrderNumber(OrderEntity $order): string
    {
        $orderNumber = $order->getOrderNumber();

        if ($orderNumber === null) {
            throw new \RuntimeException(sprintf('Order %s has no order number.', $order->getId()));
        }

        $orderCustomFields = $order->getCustomFields() ?? [];

        if ($this->hasReferenceNumber($orderCustomFields)) {
            return sprintf(
                self::ORDERNUMBER_FORMAT,
                $orderNumber,
                $orderCustomFields[self::CUSTOM_FIELD_ORDER_REFERENCE_KEY]
            );
        }

        return $orderNumber;
    }

    private function getRequestedDeliveryDate(OrderEntity $order): ?\DateTimeInterface
    {
        $orderCustomFields = $order->getCustomFields() ?? [];

        if ($this->hasDeliveryDate($orderCustomFields)) {
            $deliveryDateString = $orderCustomFields[self::CUSTOM_FIELD_DELIVERY_DATE_KEY];
            $orderNumber        = $order->getOrderNumber();

            if ($orderNumber === null) {
                throw new \RuntimeException(sprintf('Order %s has no order number.', $order->getId()));
            }

            try {
                $deliveryDate = new \DateTimeImmutable($deliveryDateString);
            } catch (\Throwable $throwable) {
                throw new InvalidDeliveryDateException($orderNumber, $deliveryDateString, $throwable->getCode(), $throwable);
            }

            return $deliveryDate;
        }

        return null;
    }

    private function getName(OrderAddressEntity $adress): string
    {
        $salutation = $adress->getSalutation();

        return sprintf(
            '%s %s %s',
            $salutation !== null ? $salutation->getDisplayName() ?? '' : '',
            $adress->getFirstName(),
            $adress->getLastName()
        );
    }

    private function getOrderIsoCode(OrderEntity $order): string
    {
        $locale = $order->getLanguage()->getLocale();

        if ($locale === null) {
            return '';
        }

        return explode('-', $locale->getCode())[1] ?? '';
    }

    private function getDebtorNumber(OrderEntity $order): string
    {
        $orderCustomer = $order->getOrderCustomer();

        if (!$orderCustomer instanceof OrderCustomerEntity) {
            throw new \RuntimeException(sprintf('Order %s has no order customer', $order->getOrderNumber()));
        }

        $customer = $orderCustomer->getCustomer();

        if (!$customer instanceof CustomerEntity) {
            throw new \RuntimeException(sprintf('Order %s has no customer', $order->getOrderNumber()));
        }

        $reiffCustomer = $customer->getExtension(CustomerExtension::EXTENSION_NAME);

        if (!$reiffCustomer instanceof ReiffCustomerEntity) {
            throw new \RuntimeException(sprintf('Order %s has no REIFF customer', $order->getOrderNumber()));
        }

        $debtorNumber = $reiffCustomer->getDebtorNumber();

        if ($debtorNumber === null) {
            throw new \RuntimeException(sprintf('Customer for order %s has no debtor number', $order->getOrderNumber()));
        }

        return str_pad($debtorNumber, strlen($debtorNumber) - mb_strlen($debtorNumber) + 10, '0', STR_PAD_LEFT);
    }

    private function hasLineItemCommission(array $lineItemCustomFields): bool
    {
        return isset($lineItemCustomFields[CustomFieldInstaller::ORDER_LINE_ITEM_COMMISSION])
            && $lineItemCustomFields[CustomFieldInstaller::ORDER_LINE_ITEM_COMMISSION] !== null
            && $lineItemCustomFields[CustomFieldInstaller::ORDER_LINE_ITEM_COMMISSION] !== '';
    }

    private function hasOrderCommission(array $orderCustomFields): bool
    {
        return isset($orderCustomFields[CustomFieldInstaller::ORDER_COMMISSION])
            && $orderCustomFields[CustomFieldInstaller::ORDER_COMMISSION] !== null
            && $orderCustomFields[CustomFieldInstaller::ORDER_COMMISSION] !== '';
    }

    private function isCompleteDelivery(array $orderCustomFields): bool
    {
        return isset($orderCustomFields[CustomFieldInstaller::ORDER_COMPLETE_DELIVERY])
            && $orderCustomFields[CustomFieldInstaller::ORDER_COMPLETE_DELIVERY] === true;
    }

    private function getUnitIdentifier(ProductEntity $product): string
    {
        $unit = $product->getUnit();

        if ($unit === null) {
            return self::DEFAULT_UNIT;
        }

        $unitCustomFields = $unit->getCustomFields() ?? [];

        return $unitCustomFields[CustomFieldInstaller::UNIT_SAP_IDENTIFIER] ?? self::DEFAULT_UNIT;
    }

    private function getTruncatedCompany(string $value): string
    {
        return mb_substr($value, 0, 35); // 35 is the fixed max-length for the company field
    }
}
