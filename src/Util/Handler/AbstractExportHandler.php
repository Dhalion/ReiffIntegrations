<?php

declare(strict_types=1);

namespace ReiffIntegrations\Util\Handler;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Seeburger\Struct\IdocRowCollection;
use ReiffIntegrations\Util\ExportArchiver;
use ReiffIntegrations\Util\Mailer;
use ReiffIntegrations\Util\Message\AbstractExportMessage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SystemConfig\SystemConfigService;

abstract class AbstractExportHandler extends AbstractHandler
{
    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $configService,
        Mailer $mailer,
        protected readonly ExportArchiver $archiver,
    ) {
        parent::__construct($logger, $configService, $mailer);
    }

    abstract public function supports(AbstractExportMessage $message): bool;

    abstract public function handle(AbstractExportMessage $message, Context $context): string;

    abstract public function getMessage(Struct $struct, Context $context): AbstractExportMessage;

    final protected function toString(IdocRowCollection $idoc): string
    {
        $idocString = '';

        foreach ($idoc as $index => $idocRow) {
            if ($index !== 0) {
                $idocString .= "\r\n";
            }

            foreach ($idocRow->getColumns() as $column) {
                $idocString .= $this->fillColumn($column->getValue(), $column->getLength());
            }
        }

        return $idocString;
    }

    final protected function archive(string $xml, string $filename): void
    {
        $this->archiver->archive($xml, $filename);
    }

    private function fillColumn(string $input, int $padLength): string
    {
        return str_pad($input, strlen($input) - mb_strlen($input) + $padLength);
    }
}
