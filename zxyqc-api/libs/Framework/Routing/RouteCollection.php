<?php

namespace Framework\Routing;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection as BaseRouteCollection;

class RouteCollection extends BaseRouteCollection
{
    public function __construct($routeArray = array())
    {
        foreach ($routeArray as $key => $route) {
            if (!empty($route['requirements'])) {
                $this->add($key, new Route($route['pattern'], $route['defaults'], $route['requirements']));
            } else {
                $this->add($key, new Route($route['pattern'], $route['defaults']));
            }
        }
    }
}