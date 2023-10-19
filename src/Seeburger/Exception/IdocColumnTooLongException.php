<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Exception;

use ReiffIntegrations\Seeburger\Struct\IdocColumn;
use ReiffIntegrations\Seeburger\Struct\IdocRow;

class IdocColumnTooLongException extends \Exception
{
    public function __construct(IdocRow $idocRow, IdocColumn $idocColumn, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Value %s for column %s in row %s is too long.', $idocColumn->getValue(), $idocRow->getIdentifier(), $idocColumn->getName()),
            $code,
            $previous
        );
    }
}
