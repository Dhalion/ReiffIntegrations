<?php

declare(strict_types=1);

namespace ReiffIntegrations\Components\Order\EventSubscriber;

use ReiffIntegrations\Seeburger\DataConverter\OrderIdocConverter;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGenerator;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CheckoutConfirmEventSubscriber implements EventSubscriberInterface
{
    public const PAGE_EXTENSION_KEY             = 'ReiffCheckoutExtension';
    public const MAX_LENGTH_CUSTOM_ORDER_NUMBER = 'maxLengthCustomOrderNumber';
    public const MAX_LENGTH_CUSTOMER_COMMENT    = 'maxLengthCustomerComment';
    public const MAX_LENGTH_COMMISSION_ORDER    = 'maxLengthCommissionOrder';
    public const MAX_LENGTH_COMMISSION_PRODUCT  = 'maxLengthCommissionProduct';

    public function __construct(private NumberRangeValueGenerator $numberRangeValueGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onConfirmPageLoaded',
        ];
    }

    public function onConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $pageLoadedEvent): void
    {
        $generatedOrderNumber = $this->numberRangeValueGenerator->getValue(OrderDefinition::ENTITY_NAME, $pageLoadedEvent->getContext(), $pageLoadedEvent->getSalesChannelContext()->getSalesChannelId(), true);
        $combinedData         = sprintf(OrderIdocConverter::ORDERNUMBER_FORMAT, $generatedOrderNumber, '');

        $page = $pageLoadedEvent->getPage();
        $page->addExtension(self::PAGE_EXTENSION_KEY, new ArrayStruct([
            self::MAX_LENGTH_CUSTOM_ORDER_NUMBER => OrderIdocConverter::I_DOC_LENGTH_ORDER_NUMBER - strlen($combinedData),
            self::MAX_LENGTH_COMMISSION_PRODUCT  => OrderIdocConverter::I_DOC_LENGTH_COMMISSION_PRODUCT,
            self::MAX_LENGTH_COMMISSION_ORDER    => OrderIdocConverter::I_DOC_LENGTH_COMMISSION_ORDER,
            self::MAX_LENGTH_CUSTOMER_COMMENT    => OrderIdocConverter::I_DOC_LENGTH_CUSTOMER_COMMENT,
        ]));
    }
}
