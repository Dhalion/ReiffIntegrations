<?php declare(strict_types=1);

namespace ReiffIntegrations\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use ReiffIntegrations\Installer\CustomFieldInstaller;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Twig\Environment;
use Shopware\Core\System\SystemConfig\SystemConfigService;


#[Route(defaults: ['_routeScope' => ['storefront'], 'auth_required' => false])]
class RequestPriceApiController extends AbstractController {
    const MAIL_TEMPLATE_TECHNICAL_NAME = 'request_product_price_mail_template';

    public function __construct(
        private EntityRepository $productRepository,
        private EntityRepository $mailTemplateRepository,
        private Environment $twig,
        private SystemConfigService $systemConfigService
    ){
    }

    #[Route(
        path: '/reiff-integrations/request-price/{productId}',
        name: 'api.request_price',
        defaults: [
            '_scope' => 'storefront',
            'auth_required' => false,
        ],
        methods: ['GET'])]
    public function getPriceRequestMailBody(Request $request, SalesChannelContext $context, string $productId): JsonResponse | Response {
        $criteria = new Criteria([$productId]);
        $product = $this->productRepository->search($criteria, $context->getContext())->first();

        $priceRequestRequired = $product->getCustomFields()[CustomFieldInstaller::PRODUCT_ANFRAGE] ?? false;
        $customer = $context->getCustomer();

        if (!$priceRequestRequired) {
            return new JsonResponse('Price request not required', 404);
        }

        if (!$customer) {
            return new JsonResponse('Price request only for customers', 400);
        }

        $mailTemplate = $this->getPriceRequestMailTemplateByLanguage($context);

        $templatePlain = $this->twig->createTemplate($mailTemplate->getContentPlain());
        $contentPlain = $templatePlain->render([
            'product' => $product,
            'customer' => $customer,
        ]);
        $recipient = $this->systemConfigService->get('ReiffIntegrations.config.productPriceRequestReiffMail');
        $encodedBody = urlencode($contentPlain);
        $encodedBody = str_replace('+', '%20', $encodedBody);
        $mailtoUrl = sprintf(
            'mailto:%s?subject=%s&body=%s',
            urlencode($recipient ?? 'preisanfragen@example.com'),
            urlencode($mailTemplate->getSubject()),
            $encodedBody
        );

        return $this->redirect($mailtoUrl);
    }


    private function getPriceRequestMailTemplateByLanguage(SalesChannelContext $salesChannelContext): MailTemplateEntity {
        $languageId = $salesChannelContext->getContext()->getLanguageId();

        $mailTemplateCriteria = new Criteria();
        $mailTemplateCriteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', self::MAIL_TEMPLATE_TECHNICAL_NAME));
        $mailTemplateCriteria->addFilter(new EqualsFilter('translations.languageId', $languageId));
        $mailTemplateCriteria->addAssociation('translations');
        $mailTemplateCriteria->addAssociation('mailTemplateType');

        return $this->mailTemplateRepository->search($mailTemplateCriteria, $salesChannelContext->getContext())->first();
    }
}
