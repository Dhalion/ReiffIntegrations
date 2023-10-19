<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\DataAbstractionLayer;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ReiffOrderDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'reiff_order';
    }

    public function getEntityClass(): string
    {
        return ReiffOrderEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ReiffOrderCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new OneToOneAssociationField('order', 'order_id', 'id', OrderDefinition::class, false),
            new DateTimeField('exported_at', 'exportedAt'),
            new DateTimeField('queued_at', 'queuedAt'),
            new DateTimeField('notified_at', 'notifiedAt'),
            new IntField('export_tries', 'exportTries'),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required(), new PrimaryKey()),
            (new ReferenceVersionField(OrderDefinition::class))->addFlags(new Required(), new PrimaryKey()),
        ]);
    }
}
