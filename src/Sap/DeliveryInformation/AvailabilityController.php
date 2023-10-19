<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\DeliveryInformation;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class AvailabilityController extends StorefrontController
{
    private const CODE_MAPPING = [
        1          => 'ReiffIntegrations.checkout.cart.delivery.deliveryTime.code1',
        2          => 'ReiffIntegrations.checkout.cart.delivery.deliveryTime.code2',
        3          => 'ReiffIntegrations.checkout.cart.delivery.deliveryTime.code3',
        4          => 'ReiffIntegrations.checkout.cart.delivery.deliveryTime.code4',
        5          => 'ReiffIntegrations.checkout.cart.delivery.deliveryTime.code5',
        'fallback' => 'ReiffIntegrations.checkout.cart.delivery.deliveryTime.codeFallback',
    ];

    public function __construct(private readonly AvailabilityService $availabilityService)
    {
    }

    #[Route('/availability-information', name: 'frontend.reiff.availability', options: ['seo' => false], defaults: ['XmlHttpRequest' => true, '_loginRequired' => true], methods: ['POST'])]
    public function indexAction(Request $request, SalesChannelContext $context): Response
    {
        /** @var array $productNumbers */
        $productNumbers = $request->get('productNumbers');

        if (empty($productNumbers)) {
            return new JsonResponse(['success' => false]);
        }

        $availabilitySearchResult = $this->availabilityService->fetchAvailabilities($productNumbers);

        if ($availabilitySearchResult->count() === 0) {
            return new JsonResponse(['success' => false]);
        }

        foreach ($availabilitySearchResult->getElements() as $searchResult) {
            $searchResult->setTranslatedResult($this->getTranslatedDeliveryTime($searchResult->getCode()));
        }

        return new JsonResponse([
            'success' => true,
            'results' => array_values($availabilitySearchResult->getElements()),
        ]);
    }

    private function getTranslatedDeliveryTime(int $code): string
    {
        if (array_key_exists($code, self::CODE_MAPPING)) {
            return $this->trans(self::CODE_MAPPING[$code]);
        }

        return $this->trans(self::CODE_MAPPING['fallback']);
    }
}
