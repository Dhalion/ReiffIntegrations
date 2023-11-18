<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Struct;

use Shopware\Core\Framework\Struct\Struct;

class CatalogMetadata extends Struct
{
    protected string $catalogId;
    protected ?string $sortimentId = null;
    protected string $languageCode;
    protected string $systemLanguageCode;

    public function __construct(
        string  $catalogId,
        ?string $sortimentId,
        string $languageCode,
        string $systemLanguageCode
    )
    {
        $this->catalogId = $catalogId;
        $this->sortimentId = $sortimentId;
        $this->languageCode = $languageCode;
        $this->systemLanguageCode = $systemLanguageCode;
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
}
