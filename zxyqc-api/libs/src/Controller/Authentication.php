<?php

namespace Controller;

use Framework\Controller\AppBaseController;
use Framework\Controller\PublicAccessController;
use Framework\Entity\Where;
use Framework\Exception\BadCredentialException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class Authentication extends AppBaseController
{
    public function getTokenAction(Request $request)
    {
        $authenticatingProvider = $this->getAuthenticationProvider();
        $user = $this->get('entity_manager_factory')->getRepository('Entity\\User')->findByUserName($request->request->get('username'));

        if($user == null || sha1($request->request->get('password')) != $user->password) {
            throw new BadCredentialException("Invalid credential");
        }


        $designation = $user->designation;
       $user_id = $user->contact_id;

        if($designation==237)
           $qc_filter = "WHERE hr_tbl.`designation` IN (237) AND hr_tbl.contact_id ='$user_id'";
        else {
            $qc_filter = "WHERE hr_tbl.`designation` IN (237)";

        }
       if ($designation==642 || $designation == 2097)
            $qa_filter = "WHERE hr_tbl.`designation` IN (642,2097) AND hr_tbl.contact_id ='$user_id'";
        else
            $qa_filter = "WHERE hr_tbl.`designation` IN (642,2097";


     if($designation==659 || $designation == 9268 || $designation == 9348)
            $qam_filter = "WHERE hr_tbl.`designation` IN (659,9268,9348) AND hr_tbl.contact_id ='$user_id'";
        else
            $qam_filter = "WHERE hr_tbl.`designation` IN (659,9268,9348)";


        $sql ="SELECT 
contact_info.id as contact_id,
  CONCAT_WS(
    ' ',
    contact_info.`fname`,
    contact_info.`mname`,
    contact_info.`lname`
  ) AS emp_name,
  division_tbl.`name` AS bu
FROM
  hr_tbl 
  LEFT JOIN contact_info 
    ON contact_info.id = hr_tbl.`contact_id` 
  LEFT JOIN division_tbl 
    ON division_tbl.id = hr_tbl.`dept` 
  LEFT JOIN department_tbl 
    ON department_tbl.id = hr_tbl.`department` 
$qam_filter
  AND hr_tbl.`basic_salary` > 0 
  AND hr_tbl.status != 1611 
  AND hr_tbl.id != 0";

        $desig = $this->getRepository()->getResults($sql);

        $sql = "SELECT 
contact_info.id as contact_id,
  CONCAT_WS(
    ' ',
    contact_info.`fname`,
    contact_info.`mname`,
    contact_info.`lname`
  ) AS emp_name,
  division_tbl.`name` AS bu
FROM
  hr_tbl 
  LEFT JOIN contact_info 
    ON contact_info.id = hr_tbl.`contact_id` 
  LEFT JOIN division_tbl 
    ON division_tbl.id = hr_tbl.`dept` 
  LEFT JOIN department_tbl 
    ON department_tbl.id = hr_tbl.`department` 
$qa_filter
  AND hr_tbl.`basic_salary` > 0 
  AND hr_tbl.status != 1611 
  AND hr_tbl.id != 0";
        $qa = $this->getRepository()->getResults($sql);
$sql = "SELECT 
contact_info.id as contact_id,
  CONCAT_WS(
    ' ',
    contact_info.`fname`,
    contact_info.`mname`,
    contact_info.`lname`
  ) AS emp_name,
  division_tbl.`name` AS bu
FROM
  hr_tbl 
  LEFT JOIN contact_info 
    ON contact_info.id = hr_tbl.`contact_id` 
  LEFT JOIN division_tbl 
    ON division_tbl.id = hr_tbl.`dept` 
  LEFT JOIN department_tbl 
    ON department_tbl.id = hr_tbl.`department` 
$qc_filter
  AND hr_tbl.`basic_salary` > 0 
  AND hr_tbl.status != 1611 
  AND hr_tbl.id != 0";

        $qc = $this->getRepository()->getResults($sql);

        return new JsonResponse(array(
            'token' => $authenticatingProvider->generateToken(
                $request->headers->get('X-API-KEY'),
                array('full_name' => $user->full_name,
                    'id' => $user->id,
                    'profile' => "img.php?state=ui&pat={$user->contact_id}_user_0.jpg&w=60&h=80",
                    'contact_id' => $user->contact_id,
                    'division_id' => $user->division_id,
                    'qam' => $desig,
                    'qa' => $qa,
                    'qc' => $qc

                )
            )
        ));
    }
}