<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\System\Currency;

use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\System\Currency\CurrencyFormatter as BaseCurrencyFormatter;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

// Yes, extending is ugly, but there's no interface/abstract, so what can you do ¯\_(ツ)_/¯
class CurrencyFormatter extends BaseCurrencyFormatter
{
    /** @var int This prevents showing too many digits if not necessary */
    private const CURRENCY_MIN_FRACTION_DIGITS = 2;
    /** @var \NumberFormatter[] */
    private array $formatter = [];

    private LanguageLocaleCodeProvider $languageLocaleProvider;

    /**
     * @internal
     */
    public function __construct(LanguageLocaleCodeProvider $languageLocaleProvider)
    {
        $this->languageLocaleProvider = $languageLocaleProvider;
    }

    /**
     * @see \Shopware\Core\System\Currency\CurrencyFormatter This is a complete replacement to set MIN_FRACTION_DIGITS
     *
     * @throws InconsistentCriteriaIdsException
     * @throws LanguageNotFoundException
     */
    public function formatCurrencyByLanguage(float $price, string $currency, string $languageId, Context $context, ?int $decimals = null): string
    {
        $decimals ??= $context->getRounding()->getDecimals();

        $locale    = $this->languageLocaleProvider->getLocaleForLanguageId($languageId);
        $formatter = $this->getFormatter($locale, \NumberFormatter::CURRENCY);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, self::CURRENCY_MIN_FRACTION_DIGITS);


        return (string) $formatter->formatCurrency($price, $currency);
    }

    private function getFormatter(string $locale, int $format): \NumberFormatter
    {
        $hash = md5((string) json_encode([$locale, $format]));

        if (isset($this->formatter[$hash])) {
            return $this->formatter[$hash];
        }

        return $this->formatter[$hash] = new \NumberFormatter($locale, $format);
    }
}
