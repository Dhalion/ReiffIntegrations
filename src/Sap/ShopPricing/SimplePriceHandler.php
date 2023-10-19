<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\ShopPricing;

use ReiffIntegrations\Sap\Struct\Price\ItemCollection;
use ReiffIntegrations\Sap\Struct\Price\ItemStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Symfony\Contracts\Translation\TranslatorInterface;

class SimplePriceHandler
{
    public const PRICE_PLACEHOLDER           = '#REIFFCUSTOMERPRICE#';
    public const PRICE_FORMATTED_PLACEHOLDER = '#REIFFCUSTOMERPRICEFORMATTED#';
    public const ERROR_PLACEHOLDER           = '#REIFFCUSTOMERPRICEERROR#';

    private const MESSAGE_ERROR_SAP = 'ERROR_SAP';
    private const CURRENCY_ISO      = 'EUR';

    public function __construct(
        private readonly CurrencyFormatter $currencyFormatter,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function handleSinglePrices(
        string $content,
        string $languageId,
        array $productNumbers,
        ItemCollection $itemCollection,
        ?string $debtorNumber = null
    ): string {
        $context = Context::createDefaultContext();

        foreach ($productNumbers as $productNumber) {
            $matchingItems = $itemCollection->getItemsByNumber($productNumber);

            if ($matchingItems->count() > 1 && ($lowestPricedMatchingItem = $matchingItems->getLowestPrice()) !== null) {
                $content = $this->handleSinglePriceData($languageId, $context, $productNumber, $content, $lowestPricedMatchingItem, $debtorNumber);
            } elseif ($matchingItems->count() === 1) {
                $content = $this->handleSinglePriceData($languageId, $context, $productNumber, $content, $matchingItems->first(), $debtorNumber);
            } else {
                $content = $this->handleMissingSinglePriceData($productNumber, $content);
            }
        }

        return $content;
    }

    private function handleSinglePriceData(
        string $languageId,
        Context $context,
        string $productNumber,
        string $content,
        ?ItemStruct $item,
        ?string $debtorNumber
    ): string {
        if ($item === null) {
            return str_replace(
                self::ERROR_PLACEHOLDER . $productNumber . '#',
                $this->translator->trans(sprintf('ReiffIntegrations.price.error.%s', self::MESSAGE_ERROR_SAP)),
                $content
            );
        }

        $price = 0.0;

        if ($debtorNumber !== null) {
            $price = $item->getTotalPrice();
        }

        $formattedPrice = $this->currencyFormatter->formatCurrencyByLanguage(
            $price,
            self::CURRENCY_ISO,
            $languageId,
            $context
        );

        $content = preg_replace_callback_array([
            sprintf(
                '/%s%s\#(?<fallbackPrice>[^\#]+)\#/',
                preg_quote(self::PRICE_FORMATTED_PLACEHOLDER, '/'),
                preg_quote($productNumber, '/')
            ) => static function (array $matches) use ($price, $formattedPrice) {
                if ($price > 0.001) {
                    return $formattedPrice;
                }

                return $matches['fallbackPrice'];
            },
            sprintf(
                '/%s%s\#(?<fallbackPrice>[^\#]+)\#/',
                preg_quote(self::PRICE_PLACEHOLDER, '/'),
                preg_quote($productNumber, '/')
            ) => static function (array $matches) use ($price) {
                if ($price > 0.001) {
                    return $price;
                }

                return $matches['fallbackPrice'];
            },
        ], $content);

        return str_replace(self::ERROR_PLACEHOLDER . $productNumber . '#', '', (string) $content);
    }

    private function handleMissingSinglePriceData(
        string $productNumber,
        string $content,
    ): string {
        $content = str_replace(
            self::ERROR_PLACEHOLDER . $productNumber . '#',
            $this->translator->trans(sprintf('ReiffIntegrations.price.error.%s', self::MESSAGE_ERROR_SAP)),
            $content
        );

        $content = preg_replace_callback_array([
            sprintf(
                '/%s%s\#(?<fallbackPrice>[^\#]+)\#/',
                preg_quote(self::PRICE_FORMATTED_PLACEHOLDER, '/'),
                preg_quote($productNumber, '/')
            ) => static function (array $matches) {
                return $matches['fallbackPrice'];
            },
            sprintf(
                '/%s%s\#(?<fallbackPrice>[^\#]+)\#/',
                preg_quote(self::PRICE_PLACEHOLDER, '/'),
                preg_quote($productNumber, '/')
            ) => static function (array $matches) {
                return $matches['fallbackPrice'];
            },
        ], $content);

        return str_replace(self::ERROR_PLACEHOLDER . $productNumber . '#', '', (string) $content);
    }
}
