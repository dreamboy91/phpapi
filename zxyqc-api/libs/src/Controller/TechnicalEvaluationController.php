<?php

namespace Controller;


use Framework\Controller\AppBaseController;
use Framework\Controller\TokenAuthenticatedController;
use Helper\FileUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TechnicalEvaluationController extends AppBaseController implements TokenAuthenticatedController
{
    public function indexAction($limit)
    {
		$max= 10;
        $min=$limit*$max;
		
        $sql= "SELECT 
  request_for_technical_evaluation_tbl.id AS id,
  SUBSTR(request_date_time,1,10) AS `date`,
  IF(
    request_for_technical_evaluation_tbl.`business_unit` != '',
    request_for_technical_evaluation_tbl.`business_unit`,
    '---'
  ) AS `division`,
  IF(
    request_for_technical_evaluation_tbl.`factory_name` != '',
    request_for_technical_evaluation_tbl.`factory_name`,
    '---'
  ) AS `factory`,
  IF(
    request_for_technical_evaluation_tbl.`factory_key_contact` != '',
    request_for_technical_evaluation_tbl.`factory_key_contact`,
    '---'
  ) AS `contact_name`,
  
   IF(
    request_for_technical_evaluation_tbl.`designation` != '',
    request_for_technical_evaluation_tbl.`designation`,
    '---'
  ) AS `designation`,
  
   IF(
    request_for_technical_evaluation_tbl.`contact` != '',
    request_for_technical_evaluation_tbl.`contact`,
    '---'
  ) AS `contact_no`,
  
  IF(
    request_for_technical_evaluation_tbl.`i_am` != '',
    request_for_technical_evaluation_tbl.`i_am`,
    '---'
  ) AS `assign_by`,
  IF(
    CONCAT(
      customer_tbl.ol_name,
      ' | ',
      customer_tbl.threedigit_code
    ) != '',
    CONCAT(
      customer_tbl.ol_name,
      ' | ',
      customer_tbl.threedigit_code
    ),
    request_for_technical_evaluation_tbl.`new_customer`
  ) AS customer,  
  (SELECT
  COUNT(technical_evaluation_tbl.id) AS id
FROM
  technical_evaluation_tbl
   WHERE technical_evaluation_tbl.ref_id=  request_for_technical_evaluation_tbl.id) AS ref_count
   
FROM
  request_for_technical_evaluation_tbl 
  LEFT JOIN `company_tbl` AS customer_tbl 
    ON `customer_tbl`.id = request_for_technical_evaluation_tbl.`customer` 
  LEFT JOIN `contact_info` AS assign_tbl 
    ON assign_tbl.id = request_for_technical_evaluation_tbl.`assign_to`  
WHERE request_for_technical_evaluation_tbl.assign_to = ?
GROUP BY request_for_technical_evaluation_tbl.id order by request_for_technical_evaluation_tbl.id  desc limit $min,$max ";


  $last_entry="SELECT 
  `technical_evaluation_tbl`.`ref_id` AS last_update_id 
FROM
  `technical_evaluation_tbl` 
WHERE technical_evaluation_tbl.`entryby` =?
ORDER BY technical_evaluation_tbl.id DESC 
LIMIT 1 ";

  $tecnical_evaluation_last_entry = $this->getRepository()->getResults($last_entry, array($this->getUser()->contact_id));
  
        $technical_evaluation = $this->getRepository()->getResults($sql, array($this->getUser()->contact_id));

        $values=array(
             'list' => $technical_evaluation,
			  'last_id' => empty($tecnical_evaluation_last_entry) ? null: $tecnical_evaluation_last_entry[0]['last_update_id']
        );
        //return new JsonResponse($technical_evaluation);
        return new JsonResponse($values);
    }

    public function saveAction(Request $request)
    {
        $checkData = $data = json_decode($request->getContent(), true);


        file_put_contents('Technical_Evaluation_last'.'.txt', $request->getContent());

        $checkData['entryby'] = $this->getUser()->contact_id;
        unset($checkData['images']);

       // return new JsonResponse($checkData);
		
        $checkData['establishedYear'] = isset($data['establishedYear'])?substr($data['establishedYear'],0,10):'0000-00-00';
        $checkData['plantProduction'] = isset($data['plantProduction'])?substr($data['plantProduction'],0,10):'0000-00-00';

        $checkData = array_filter($checkData);

        $whereArray = array();
        foreach($checkData as $key => $value) {
            $whereArray["$key=?"] = $value;
        }


        if($this->getRepository()->from('technical_evaluation_tbl')->findBy($whereArray)->rowCount()>0) {
            return new JsonResponse(array('error'=>409), 409);
        }


        $checkData['entrytime'] = date('Y-m-d H:i:s');
        $id = $this->getRepository()->from('technical_evaluation_tbl')->insert($checkData);

        $this->uploadImage($id, $data);

        return new JsonResponse(array(
                'id'=>$id)
        );

    }

    /**
     * @param $id
     * @param $data
     * @return string
     * @throws \Exception
     */
    public function uploadImage($id, $data)
    {
        if(!isset($data['images']) || empty($data['images'])) {
            return;
        }

        $photos = FileUtil::uploadFileFromDataUrls(
            realpath(WEB_DIR . "/../mis/images/technical_evaluation_photo/"),
            "{$id}_technical_evaluation_photo",
            $data['images']
        );

        $this->getRepository()->from('technical_evaluation_tbl')->update(array(
            'photo' => implode(',', $photos)
        ), array('id=?' => $id));
    }


    public function detailsAction(Request $request)
    {
        $detailsFor= json_decode($request->getContent(), true);
        $sql= "SELECT 
  technical_evaluation_tbl.id,
  DATE_FORMAT(
    technical_evaluation_tbl.entrytime,
    '%d-%m-%Y %h:%i %p'
  ) AS entrytime,
   IF(
    technical_evaluation_tbl.photo = '',
    '0',
    LENGTH(
      technical_evaluation_tbl.photo
    ) - LENGTH(
      REPLACE(
        technical_evaluation_tbl.photo,
        ',',
        ''
      )
    ) + 1
  ) AS photos,
  technical_evaluation_tbl.photo AS images,
 SUBSTR(request_for_technical_evaluation_tbl.request_date_time,1,10) AS `date`
  
FROM
 technical_evaluation_tbl
 LEFT JOIN `request_for_technical_evaluation_tbl` ON
 request_for_technical_evaluation_tbl.id = technical_evaluation_tbl.ref_id
 
WHERE technical_evaluation_tbl.ref_id =?";

        $techncal_evaluation_details = $this->getRepository()->getResults($sql, array($detailsFor));
        return new JsonResponse($techncal_evaluation_details);

    }

}
