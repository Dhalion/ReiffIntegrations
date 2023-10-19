<?php

declare(strict_types=1);

namespace ReiffIntegrations\Components\CustomerAccount\EventSubscriber;

use Shopware\Core\SalesChannelRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class CustomerAccountEventSubscriber implements EventSubscriberInterface
{
    public const SHOPWARE_REDIRECT_ROUTES = [
        // profile
        'frontend.account.profile.save'       => self::SHOPWARE_PROFILE_PAGE_ROUTE,
        'frontend.account.profile.email.save' => self::SHOPWARE_PROFILE_PAGE_ROUTE,
        // address
        'frontend.account.address.create.page'         => self::SHOPWARE_ADDRESS_OVERVIEW_PAGE_ROUTE,
        'frontend.account.address.create'              => self::SHOPWARE_ADDRESS_OVERVIEW_PAGE_ROUTE,
        'frontend.account.address.edit.page'           => self::SHOPWARE_ADDRESS_OVERVIEW_PAGE_ROUTE,
        'frontend.account.address.edit.save'           => self::SHOPWARE_ADDRESS_OVERVIEW_PAGE_ROUTE,
        'frontend.account.address.set-default-address' => self::SHOPWARE_ADDRESS_OVERVIEW_PAGE_ROUTE,
        'frontend.account.address.delete'              => self::SHOPWARE_ADDRESS_OVERVIEW_PAGE_ROUTE,
    ];

    public const B2B_SUITE_REDIRECT_ROUTES = [
        // overall (contacts + address)
        'frontend.b2b.b2bconfirm.remove' => self::B2B_SUITE_ADDRESS_OVERVIEW_PAGE_ROUTE,
        // account/contacts
        'frontend.b2b.b2bcontact.new'    => self::B2B_SUITE_ADDRESS_OVERVIEW_PAGE_ROUTE,
        'frontend.b2b.b2bcontact.create' => self::B2B_SUITE_ADDRESS_OVERVIEW_PAGE_ROUTE,
        'frontend.b2b.b2bcontact.update' => self::B2B_SUITE_ADDRESS_OVERVIEW_PAGE_ROUTE,
        // address
        'frontend.b2b.b2baddress.new'    => self::B2B_SUITE_ADDRESS_SHIPPING_OVERVIEW_PAGE_ROUTE,
        'frontend.b2b.b2baddress.create' => self::B2B_SUITE_ADDRESS_SHIPPING_OVERVIEW_PAGE_ROUTE,
        'frontend.b2b.b2baddress.update' => self::B2B_SUITE_ADDRESS_SHIPPING_OVERVIEW_PAGE_ROUTE,
    ];

    private const SHOPWARE_PROFILE_PAGE_ROUTE          = 'frontend.account.profile.page';
    private const SHOPWARE_ADDRESS_OVERVIEW_PAGE_ROUTE = 'frontend.account.address.page';

    private const B2B_SUITE_ADDRESS_OVERVIEW_PAGE_ROUTE          = 'frontend.b2b.b2bcompany.index';
    private const B2B_SUITE_ADDRESS_BILLING_OVERVIEW_PAGE_ROUTE  = 'frontend.b2b.b2baddress.billing';
    private const B2B_SUITE_ADDRESS_SHIPPING_OVERVIEW_PAGE_ROUTE = 'frontend.b2b.b2baddress.shipping';

    public function __construct(private RouterInterface $router)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onCustomerAccountChanges',
        ];
    }

    public function onCustomerAccountChanges(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request instanceof Request
            || !$request->attributes->has(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)
            || !$request->attributes->has('_route')
        ) {
            return;
        }

        /* @phpstan-ignore-next-line */
        $route = strtolower((string) $request->attributes->get('_route'));

        if ($this->isHandledByShopwareRoute($event, $route)) {
            return;
        }

        $this->isHandledByB2bRoute($event, $request, $route);
    }

    private function isHandledByShopwareRoute(RequestEvent $event, string $route): bool
    {
        if (array_key_exists($route, self::SHOPWARE_REDIRECT_ROUTES)) {
            $redirectRoute = $this->router->generate(
                self::SHOPWARE_REDIRECT_ROUTES[$route]
            );

            if (empty($redirectRoute)) {
                return false;
            }

            $event->setResponse(new RedirectResponse($redirectRoute));

            return true;
        }

        return false;
    }

    private function isHandledByB2bRoute(RequestEvent $event, Request $request, string $route): void
    {
        $routeParameters = [];
        /** @var null|string $grantContext */
        $grantContext = $request->query->get('grantContext');

        if (!empty($grantContext)) {
            $explodedGrantContext = explode('::', $grantContext);
            $roleId               = end($explodedGrantContext);

            if (!empty($roleId)) {
                $routeParameters['roleId'] = $roleId;
            }
        }

        if (array_key_exists($route, self::B2B_SUITE_REDIRECT_ROUTES)) {
            $redirectRoute = $this->router->generate(
                self::B2B_SUITE_REDIRECT_ROUTES[$route],
                $routeParameters
            );

            if ($route === 'frontend.b2b.b2baddress.update'
                || $route === 'frontend.b2b.b2baddress.new'
                || $route === 'frontend.b2b.b2baddress.create') {
                $redirectRoute = '';

                if ($request->get('type') === 'billing') {
                    $redirectRoute = $this->router->generate(
                        self::B2B_SUITE_ADDRESS_BILLING_OVERVIEW_PAGE_ROUTE,
                        $routeParameters
                    );
                }
            }

            if ($route === 'frontend.b2b.b2bconfirm.remove' && $request->query->get('isUnderDelete') === '1') {
                return;
            }

            if (empty($redirectRoute)) {
                return;
            }

            $event->setResponse(new RedirectResponse($redirectRoute));
        }
    }
}
