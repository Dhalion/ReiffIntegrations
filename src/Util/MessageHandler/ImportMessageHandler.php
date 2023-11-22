<?php

declare(strict_types=1);

namespace ReiffIntegrations\Util\MessageHandler;

use ReiffIntegrations\Util\Handler\AbstractImportHandler;
use ReiffIntegrations\Util\ImportArchiver;
use ReiffIntegrations\Util\Message\AbstractImportMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'import')]
class ImportMessageHandler
{
    /**
     * @param AbstractImportHandler[] $importHandlers
     */
    public function __construct(
        private readonly iterable $importHandlers,
        private readonly ImportArchiver $archiver,
    ) {
    }

    public function __invoke(AbstractImportMessage $message): void
    {
        $context = $message->getContext();

        foreach ($this->importHandlers as $importHandler) {
            if ($importHandler->supports($message)) {
                try {
                    $importHandler->handle($message);
                } catch (\Throwable $e) {
                    $importHandler->addError($e, $context);
                }

                if ($importHandler->hasErrors()) {
                    $this->archiver->error($message->getArchivedFileName(), $context);
                }

                $importHandler->notifyErrors(sprintf('%s: %s', get_class($message), $message->getArchivedFileName()), $context);
            }
        }
    }

    public function handle(AbstractImportMessage $message): void
    {
        $this->__invoke($message);
    }
}
