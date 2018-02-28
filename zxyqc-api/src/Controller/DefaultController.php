<?php

namespace Controller;

use Framework\Controller\AppBaseController;
use Framework\Controller\PublicAccessController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DefaultController extends AppBaseController implements PublicAccessController
{
    public function indexAction($name)
    {
        $user = $this->get('user_model')->findByUserName($name);

        if($user == null) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse(array('Hello' => $user->full_name));
    }
}