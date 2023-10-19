<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\DataAbstractionLayer;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CategoryExtension extends EntityExtension
{
    public const EXTENSION_NAME = 'reiffCategory';

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToOneAssociationField(
                self::EXTENSION_NAME,
                'id',
                'category_id',
                ReiffCategoryDefinition::class,
                true
            )
        );
    }

    public function getDefinitionClass(): string
    {
        return CategoryDefinition::class;
    }
}
