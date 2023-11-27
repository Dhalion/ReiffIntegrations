<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Struct;

use Shopware\Core\Framework\Struct\Struct;

class CategoryStruct extends Struct
{
    public function __construct(
        protected readonly string $id,
        protected readonly ?string $parentId,
        protected readonly string $uId,
        protected readonly string $type,
        protected readonly string $name,
        protected readonly string $description,
        protected readonly int $depth,
        protected readonly array $mediaPaths
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function getUId(): string
    {
        return $this->uId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getMediaPaths(): array
    {
        return $this->mediaPaths;
    }
}
