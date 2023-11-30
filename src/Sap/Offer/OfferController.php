<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Offer;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Sap\DataAbstractionLayer\CustomerExtension;
use ReiffIntegrations\Sap\DataAbstractionLayer\ReiffCustomerEntity;
use ReiffIntegrations\Sap\Offer\ApiClient\OfferReadApiClient;
use ReiffIntegrations\Sap\Offer\ApiClient\Pdf\OfferPdfApiClient;
use ReiffIntegrations\Sap\Offer\Page\AccountOfferPageLoader;
use ReiffIntegrations\Sap\Offer\Page\OfferDetailPageLoader;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class OfferController extends StorefrontController
{
    public function __construct(
        private readonly AccountOfferPageLoader $offerPageLoader,
        private readonly OfferDetailPageLoader $offerDetailPageLoader,
        private readonly OfferReadApiClient $apiClient,
        private readonly LoggerInterface $logger,
        private readonly OfferPdfApiClient $pdfApiClient,
        private readonly AcceptOfferHandler $acceptOfferHandler
    ) {
    }

    #[Route('/b2boffer', name: 'frontend.k10r_reiff_integrations.account.offer.overview.page', options: ['seo' => false], defaults: ['_noStore' => true, '_loginRequired' => true], methods: ['GET'])]
    public function indexAction(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->offerPageLoader->load($request, $context);

        return $this->renderStorefront(
            '@Storefront/storefront/page/account/offer/index.html.twig',
            [
                'page'   => $page,
                'locale' => $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE),
            ]
        );
    }

    #[Route('/account/ajax-offer-list', name: 'frontend.k10r_reiff_integrations.account.offer.ajax_list', options: ['seo' => false], defaults: ['XmlHttpRequest' => true, '_loginRequired' => true], methods: ['POST'])]
    public function ajaxOfferList(Request $request, SalesChannelContext $context): Response
    {
        try {
            $customer = $context->getCustomer();

            if (!$customer) {
                throw new CustomerNotLoggedInException(404, '404', 'Customer not logged in');
            }

            /** @var null|ReiffCustomerEntity $customerData */
            $customerData = $customer->getExtension(CustomerExtension::EXTENSION_NAME);

            if ($customerData === null) {
                return $this->renderStorefront('@Storefront/storefront/component/k10r-offer/error-response.html.twig');
            }

            $apiResponse = $this->apiClient->readOffers($customerData);
        } catch (\Throwable $throwable) {
            // TODO: discuss about direct information to REIFF
            $this->logger->error(
                'Offers cannot be read.',
                [
                    'exception' => $throwable,
                ]
            );

            return new JsonResponse($throwable->getMessage());
        }

        if (!$apiResponse->isSuccess()) {
            return $this->renderStorefront('@Storefront/storefront/component/k10r-offer/error-response.html.twig');
        }

        $response = $this->renderStorefront('@Storefront/storefront/component/k10r-offer/offer-list.html.twig', [
            'documents' => $apiResponse->getDocuments(),
        ]);

        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    #[Route('/offers/{offerNumber}', name: 'frontend.k10r_reiff_integrations.account.offer.detail', options: ['seo' => false], defaults: ['_noStore' => true, '_loginRequired' => true], methods: ['GET'])]
    public function detailAction(Request $request, string $offerNumber, SalesChannelContext $context): Response
    {
        $page = $this->offerDetailPageLoader->load($offerNumber, $request, $context);

        $response = $this->renderStorefront('@Storefront/storefront/component/k10r-offer/detail.html.twig', [
            'page'   => $page,
            'locale' => $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE),
        ]);

        $statusCode = Response::HTTP_OK;

        if ($page->getOffer() === null) {
            $statusCode = Response::HTTP_NOT_FOUND;
        } elseif ($page->hasErrors()) {
            $statusCode = Response::HTTP_SERVICE_UNAVAILABLE;
        }

        $response->setStatusCode($statusCode);

        return $response;
    }

    #[Route('/offers/{offerNumber}/pdf', name: 'frontend.k10r_reiff_integrations.account.offer.document', options: ['seo' => false], defaults: ['_noStore' => true, '_loginRequired' => true, 'XmlHttpRequest' => true], methods: ['GET'])]
    public function document(string $offerNumber, SalesChannelContext $context): Response
    {
        $customer = $context->getCustomer();

        if (!$customer) {
            throw new CustomerNotLoggedInException(404, '404', 'Customer not logged in');
        }

        $offerNumber  = ltrim($offerNumber, '0');
        $documentData = $this->pdfApiClient->getOfferPdf($offerNumber, $context->getContext());

        /** @var ReiffCustomerEntity $reiffCustomer */
        $reiffCustomer = $customer->getExtension(CustomerExtension::EXTENSION_NAME);

        $document = $documentData->getDocument();

        if (!$documentData->isSuccess() || $document->getCustomerNumber() !== $reiffCustomer->getDebtorNumber() || $document->getDocumentNumber() !== $offerNumber) {
            $this->addFlash('warning', $this->trans('ReiffIntegrations.customer.offer.errorNotFoundMessage'));

            return $this->redirectToRoute('frontend.k10r_reiff_integrations.account.offer.overview.page');
        }

        $response = new Response($document->getPdf());
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $document->getFileName() ?? sprintf('%s.pdf', $offerNumber)));

        return $response;
    }

    #[Route('/offers/{offerNumber}/accept', name: 'frontend.k10r_reiff_integrations.account.offer.accept', options: ['seo' => false], defaults: ['_noStore' => true, '_loginRequired' => true, 'XmlHttpRequest' => true], methods: ['GET'])]
    public function accept(string $offerNumber, SalesChannelContext $context): Response
    {
        $result = $this->acceptOfferHandler->acceptOffer($offerNumber, $context);

        if (!$result) {
            return $this->renderStorefront('@Storefront/storefront/component/k10r-offer/error-response.html.twig');
        }

        return $this->redirectToRoute('frontend.k10r_reiff_integrations.account.offer.detail', ['offerNumber' => $offerNumber]);
    }
}
