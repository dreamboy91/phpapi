<?php

namespace Framework\Controller;

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\HttpFoundation\JsonResponse;

class ErrorController implements PublicAccessController
{
    public function exceptionAction(FlattenException $exception)
    {
        switch ($exception->getClass()) {
            case 'Symfony\Component\HttpKernel\Exception\NotFoundHttpException':
                return $this->createErrorJsonResponse('Resource Not Found', 404);
                break;
            case 'Framework\Exception\BadCredentialException':
                return $this->createErrorJsonResponse('Invalid credential', 400);
                break;
            case 'Symfony\Component\HttpKernel\Exception\BadRequestHttpException':
                return $this->createErrorJsonResponse($exception->getMessage(), 400);
                break;
            case 'Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException':
                return $this->createErrorJsonResponse('Method Not Allowed', 405);
                break;
            case 'Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException':
                return $this->createErrorJsonResponse($exception->getMessage(), 403);
                break;
            default:
                return $this->createErrorJsonResponse('Something went wrong! ('.$exception->getMessage().')', $exception->getStatusCode());
        }
    }

    /**
     * @param $str
     * @param $code
     * @return JsonResponse
     */
    protected function createErrorJsonResponse($str, $code)
    {
        return new JsonResponse(array('status' => $code, 'msg' => $str), $code);
    }
}