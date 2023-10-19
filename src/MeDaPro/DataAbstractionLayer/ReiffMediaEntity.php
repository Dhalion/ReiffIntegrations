<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\DataAbstractionLayer;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class ReiffMediaEntity extends Entity
{
    protected ?MediaEntity $media   = null;
    protected ?string $mediaId      = null;
    protected ?string $hash         = null;
    protected ?string $originalPath = null;

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(?MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function setMediaId(?string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(?string $hash): void
    {
        $this->hash = $hash;
    }

    public function getOriginalPath(): ?string
    {
        return $this->originalPath;
    }

    public function setOriginalPath(?string $originalPath): void
    {
        $this->originalPath = $originalPath;
    }
}
