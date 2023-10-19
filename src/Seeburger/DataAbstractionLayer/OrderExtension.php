<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\DataAbstractionLayer;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderExtension extends EntityExtension
{
    public const EXTENSION_NAME = 'reiffOrder';

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToOneAssociationField(
                self::EXTENSION_NAME,
                'id',
                'order_id',
                ReiffOrderDefinition::class,
                true
            )
        );
    }

    public function getDefinitionClass(): string
    {
        return OrderDefinition::class;
    }
}
