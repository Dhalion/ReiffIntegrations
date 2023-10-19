<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Struct;

use Shopware\Core\Framework\Struct\Struct;

class CategoryStruct extends Struct
{
    protected string $id;
    protected ?string $parentId;
    protected string $uId;
    protected string $type;
    protected string $name;
    protected string $description;
    protected int $depth;
    protected array $mediaPaths;

    public function __construct(
        string $id,
        ?string $parentId,
        string $uId,
        string $type,
        string $name,
        string $description,
        int $depth,
        array $mediaPaths
    ) {
        $this->id          = $id;
        $this->parentId    = $parentId;
        $this->uId         = $uId;
        $this->type        = $type;
        $this->name        = $name;
        $this->description = $description;
        $this->depth       = $depth;
        $this->mediaPaths  = $mediaPaths;
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
