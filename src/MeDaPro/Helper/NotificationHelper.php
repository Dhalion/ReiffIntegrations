<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Helper;

use K10rIntegrationHelper\NotificationSystem\DataAbstractionLayer\Notifications\NotificationCollection;
use K10rIntegrationHelper\NotificationSystem\DataAbstractionLayer\Notifications\NotificationEntity;
use K10rIntegrationHelper\NotificationSystem\NotificationService;
use Monolog\Level;
use Monolog\Logger;
use ReiffIntegrations\MeDaPro\Struct\CatalogMetadata;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class NotificationHelper
{
    private array $notifications = [];

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    public function addNotification(
        string $message,
        string $notificationType,
        array $notificationData,
        CatalogMetadata $catalogMetadata
    ): void {
        $notificationData = array_merge($notificationData, [
            'catalogId'   => $catalogMetadata->getCatalogId(),
            'sortimentId' => $catalogMetadata->getSortimentId(),
            'language'    => $catalogMetadata->getLanguageCode(),
        ]);

        $notification = new NotificationEntity();
        $notification->assign([
            'id'                  => Uuid::randomHex(),
            'notificationType'    => $notificationType,
            'notificationLevel'   => (string) Logger::ERROR,
            'notificationReason'  => Level::Error,
            'notificationData'    => $notificationData,
            'notificationMessage' => $message,
            'notificationTime'    => new \DateTimeImmutable(),
        ]);

        $this->notifications[] = $notification;
    }

    public function sendNotifications(Context $context): void
    {
        if (empty($this->notifications)) {
            return;
        }

        $notifications = new NotificationCollection($this->notifications);

        $this->notificationService->notifyBatch($notifications, $context);

        $this->notifications = [];
    }
}
