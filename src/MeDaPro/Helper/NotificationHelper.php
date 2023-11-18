<?php

namespace ReiffIntegrations\MeDaPro\Helper;

use K10rIntegrationHelper\NotificationSystem\DataAbstractionLayer\Notifications\NotificationCollection;
use K10rIntegrationHelper\NotificationSystem\DataAbstractionLayer\Notifications\NotificationEntity;
use K10rIntegrationHelper\NotificationSystem\NotificationService;
use Monolog\Logger;
use ReiffIntegrations\MeDaPro\Struct\CatalogMetadata;
use setasign\Fpdi\PdfParser\Type\PdfName;
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
    ): void
    {
        $notificationData = array_merge($notificationData, [
            'catalogId'   => $catalogMetadata->getCatalogId(),
            'sortimentId' => $catalogMetadata->getSortimentId(),
            'language' => $catalogMetadata->getLanguageCode(),
        ]);

        $notification = new NotificationEntity();
        $notification->assign([
            'id'                 => Uuid::randomHex(),
            'notificationType'   => $notificationType,
            'notificationLevel'  => (string) Logger::ERROR,
            'notificationReason' => Logger::getLevelName(Logger::ERROR),
            'notificationData'   => $notificationData,
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

        $notications = new NotificationCollection($this->notifications);

        $this->notificationService->notifyBatch($notications, $context);

        $this->notifications = [];
    }

}
