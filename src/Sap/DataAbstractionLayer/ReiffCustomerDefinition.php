<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\DataAbstractionLayer;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ReiffCustomerDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'reiff_customer';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ReiffCustomerEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ReiffCustomerCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new OneToOneAssociationField('customer', 'customer_id', 'id', CustomerDefinition::class, false),
            (new StringField('debtor_number', 'debtorNumber'))->addFlags(new ApiAware()),
            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new Required(), new PrimaryKey()),
        ]);
    }
}
