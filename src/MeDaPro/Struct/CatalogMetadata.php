<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Struct;

use Shopware\Core\Framework\Struct\Struct;

class CatalogMetadata extends Struct
{
    public function __construct(
        protected readonly string $catalogId,
        protected readonly ?string $sortimentId,
        protected readonly string $languageCode,
        protected readonly string $systemLanguageCode,
        protected string $archivedFilename = ''
    ) {
    }

    public function getCatalogId(): string
    {
        return $this->catalogId;
    }

    public function getSortimentId(): ?string
    {
        return $this->sortimentId;
    }

    public function getLanguageCode(): string
    {
        return $this->languageCode;
    }

    public function getSystemLanguageCode(): string
    {
        return $this->systemLanguageCode;
    }

    public function isSystemLanguage(): bool
    {
        return $this->languageCode === $this->systemLanguageCode;
    }

    public function isValid(): bool
    {
        return $this->catalogId !== '' && $this->languageCode !== '' && $this->systemLanguageCode !== '';
    }

    public function getArchivedFilename(): string
    {
        return $this->archivedFilename;
    }

    public function setArchivedFilename(string $archivedFilename): void
    {
        $this->archivedFilename = $archivedFilename;
    }
}
