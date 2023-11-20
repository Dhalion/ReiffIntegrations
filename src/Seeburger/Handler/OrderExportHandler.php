<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Handler;

use Doctrine\DBAL\Connection;
use K10rIntegrationHelper\Observability\RunService;
use Psr\Log\LoggerInterface;
use ReiffIntegrations\Seeburger\Client\SeeburgerClient;
use ReiffIntegrations\Seeburger\DataAbstractionLayer\OrderExtension;
use ReiffIntegrations\Seeburger\DataConverter\OrderIdocConverter;
use ReiffIntegrations\Seeburger\Helper\OrderHelper;
use ReiffIntegrations\Seeburger\Message\OrderExportMessage;
use ReiffIntegrations\Seeburger\Provider\OrderProvider;
use ReiffIntegrations\Seeburger\Struct\OrderData;
use ReiffIntegrations\Util\Configuration;
use ReiffIntegrations\Util\Context\DebugState;
use ReiffIntegrations\Util\Context\DryRunState;
use ReiffIntegrations\Util\Exception\WrappedException;
use ReiffIntegrations\Util\ExportArchiver;
use ReiffIntegrations\Util\Handler\AbstractExportHandler;
use ReiffIntegrations\Util\Mailer;
use ReiffIntegrations\Util\Message\AbstractExportMessage;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OrderExportHandler extends AbstractExportHandler
{
    private string $orderId;

    public function __construct(
        ExportArchiver $archiver,
        LoggerInterface $logger,
        SystemConfigService $configService,
        Mailer $errorMailer,
        private readonly EntityRepository $orderRepository,
        private readonly OrderHelper $orderHelper,
        private readonly Connection $connection,
        private readonly OrderIdocConverter $orderIdocConverter,
        private readonly SeeburgerClient $client,
        private readonly RunService $runService,
    ) {
        parent::__construct($logger, $configService, $errorMailer, $archiver);
    }

    public function supports(AbstractExportMessage $message): bool
    {
        return $message instanceof OrderExportMessage;
    }

    /**
     * @param OrderData $struct
     */
    public function getMessage(Struct $struct, Context $context): OrderExportMessage
    {
        return new OrderExportMessage($struct, $context);
    }

    /**
     * @param OrderExportMessage $message
     *
     * @return string The IDOC output
     */
    public function handle(AbstractExportMessage $message, Context $context): string
    {
        if (!$message instanceof OrderExportMessage) {
            throw new \InvalidArgumentException();
        }

        $order = $this->getOrder($message->getOrderData()->getOrderId(), $context);

        $notificationData = [
            'shopwareOrderId'        => $message->getOrderData()->getOrderId(),
        ];

        $isSuccess = true;
        $exception = null;

        if (!$order) {
            throw new \RuntimeException(sprintf('Order with ID %s not found', $message->getOrderData()->getOrderId()));
        }

        $this->orderId = $order->getId();
        $result        = '';

        try {
            $this->exportOrder($context, $order, $result);
        } catch (\Throwable $throwable) {
            $isSuccess = false;
            $exception = $throwable;

            $notificationData['error'] = $throwable->getMessage();
        }

        $notificationData['idoc'] = $result;

        $this->runService->markAsHandled(
            $message->getOrderData()->getElementId(),
            $isSuccess,
            $notificationData,
            null,
            $context
        );

        if (null !== $exception) {
            throw $exception;
        }

        return $result;
    }

    public function notifyErrors(string $itemIdentifier, Context $context): void
    {
        if ($this->hasErrors() && !$context->hasState(DebugState::NAME) && !$context->hasState(DryRunState::NAME)) {
            $this->orderRepository->upsert([
                [
                    'id'                           => $this->orderId,
                    OrderExtension::EXTENSION_NAME => [
                        'notifiedAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ],
                ],
            ], $context);
        }

        parent::notifyErrors($itemIdentifier, $context);
    }

    public function exportOrder(Context $context, OrderEntity $order, string &$result): string
    {
        $this->connection->transactional(function () use ($context, $order, &$result): void {
            if (!$context->hasState(DryRunState::NAME)) {
                $this->orderRepository->upsert([
                    [
                        'id' => $order->getId(),
                        OrderExtension::EXTENSION_NAME => [
                            'queuedAt' => null,
                        ],
                    ],
                ], $context);
            }

            $idoc = $this->orderIdocConverter->convert($order);
            $result = $this->toString($idoc);

            if (!$context->hasState(DebugState::NAME) && !$context->hasState(DryRunState::NAME)) {
                $this->client->post($result, $this->configService->getString(Configuration::CONFIG_KEY_ORDER_EXPORT_URL));
            }

            if (!$context->hasState(DryRunState::NAME)) {
                $this->orderRepository->upsert([
                    [
                        'id' => $order->getId(),
                        OrderExtension::EXTENSION_NAME => [
                            'exportedAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        ],
                    ],
                ], $context);

                try {
                    $this->orderHelper->transitionOrderToState(OrderStates::STATE_IN_PROGRESS, $order, StateMachineTransitionActions::ACTION_PROCESS, $context);
                } catch (IllegalTransitionException $e) {
                    // This allows for easier re-export without resetting the order state
                    throw new WrappedException(sprintf('State transition error for order %s:', $order->getOrderNumber()), $e);
                }

                $this->archive($result, 'order.idoc');
            }
        });

        return $result;
    }

    protected function getLogIdentifier(): string
    {
        return self::class;
    }

    private function getOrder(string $orderId, Context $context): ?OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        OrderProvider::setupCriteria($criteria);

        return $this->orderRepository->search($criteria, $context)->first();
    }
}
