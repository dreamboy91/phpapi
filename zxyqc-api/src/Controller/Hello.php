<?php

namespace Controller;



use Framework\Controller\AppBaseController;
use Framework\Controller\TokenAuthenticatedController;
use pdf\Pdf;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class Hello extends AppBaseController implements TokenAuthenticatedController
{
    public function indexAction()
    {
        $user_id = $this->getUser()->contact_id;
        $user_photo = 'http://l2nsoft.ml/mis.zxyinternational.com/mis/size_measurement_inspection.php?state=details&id=13&1477996939704';
        $d = 'http://l2nsoft.ml/mis.zxyinternational.com/mis/final_inspection.php?state=details&id=11';

        return new JsonResponse(array($d));
        // return new JsonResponse(array('Hello' => $this->getUser()->full_name, 'img' =>$user_photo,'id' => $this->getUser()->contact_id));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function saveAction(Request $request)
    {
        $id=9;
        $filename='hellow';
        $pdf_val = '<div><h1>MY NAME IS MIRAJ</h1> </div>';
        Pdf::GeneratePDF($id,$filename,$pdf_val);


        // $file = FileUtil::GeneratePDF($result);


        // file_put_contents(realpath(WEB_DIR . "/../mis/testPDF/") . DIRECTORY_SEPARATOR . Pdf::GeneratePDF($result));


    }


}
