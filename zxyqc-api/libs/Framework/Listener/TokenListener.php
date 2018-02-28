<?php

namespace Framework\Listener;

use Framework\Controller\TokenAuthenticatedController;
use Framework\Service\AuthenticationProvider;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TokenListener
{

    /**
     * @var AuthenticationProvider
     */
    private $authenticationProvider;

    public function __construct(AuthenticationProvider $authenticationProvider)
    {
        $this->authenticationProvider = $authenticationProvider;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        $controller = $event->getController();

        if (!is_array($controller)) {
            return;
        }

        if ($controller[0] instanceof TokenAuthenticatedController) {
            $this->validateRequest($event);
        }
    }

    /**
     * @param FilterControllerEvent $event
     */
    protected function validateRequest(FilterControllerEvent $event)
    {
        $request = $event->getRequest();

        if (!$request->headers->has('X-AUTH-TOKEN')) {
            throw new BadRequestHttpException('This action needs a valid X-AUTH-TOKEN header!');
        }

        $apiKey = $request->headers->get('X-API-KEY');
        $authorizationHeader = $request->headers->get('X-AUTH-TOKEN');

        if (!$this->authenticationProvider->isValid($apiKey, $authorizationHeader)) {
            throw new AccessDeniedHttpException('Invalid access token!');
        }
    }
}