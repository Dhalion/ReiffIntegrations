<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\DataAbstractionLayer;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ReiffMediaDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'reiff_media';
    }

    public function getEntityClass(): string
    {
        return ReiffMediaEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ReiffMediaCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new OneToOneAssociationField('media', 'media_id', 'id', MediaDefinition::class, false),
            new StringField('hash', 'hash'),
            new StringField('original_path', 'originalPath'),
            (new FkField('media_id', 'mediaId', MediaDefinition::class))->addFlags(new Required(), new PrimaryKey()),
        ]);
    }
}
