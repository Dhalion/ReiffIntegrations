<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Helper;

use ReiffIntegrations\Util\Exception\WrappedException;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\AssociationNotFoundException;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class OrderHelper
{
    private StateMachineRegistry $stateMachineRegistry;

    public function __construct(StateMachineRegistry $stateMachineRegistry)
    {
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    public function transitionOrderToState(
        string $targetStateName,
        OrderEntity $order,
        string $actionName,
        Context $context
    ): void {
        if ($order->getStateMachineState() === null) {
            throw new AssociationNotFoundException('stateMachineState');
        }

        if ($order->getStateMachineState()->getTechnicalName() === $targetStateName) {
            return;
        }

        try {
            $this->stateMachineRegistry->transition(
                new Transition(
                    OrderDefinition::ENTITY_NAME,
                    $order->getId(),
                    $actionName,
                    'stateId'
                ),
                $context
            );
        } catch (IllegalTransitionException $e) {
            throw new WrappedException(sprintf('State transition error for order %s:', $order->getOrderNumber()), $e);
        }
    }
}
