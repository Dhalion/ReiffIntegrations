<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Page\Orders;

use Doctrine\DBAL\Connection;
use ReiffIntegrations\Sap\Api\Client\Orders\OrderDetailApiClient;
use ReiffIntegrations\Sap\Api\Client\Orders\OrderListApiClient;
use ReiffIntegrations\Sap\DataAbstractionLayer\CustomerExtension;
use ReiffIntegrations\Sap\DataAbstractionLayer\ReiffCustomerEntity;
use ReiffIntegrations\Util\Configuration;
use ReiffIntegrations\Util\Traits\UnitDataTrait;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\Page;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrdersPageLoader
{
    use UnitDataTrait;

    public const PARAMETER_FROM_DATE = 'fromDate';
    public const PARAMETER_TO_DATE   = 'toDate';

    public function __construct(
        private readonly GenericPageLoaderInterface $genericPageLoader,
        private readonly OrderListApiClient $orderListClient,
        private readonly OrderDetailApiClient $orderDetailClient,
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public function load(Request $request, SalesChannelContext $salesChannelContext): OrdersPage
    {
        $page = $this->getBasicPage($salesChannelContext, $request);

        /** @var OrdersPage $page */
        $page = OrdersPage::createFrom($page);

        $customer = $salesChannelContext->getCustomer();

        if ($customer === null) {
            return $page;
        }

        /** @var null|ReiffCustomerEntity $customerData */
        $customerData = $customer->getExtension(CustomerExtension::EXTENSION_NAME);

        if ($customerData === null) {
            return $page;
        }

        if ($request->query->has(self::PARAMETER_FROM_DATE)) {
            try {
                $fromDate = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->query->get(self::PARAMETER_FROM_DATE));

                if ($fromDate) {
                    $page->setFromDate($fromDate);
                }
            } catch (\Exception $e) {
                // No-op, invalid date results in default case
            }
        }

        if ($request->query->has(self::PARAMETER_TO_DATE)) {
            try {
                $toDate = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->query->get(self::PARAMETER_TO_DATE));

                if ($toDate) {
                    $page->setToDate($toDate);
                }
            } catch (\Exception $e) {
                // No-op, invalid date results in default case
            }
        }

        $orderResult = $this->orderListClient->getOrders(
            $customerData,
            $page->getFromDate(),
            $page->getToDate(),
            $customer
        );

        $page->setSuccess($orderResult->isSuccess());
        $page->setOrders($orderResult->getOrders());

        return $page;
    }

    public function loadDetails(string $orderNumber, Request $request, SalesChannelContext $salesChannelContext): OrderDetailPage
    {
        $page = $this->getBasicPage($salesChannelContext, $request);
        /** @var OrderDetailPage $page */
        $page = OrderDetailPage::createFrom($page);

        /** @var CustomerEntity $customer See $this::getBasicPage() */
        $customer = $salesChannelContext->getCustomer();

        /** @var ReiffCustomerEntity $customerData */
        $customerData = $customer->getExtension(CustomerExtension::EXTENSION_NAME);

        $order = $this->orderDetailClient->getOrder(
            $orderNumber,
            $salesChannelContext->getContext(),
            $this->fetchLanguageCode($salesChannelContext)
        )->getOrder();

        $page->setOrder($order);

        // Make sure the order belongs to our current customer
        if ($order && $order->getDebtorNumber() !== $customerData->getDebtorNumber()) {
            throw new NotFoundHttpException();
        }

        $this->updateProductUnits($salesChannelContext->getLanguageId());

        if ($page->getOrder() !== null) {
            foreach ($page->getOrder()->getLineItems()->getIterator() as $lineItem) {
                $lineItem->setUnit($this->getSalesUnit($lineItem->getUnit(), $lineItem->getUnit()));
            }
        }

        return $page;
    }

    private function getBasicPage(SalesChannelContext $salesChannelContext, Request $request): Page
    {
        if (!$salesChannelContext->getCustomer()) {
            throw new CustomerNotLoggedInException(404, '404', 'Customer not logged in');
        }

        $page = $this->genericPageLoader->load($request, $salesChannelContext);
        $page->getMetaInformation()?->setRobots('noindex,follow');

        return $page;
    }

    private function fetchLanguageCode(SalesChannelContext $context): string
    {
        $languageCode = $context->getCustomer()?->getLanguage()?->getTranslationCode()?->getCode();

        if ($languageCode === null) {
            $languageCode = $this->systemConfigService->getString(Configuration::CONFIG_KEY_API_FALLBACK_LANGUAGE_CODE);
        }

        return strtoupper(substr($languageCode, 0, 2));
    }
}
