<?php

declare(strict_types=1);

namespace ReiffIntegrations\Util\Handler;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Util\Context\DebugState;
use ReiffIntegrations\Util\Context\DryRunState;
use ReiffIntegrations\Util\Exception\AbstractException;
use ReiffIntegrations\Util\Exception\WrappedException;
use ReiffIntegrations\Util\Mailer;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

abstract class AbstractHandler
{
    /** @var \Throwable[] */
    protected array $errors = [];

    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly SystemConfigService $configService,
        protected readonly Mailer $mailer
    ) {
    }

    final public function addError(\Throwable $error, Context $context): void
    {
        if ($context->hasState(DebugState::NAME)) {
            throw $error;
        }

        if (!($error instanceof AbstractException)) {
            $error = new WrappedException('Wrapped error:', $error);
        }

        $this->errors[] = $error;
    }

    public function notifyErrors(string $itemIdentifier, Context $context): void
    {
        if (!$this->hasErrors()) {
            return;
        }

        foreach ($this->errors as $error) {
            $message = sprintf('[%s][%s] %s', $this->getLogIdentifier(), $itemIdentifier, $error);

            if ($context->hasState(DebugState::NAME)) {
                $message = sprintf('[DEBUG]%s', $message);
            }

            $this->logger->error($message);
        }

        if (!$context->hasState(DebugState::NAME) && !$context->hasState(DryRunState::NAME)) {
            $this->mailer->sendErrorMail($this->errors, $itemIdentifier, $context);
        }

        $this->errors = [];
    }

    final public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    abstract protected function getLogIdentifier(): string;
}
