<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\DataAbstractionLayer;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class MediaExtension extends EntityExtension
{
    public const EXTENSION_NAME = 'reiffMedia';

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToOneAssociationField(
                self::EXTENSION_NAME,
                'id',
                'media_id',
                ReiffMediaDefinition::class,
                true
            )
        );
    }

    public function getDefinitionClass(): string
    {
        return MediaDefinition::class;
    }
}
