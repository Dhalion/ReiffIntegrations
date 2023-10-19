<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\ShopPricing;

use ReiffIntegrations\Sap\Struct\Price\ItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Storefront\Page\Product\ProductPage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class DetailedPriceHandler
{
    public const PRICE_SWITCH_PLACEHOLDER_FORMATTED = '#REIFFCUSTOMERPRICESWITCHPLACEHOLDERFORMATTED#';
    public const PRICE_SWITCH_PLACEHOLDER_FALLBACK  = '#REIFFCUSTOMERPRICESWITCHPLACEHOLDERFALLBACK#';

    public function __construct(
        private readonly Environment $twigEnv,
        private readonly TranslatorInterface $translator,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory
    ) {
    }

    public function handleSwitchPrices(
        string $content,
        array $productNumbers,
        string $salesChannelId,
        string $currencyId,
        ItemCollection $itemCollection,
        ?string $debtorNumber = null
    ): string {
        $prices = [];
        $units  = [];

        if ($itemCollection->count() > 0) {
            foreach ($productNumbers as $productNumber) {
                $matchingItems = $itemCollection->getItemsByNumber($productNumber);

                foreach ($matchingItems->getElements() as $item) {
                    if (!array_key_exists($productNumber, $prices)) {
                        $prices[$productNumber] = [];
                    }

                    $prices[$productNumber][$item->getQuantity()] = $item->getTotalPrice();
                    $units[$productNumber]                        = $item->getOrderUnit();
                }
            }
        }

        return $this->handleSwitchPriceData($prices, $units, $salesChannelId, $currencyId, $content, $debtorNumber);
    }

    private function handleSwitchPriceData(
        array $pricesByNumber,
        array $unitsByNumber,
        string $salesChannelId,
        string $currencyId,
        string $content,
        ?string $debtorNumber = null
    ): string {
        $isFallback = false;

        if (empty($pricesByNumber)) {
            $pricesByNumber = $this->getFallbackData($content);
            $isFallback     = true;
        }
        preg_match_all('/' . self::PRICE_SWITCH_PLACEHOLDER_FORMATTED . '(?<productNumber>[^#]+)#(?<unit>[^#]*)#/', $content, $foundData);

        $products = $this->getProductsWithData($pricesByNumber, $unitsByNumber);

        $foundNumbers = $foundData['productNumber'];
        $units        = $foundData['unit'];

        foreach ($foundNumbers as $index => $foundNumber) {
            $clearedNumber = (string) str_replace('#', '', $foundNumber);
            $unit          = $units[$index];

            if (!array_key_exists($clearedNumber, $products)) {
                $renderedData = $this->twigEnv->render('@Storefront/storefront/utilities/alert.html.twig', [
                    'type'    => 'primary',
                    'content' => $this->translator->trans('ReiffIntegrations.price.error.ERROR_SAP'),
                ]);

                /** @var string $content */
                $content = preg_replace('/' . self::PRICE_SWITCH_PLACEHOLDER_FORMATTED . $clearedNumber . '#(?<unit>[^#]*)#/', $renderedData, $content);

                continue;
            }

            $productPage = new ProductPage();
            $products[$clearedNumber]->setPackUnit($unit);

            $productPage->setProduct($products[$clearedNumber]);

            $salesChannelContext = $this->salesChannelContextFactory->create(Uuid::randomHex(), $salesChannelId, [
                    SalesChannelContextService::CURRENCY_ID => $currencyId,
            ]);

            $renderedData = $this->twigEnv->render('@ReiffIntegrations/storefront/component/k10r-price/detailed-price-render-template.html.twig', [
                'page'            => $productPage,
                'context'         => $salesChannelContext,
                'debtorNumber'    => $debtorNumber,
                'isPageRendering' => true,
                'isFallback'      => $isFallback,
            ]);

            /** @var string $content */
            $content = preg_replace('/' . self::PRICE_SWITCH_PLACEHOLDER_FORMATTED . $clearedNumber . '#(?<unit>[^#]*)#/', $renderedData, $content);
        }

        if (strpos($content, self::PRICE_SWITCH_PLACEHOLDER_FALLBACK)) {
            /** @var string $content */
            $content = preg_replace('/' . self::PRICE_SWITCH_PLACEHOLDER_FALLBACK . '(.*#)?(.*#)/', '', $content);
        }

        return $content;
    }

    private function getFallbackData(string $content): array
    {
        /** @var string $content */
        $content = preg_replace('/' . self::PRICE_SWITCH_PLACEHOLDER_FORMATTED . '(.*#)?(.*#)(.*#)/', '', $content);
        $matches = [];
        preg_match_all('/' . self::PRICE_SWITCH_PLACEHOLDER_FALLBACK . '(.*#)(.*#)(.*#)/', $content, $matches);

        $data = [];

        if (!empty($matches)) {
            $wholeMatches        = current($matches) ?? [];
            $matchProductNumbers = $matches[1] ?? [];
            $matchQuantities     = $matches[2] ?? [];
            $matchPrices         = $matches[3] ?? [];

            foreach ($wholeMatches as $matchKey => $match) {
                if (!array_key_exists($matchKey, $matchProductNumbers) || !array_key_exists($matchKey, $matchPrices)) {
                    continue;
                }

                $curProductNumber = (string) str_replace('#', '', $matchProductNumbers[$matchKey]);
                $curPrice         = (float) str_replace('#', '', $matchPrices[$matchKey]);
                $curQuantity      = (int) str_replace('#', '', $matchQuantities[$matchKey] ?? '1#');

                if (!array_key_exists($curProductNumber, $data)) {
                    $data[$curProductNumber] = [];
                }

                $data[$curProductNumber][$curQuantity] = $curPrice;
            }
        }

        return $data;
    }

    /**
     * @return array<string, SalesChannelProductEntity>
     */
    private function getProductsWithData(array $pricesByNumber, array $unitsByNumber): array
    {
        $products = [];

        foreach ($pricesByNumber as $productNumber => $prices) {
            $productNumber   = (string) $productNumber;
            $priceCollection = new PriceCollection();

            foreach ($prices as $quantity => $price) {
                $priceCollection->add(
                    new CalculatedPrice(
                        $price,
                        $price,
                        new CalculatedTaxCollection(),
                        new TaxRuleCollection(),
                        $quantity
                    )
                );
            }

            $currentProduct = new SalesChannelProductEntity();
            $currentProduct->setProductNumber($productNumber);
            $currentProduct->setCalculatedPrices($priceCollection);

            if ($priceCollection->first() !== null) {
                $currentProduct->setCalculatedPrice($priceCollection->first());
            }

            if (array_key_exists($productNumber, $unitsByNumber) && !empty($unitsByNumber[$productNumber])) {
                $currentProduct->setPackUnit($unitsByNumber[$productNumber]);
            }

            $products[$productNumber] = $currentProduct;
        }

        return $products;
    }
}
