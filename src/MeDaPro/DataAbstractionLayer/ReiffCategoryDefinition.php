<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\DataAbstractionLayer;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ReiffCategoryDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'reiff_category';
    }

    public function getEntityClass(): string
    {
        return ReiffCategoryEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ReiffCategoryCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new OneToOneAssociationField('category', 'category_id', 'id', CategoryDefinition::class, false),
            new StringField('catalog_id', 'catalogId'),
            new StringField('uid', 'uId'),
            (new FkField('category_id', 'categoryId', CategoryDefinition::class))->addFlags(new Required(), new PrimaryKey()),
        ]);
    }
}
