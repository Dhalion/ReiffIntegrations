<?php

declare(strict_types=1);

namespace ReiffIntegrations\Util\Handler;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use ReiffIntegrations\Util\EntitySyncer;
use ReiffIntegrations\Util\Mailer;
use ReiffIntegrations\Util\Message\AbstractImportMessage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SystemConfig\SystemConfigService;

abstract class AbstractImportHandler extends AbstractHandler
{
    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $configService,
        Mailer $mailer,
        protected readonly EntitySyncer $entitySyncer,
        protected readonly Connection $connection,
    ) {
        parent::__construct($logger, $configService, $mailer);
    }

    abstract public function supports(AbstractImportMessage $message): bool;

    abstract public function handle(AbstractImportMessage $message): void;

    abstract public function getMessage(Struct $struct, string $archiveFileName, Context $context): AbstractImportMessage;
}
