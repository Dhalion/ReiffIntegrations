<?php

declare(strict_types=1);

namespace ReiffIntegrations\Util\Exception;

abstract class AbstractException extends \Exception
{
    public function __construct(string $message, \Throwable $previous = null)
    {
        if ($previous) {
            $message = sprintf("%s\n\n%s", $message, $previous->getMessage());
        }

        parent::__construct($message, 0, $previous);
    }

    public function getIdentifier(): string
    {
        if ($this->getPrevious() !== null) {
            return get_class($this->getPrevious());
        }

        throw new \RuntimeException(sprintf('Implement getIdentifier() for %s', get_class($this)));
    }
}
