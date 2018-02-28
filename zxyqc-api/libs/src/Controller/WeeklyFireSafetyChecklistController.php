<?php

namespace Controller;

use Framework\Controller\AppBaseController;
use Framework\Controller\TokenAuthenticatedController;
use Helper\FileUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class WeeklyFireSafetyChecklistController extends AppBaseController implements TokenAuthenticatedController
{

    public function indexAction($limit)
    {
		$max= 10;
        $min=$limit*$max;
		
        $sql = "SELECT
  weekly_fire_safety_tbl.id AS id,
  weekly_fire_safety_assign_to.`preffered_name` AS checked_by,
  ofc_tbl.`ol_name` AS `factory`,
  division_tbl.`name` AS `bu`,
  entry_by.userid AS qa_manager,
  IF(
    weekly_fire_safety_tbl.`date` != '0000-00-00',
    DATE_FORMAT(
      weekly_fire_safety_tbl.`date`,
      '%d-%m-%Y'
    ),
    '---'
  ) AS `date`,
  (SELECT
  COUNT(weekly_fire_safety_check_list_tbl.id) AS id
FROM
  weekly_fire_safety_check_list_tbl
   WHERE weekly_fire_safety_check_list_tbl.ref_id=  weekly_fire_safety_tbl.id) AS ref_count
FROM
  weekly_fire_safety_tbl
  LEFT JOIN company_tbl AS ofc_tbl
    ON ofc_tbl.id = weekly_fire_safety_tbl.select_factory
  LEFT JOIN division_tbl
    ON division_tbl.`id` = weekly_fire_safety_tbl.for_business_unit
  LEFT JOIN `system_user` AS entry_by
    ON (
      entry_by.`contact_id` = weekly_fire_safety_tbl.`entryby`
    )
  LEFT JOIN `hr_tbl` AS weekly_fire_safety_assign_to
    ON (
      weekly_fire_safety_assign_to.`contact_id` = weekly_fire_safety_tbl.`assign_to`
    )
WHERE weekly_fire_safety_tbl.assign_to =?
GROUP BY weekly_fire_safety_tbl.id  order by weekly_fire_safety_tbl.id desc limit $min,$max ";

        $last_entry="SELECT 
  `weekly_fire_safety_check_list_tbl`.`ref_id` AS last_update_id 
FROM
  `weekly_fire_safety_check_list_tbl` 
WHERE weekly_fire_safety_check_list_tbl.`entryby` = ? 
ORDER BY weekly_fire_safety_check_list_tbl.id DESC 
LIMIT 1  ";

        $weekly_fire_last_entry = $this->getRepository()->getResults($last_entry, array($this->getUser()->contact_id));
        $weekly_fire_safety_checklist = $this->getRepository()->getResults($sql, array($this->getUser()->contact_id));

        $values=array(
            'list' => $weekly_fire_safety_checklist,
            'last_id' => empty($weekly_fire_last_entry) ? null: $weekly_fire_last_entry[0]['last_update_id']
        );
        return new JsonResponse($values);
    }

    public function saveAction(Request $request)
    {
         $data = json_decode($request->getContent(), true);
         file_put_contents('weeklyV2.txt', $request->getContent());

        $parent_tbl = array(
            'status' => 2,
            'ref_id' => '',
            'item' => '',
            'boiler_renew' => '0000-00-00',
            'boiler_validity' => '0000-00-00',
            'generator_renew' => '0000-00-00',
            'generator_validity' => '0000-00-00',
            'environment_renew' => '0000-00-00',
            'environment_validity' => '0000-00-00',
            'bond_renew' => '0000-00-00',
            'bond_validity' => '0000-00-00',
            'fire_renew' => '0000-00-00',
            'fire_validity' => '0000-00-00',
            'latitude' => '',
            'longitude' => '',
			'fireComment' => '',
			'boilerComment' => '',
			'generatorComment' => '',
			'environmentComment' => '',
			'bondComment' => ''

        );

        foreach ($parent_tbl as $key => $_d) {
            if (!isset($data[$key])) {
                continue;
            }
            $parent_tbl[$key] = $data[$key];
        }
        $DateTimeArray=array(
            10=>'boiler_renew',
            1=> 'boiler_validity',
            2=> 'generator_renew',
            3=> 'generator_validity',
            4=>'environment_renew',
            5=> 'environment_validity',
            6=>'bond_renew',
            7=>'bond_validity',
            8=>'fire_renew',
            9=>'fire_validity'
        );
        foreach($DateTimeArray as $fields)
        {
            $date=$this->SeparatedDateInDatetime($fields, $data);
            $parent_tbl["$fields"] = $date;
        }
/*
		foreach($DateTimeArray as $fields)
        {
            $date=$this->SeparatedDateInDatetime($fields, $data);
            $parent_tbl[$fields] = $date;
        }
*/
       // return new JsonResponse($parent_tbl);

        $parent_tbl['entryby'] = $this->getUser()->contact_id;
        $parent_tbl = array_filter($parent_tbl);

        $whereArray = array();
        foreach ($parent_tbl as $key => $value) {
            $whereArray["$key=?"] = $value;
        }

        if ($this->getRepository()->from('weekly_fire_safety_check_list_tbl')->findBy($whereArray)->rowCount() > 0) {
            return new JsonResponse(array('error' => 409), 409);
        }

        $parent_tbl['entrytime'] = date('Y-m-d H:i:s');
        $id = $this->getRepository()->from('weekly_fire_safety_check_list_tbl')->insert($parent_tbl);



        foreach ($data['question'] as $key => $value) {
            $this->insertIntoWeeklyFireSafetyChkQuestionTbl($id, $value);
        }

        $this->uploadImage($id, $data);

        return new JsonResponse(array(
                'id' => $id)
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
        if (!isset($data['images']) || empty($data['images'])) {
            return;
        }

        $photos = FileUtil::uploadFileFromDataUrls(
            realpath(WEB_DIR . "/../mis/images/weekly_safety_chk_photo/"),
            "{$id}_weekly_safety_chk_photo_",
            $data['images']
        );

        $this->getRepository()->from('weekly_fire_safety_check_list_tbl')->update(array(
            'photo' => implode(',', $photos)
        ), array('id=?' => $id));
    }

    protected function insertIntoWeeklyFireSafetyChkQuestionTbl($id, $data)
    {

        $data['weekly_chk_list_id'] = $id;
		if(isset($data['date_of_inspection'])){
        $inspection_dateTime = $data['date_of_inspection'];
        $inspection_date = substr($inspection_dateTime, 0, 10);
        $data['date_of_inspection'] = $inspection_date;
		}
		else
		{
			$data['date_of_inspection'] = "0000-00-00";
		}
        $this->getRepository()->from('weekly_fire_safety_checklist_question_tbl')->insert($data);
    }

    private function SeparatedDateInDatetime($fields, $data)
    {
        isset($data[$fields])?$date =substr($data[$fields] , 0,10):$date ='0000-00-00';
        //$dateTime = isset($data[$fields]);
        //$date =substr($dateTime , 0,10);
        return  $date;

   /*
	if(isset($data[$fields])!=""){
		 $dateTime = $data[$fields];	
		$date =substr($dateTime , 0,10);
		}
		else
		{
			$date = "0000-00-00";
		}
        return  $date;*/
    }

    public function detailsAction(Request $request)
    {
        $detailsFor= json_decode($request->getContent(), true);
        $sql= "SELECT
  weekly_fire_safety_check_list_tbl.id,
  DATE_FORMAT(weekly_fire_safety_check_list_tbl.entrytime,'%d-%m-%Y %h:%i %p')AS entrytime,
  weekly_fire_safety_tbl.`date` AS `date`,
  IF(
    weekly_fire_safety_check_list_tbl.photo = '',
    '0',
    LENGTH(
      weekly_fire_safety_check_list_tbl.photo
    ) - LENGTH(
      REPLACE(
        weekly_fire_safety_check_list_tbl.photo,
        ',',
        ''
      )
    ) + 1
  ) AS photos,
  weekly_fire_safety_check_list_tbl.photo AS images
FROM
  `weekly_fire_safety_check_list_tbl`
  LEFT JOIN `weekly_fire_safety_tbl`
    ON (
      weekly_fire_safety_check_list_tbl.`ref_id` = weekly_fire_safety_tbl.id
    )
WHERE weekly_fire_safety_check_list_tbl.ref_id =?";

        $weekly_fire_safety_details = $this->getRepository()->getResults($sql, array($detailsFor));
        return new JsonResponse( $weekly_fire_safety_details);

    }


}

