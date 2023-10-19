<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Controller\Storefront;

use ReiffIntegrations\Sap\Api\Client\Pdf\OrderPdfApiClient;
use ReiffIntegrations\Sap\DataAbstractionLayer\CustomerExtension;
use ReiffIntegrations\Sap\DataAbstractionLayer\ReiffCustomerEntity;
use ReiffIntegrations\Sap\Page\Orders\OrdersPageLoader;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class OrdersController extends StorefrontController
{
    public const DOCUMENT_TYPE_INVOICE  = 'invoice';
    public const DOCUMENT_TYPE_DELIVERY = 'delivery';

    public function __construct(private readonly OrdersPageLoader $ordersPageLoader, private readonly OrderPdfApiClient $pdfApiClient)
    {
    }

    // This route replaces b2b_order.controller, so it remains accessible as /b2border
    #[Route('/orders', name: 'frontend.b2b.orders.index', options: ['seo' => false], defaults: ['_noStore' => true, '_loginRequired' => true], methods: ['GET'])]
    public function indexAction(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->ordersPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/orders/index.html.twig', ['page' => $page]);
    }

    #[Route('/orders/{orderNumber}', name: 'frontend.b2b.orders.detail', options: ['seo' => false], defaults: ['_noStore' => true, '_loginRequired' => true, 'XmlHttpRequest' => true], methods: ['GET'])]
    public function orderDetails(string $orderNumber, Request $request, SalesChannelContext $context): Response
    {
        $page = $this->ordersPageLoader->loadDetails($orderNumber, $request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/orders/detail.html.twig', ['page' => $page]);
    }

    #[Route('/orders/{orderNumber}/{documentType}/{documentNumber}', name: 'frontend.b2b.orders.detail.invoice', options: ['seo' => false], defaults: ['_noStore' => true, '_loginRequired' => true, 'XmlHttpRequest' => true], methods: ['GET'])]
    public function document(string $orderNumber, string $documentType, string $documentNumber, Request $request, SalesChannelContext $context): Response
    {
        $customer = $context->getCustomer();

        if (!$customer) {
            throw new CustomerNotLoggedInException(404, '404', 'Customer not logged in');
        }

        $documentNumber = ltrim($documentNumber, '0');

        switch ($documentType) {
            case self::DOCUMENT_TYPE_INVOICE:
                $documentData = $this->pdfApiClient->getInvoicePdf($documentNumber, $context->getContext());

                break;
            case self::DOCUMENT_TYPE_DELIVERY:
                $documentData = $this->pdfApiClient->getDeliveryPdf($documentNumber, $context->getContext());

                break;
            default:
                throw $this->createNotFoundException();
        }

        /** @var ReiffCustomerEntity $reiffCustomer */
        $reiffCustomer = $customer->getExtension(CustomerExtension::EXTENSION_NAME);

        $document = $documentData->getDocument();

        if (!$documentData->isSuccess() || $document->getCustomerNumber() !== $reiffCustomer->getDebtorNumber() || $document->getDocumentNumber() !== $documentNumber) {
            $this->addFlash('warning', $this->trans('ReiffIntegrations.customer.offer.errorNotFoundMessage'));

            return $this->redirectToRoute('frontend.b2b.orders.index');
        }

        $response = new Response($document->getPdf());
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $document->getFileName() ?? sprintf('%s.pdf', $documentNumber)));

        return $response;
    }
}
