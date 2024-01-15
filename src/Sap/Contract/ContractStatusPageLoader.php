<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use ReiffIntegrations\Sap\Contract\ApiClient\ContractStatusClient;
use ReiffIntegrations\Util\Traits\UnitDataTrait;
use Shopware\B2B\Debtor\Framework\DebtorRepositoryInterface;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Defaults;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\Page;
use Symfony\Component\HttpFoundation\Request;

class ContractStatusPageLoader
{
    use UnitDataTrait;

    public function __construct(
        private readonly GenericPageLoaderInterface $genericPageLoader,
        private readonly ContractStatusClient $contractStatusClient,
        private readonly Connection $connection
    ) {
    }

    public function load(string $contractNumber, Request $request, SalesChannelContext $salesChannelContext): ContractStatusPage
    {
        $page = $this->getBasicPage($request, $salesChannelContext);

        /** @var ContractStatusPage $page */
        $page = ContractStatusPage::createFrom($page);

        $contractStatusResult = $this->contractStatusClient->getContractStatus($contractNumber, $salesChannelContext->getContext());
        $page->setSuccess($contractStatusResult->isSuccess());
        $page->setContractStatus($contractStatusResult->getContractStatus());

        if ($salesChannelContext->getCustomerId() === null) {
            return $page;
        }

        $this->addShopwareDataToResponse($page, $salesChannelContext->getCustomerId(), $salesChannelContext->getLanguageId());

        return $page;
    }

    private function getBasicPage(Request $request, SalesChannelContext $salesChannelContext): Page
    {
        if (!$salesChannelContext->getCustomer()) {
            throw new CustomerNotLoggedInException(404, '404', 'Customer not logged in');
        }

        $page = $this->genericPageLoader->load($request, $salesChannelContext);
        $page->getMetaInformation()?->setRobots('noindex,follow');

        return $page;
    }

    private function addShopwareDataToResponse(ContractStatusPage $page, string $customerId, string $languageId): void
    {
        $contractStatus = $page->getContractStatus();

        if ($contractStatus === null) {
            return;
        }

        $itemDataCollection = $contractStatus->getItemDataCollection();

        if ($itemDataCollection === null) {
            return;
        }

        $itemNumbers = [];
        foreach ($itemDataCollection->getElements() as $element) {
            $itemNumbers[] = $element->getMaterialNumber();
        }

        if (empty($itemNumbers)) {
            return;
        }

        $productSearchData = $this->getProductDataByNumbers($itemNumbers, $customerId);
        $this->updateProductUnits($languageId);
        foreach ($itemDataCollection->getIterator() as $element) {
            if ($element->getMaterialNumber() !== null && array_key_exists($element->getMaterialNumber(), $productSearchData) && !empty($productSearchData[$element->getMaterialNumber()])) {
                $element->setProductId($productSearchData[$element->getMaterialNumber()]['lowerPId'] ?? null);
                $element->setCustomProductNumber($productSearchData[$element->getMaterialNumber()]['customProductNumber'] ?? null);
            }

            $backupSalesUnit = $element->getSalesUnit();
            $element->setSalesUnit($this->getSalesUnit($element->getSalesUnitIso(), $element->getSalesUnit()));

            if ($element->getItemUsage() !== null) {
                foreach ($element->getItemUsage()->getIterator() as $usage) {
                    $usage->setOrderItemUom($this->getSalesUnit($usage->getOrderItemUom(), $usage->getOrderItemUom()));

                    if ($backupSalesUnit === $usage->getOrderItemUom() || $element->getSalesUnitIso() === $usage->getOrderItemUom()) {
                        $usage->setOrderItemUom($element->getSalesUnit());
                    }
                }
            }
        }
    }

    private function getProductDataByNumbers(array $productNumbers, string $customerId): array
    {
        $result = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(p.id)) as lowerPId, p.product_number as productNumber, b2bon.custom_ordernumber as customProductNumber
            FROM product p
            LEFT JOIN b2b_order_number b2bon
                ON (b2bon.product_id = p.id AND b2bon.context_owner_id = (SELECT context_owner_id FROM b2b_store_front_auth WHERE provider_key = :providerKey AND provider_context = :customerId))
                WHERE p.product_number IN (:productNumbers)
                  AND p.version_id = :versionId;',
            [
                'providerKey'    => DebtorRepositoryInterface::class,
                'customerId'     => $customerId,
                'productNumbers' => $productNumbers,
                'versionId'      => Defaults::LIVE_VERSION,
            ],
            [
                'providerKey'    => ParameterType::STRING,
                'customerId'     => ParameterType::STRING,
                'productNumbers' => Connection::PARAM_STR_ARRAY,
                'versionId'      => ParameterType::STRING,
            ]
        );

        $adjustedReturn = [];
        foreach ($result as $resultItem) {
            if (empty($resultItem['productNumber'] ?? null)) {
                continue;
            }

            $adjustedReturn[$resultItem['productNumber']] = [
                'lowerPId'            => $resultItem['lowerPId'],
                'customProductNumber' => $resultItem['customProductNumber'],
            ];
        }

        return $adjustedReturn;
    }
}
