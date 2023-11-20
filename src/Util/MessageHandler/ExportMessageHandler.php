<?php

declare(strict_types=1);

namespace ReiffIntegrations\Util\MessageHandler;

use ReiffIntegrations\Util\Handler\AbstractExportHandler;
use ReiffIntegrations\Util\Message\AbstractExportMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'export')]
class ExportMessageHandler
{
    /** @var AbstractExportHandler[] */
    private iterable $exportHandlers;

    public function __construct(iterable $exportHandlers)
    {
        $this->exportHandlers = $exportHandlers;
    }

    public function handle(AbstractExportMessage $message): void
    {
        $this->__invoke($message);
    }

    public function __invoke(AbstractExportMessage $message): void
    {
        $this->handleWithResult($message);
    }

    public function handleWithResult(AbstractExportMessage $message): string
    {
        $context = $message->getContext();
        $result  = '';

        foreach ($this->exportHandlers as $exportHandler) {
            if ($exportHandler->supports($message)) {
                try {
                    $result = $exportHandler->handle($message, $context);
                } catch (\Throwable $e) {
                    $exportHandler->addError($e, $context);
                }
                $exportHandler->notifyErrors(get_class($message), $context);
            }
        }

        return $result;
    }
}
