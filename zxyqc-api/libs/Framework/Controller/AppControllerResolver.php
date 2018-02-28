<?php

namespace Framework\Controller;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;

class AppControllerResolver extends ControllerResolver
{
    /**
     * @var Container
     */
    private $container;

    public function __construct(Container $container)
    {
        parent::__construct(null);
        $this->container = $container;
    }

    protected function createController($controller)
    {
        $controllerDefinition = parent::createController($controller);
        $controllerDefinition[1] .= 'Action';

        if($controllerDefinition[0] instanceof ContainerAware) {
            $controllerDefinition[0]->setContainer($this->container);
        }

        return $controllerDefinition;
    }
}