<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Struct;

use ReiffIntegrations\Seeburger\Exception\IdocColumnTooLongException;
use Shopware\Core\Framework\Struct\Struct;

class IdocRow extends Struct
{
    private string $identifier;
    private IdocColumnCollection $columns;

    public function __construct(string $identifier, IdocColumnCollection $columns)
    {
        $this->identifier = $identifier;
        $this->columns    = $columns;
    }

    public function validate(): void
    {
        foreach ($this->columns as $column) {
            if (mb_strlen($column->getValue()) > $column->getLength()) {
                throw new IdocColumnTooLongException($this, $column);
            }
        }
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getColumns(): IdocColumnCollection
    {
        return $this->columns;
    }
}
