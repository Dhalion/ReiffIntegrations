<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Controller\Administration;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OrderController extends AbstractController
{
    private EntityRepository $reiffOrderRepository;

    public function __construct(EntityRepository $reiffOrderRepository)
    {
        $this->reiffOrderRepository = $reiffOrderRepository;
    }

    #[Route('/api/_action/reiff-integrations/order/reset-export', name: 'api.action.reiff_integrations.order.reset_export', defaults: ['_routeScope' => ['api']], methods: ['POST'])]
    public function resetExport(Request $request, Context $context): Response
    {
        if (empty($request->get('orderId'))) {
            return new JsonResponse(['status' => false, 'message' => 'missing order id'], Response::HTTP_NOT_FOUND);
        }

        $this->reiffOrderRepository->upsert(
            [
                [
                    'orderId'     => $request->get('orderId'),
                    'exportedAt'  => null,
                    'notifiedAt'  => null,
                    'queuedAt'    => null,
                    'exportTries' => 0,
                ],
            ],
            $context
        );

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
