<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Helper;

use K10rIntegrationHelper\NotificationSystem\DataAbstractionLayer\Notifications\NotificationCollection;
use K10rIntegrationHelper\NotificationSystem\DataAbstractionLayer\Notifications\NotificationEntity;
use K10rIntegrationHelper\NotificationSystem\NotificationService;
use Monolog\Level;
use ReiffIntegrations\MeDaPro\Struct\CatalogMetadata;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class NotificationHelper
{
    /** @var NotificationEntity[] $notification */
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

        if (mb_strlen($message) > 255) {
            $notificationData['completeMessage'] = $message;

            $message = mb_substr($message, 0, 255);
        }

        $notification = new NotificationEntity();
        $notification->assign([
            'id'                  => Uuid::randomHex(),
            'notificationType'    => $notificationType,
            'notificationReason'  => Level::Error->getName(),
            'notificationLevel'   => (string) Level::Error->value,
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

    public function handleAsync(Context $context): void
    {
        if (empty($this->notifications)) {
            return;
        }

        foreach ($this->notifications as $notification) {
            $notification->setHandleAsync(true);

            $this->notificationService->notify($notification, $context);
        }

        $this->notifications = [];
    }
}
