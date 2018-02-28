<?php

namespace Framework\Controller;

use Framework\Entity\Repository;
use Framework\Service\AuthenticationProvider;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AppBaseController extends ContainerAware
{

    /**
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    protected function get($id) {
        return $this->getContainer()->get($id);
    }

    protected function getParameter($name) {
        return $this->getContainer()->getParameter($name);
    }

    /**
     * @return Repository
     */
    public function getRepository()
    {
        return $this->get('entity_manager_factory')->getRepository();
    }

    /**
     * @return AuthenticationProvider
     */
    protected function getAuthenticationProvider()
    {
        return $this->get('service.authentication_provider');
    }

    protected function getUser() {
        return $this->getAuthenticationProvider()->getUser();
    }
}