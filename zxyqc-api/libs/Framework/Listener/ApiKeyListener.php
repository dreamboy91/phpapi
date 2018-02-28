<?php

namespace Framework\Listener;

use Framework\Controller\PublicAccessController;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ApiKeyListener
{
    private $validKeys;

    public function __construct(array $keys = array())
    {
        $this->validKeys = $keys;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        $controller = $event->getController();

        if (!is_array($controller)) {
            return;
        }

        if (!$controller[0] instanceof PublicAccessController) {
            $this->validateRequest($event);
        }
    }

    /**
     * @param FilterControllerEvent $event
     */
    protected function validateRequest(FilterControllerEvent $event)
    {
        $request = $event->getRequest();

        if (!$request->headers->has('X-API-KEY')) {
            throw new AccessDeniedHttpException('This action needs a valid X-API-KEY header!');
        }

        $apiKey = $request->headers->get('X-API-KEY');

        if (!in_array($apiKey, $this->validKeys)) {
            throw new AccessDeniedHttpException('This action needs a valid X-API-KEY!');
        }
    }
}