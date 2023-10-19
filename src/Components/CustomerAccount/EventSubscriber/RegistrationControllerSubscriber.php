<?php

declare(strict_types=1);

namespace ReiffIntegrations\Components\CustomerAccount\EventSubscriber;

use Symfony\Component\HttpKernel\Event\ControllerEvent;

class RegistrationControllerSubscriber
{
    public function onControllerEvent(ControllerEvent $event): void
    {
        try {
            $request = $event->getRequest();

            if (!$request) {
                return;
            }

            $route = $request->attributes->get('_route');

            if ($route !== 'frontend.account.register.save') {
                return;
            }

            $request->request?->set('createCustomerAccount', 'on');
        } catch(\Exception) {

        }
    }
}
