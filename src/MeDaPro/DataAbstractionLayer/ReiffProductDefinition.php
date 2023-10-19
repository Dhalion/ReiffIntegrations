<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\DataAbstractionLayer;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ReiffProductDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'reiff_product';
    }

    public function getEntityClass(): string
    {
        return ReiffProductEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ReiffProductCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new OneToOneAssociationField('product', 'product_id', 'id', ProductDefinition::class, false),
            new BoolField('in_default_sortiment', 'inDefaultSortiment'),
            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required(), new PrimaryKey()),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new Required(), new PrimaryKey()),
        ]);
    }
}
