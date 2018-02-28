<?php

namespace Controller;

use Constant\FinalInspectionType;
use Framework\Controller\AppBaseController;
use Framework\Controller\TokenAuthenticatedController;
use Helper\FileUtil;
use Helper\DateTimeUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
class FinalInspectionController extends AppBaseController implements TokenAuthenticatedController
{

    public function indexAction($limit)
    {
		$max= 10;
        $min=$limit*$max;
        $sql= "SELECT
  quality_assignment_tbl.id AS id,
  bc_tbl.`ol_name` AS customer,
  ofc_tbl.`ol_name` AS `factory`,
  if(style_tbl.`name`!='',style_tbl.`name`,'---') AS `style_name`,
  IF(style_tbl.`main_source_ref`!='',style_tbl.`main_source_ref`,'---') AS `style`,
  IF(quality_assignment_tbl.`colors`!='',quality_assignment_tbl.`colors`,'---') AS `colors`,
  IF(quality_assignment_tbl.`sizes`!='',quality_assignment_tbl.`sizes`,'---') AS `sizes`,
  bpo_tbl.`bpo_ref_no` AS po,
  `bpo_tbl`.`zxy_code` AS zxy_code,
    quality_assignment_tbl.style_quantity AS quantity,
  style_tbl.`fabric` AS `fabric`,
  IF (style_tbl.`name`!='',style_tbl.`name`,'---') AS `description`,
  quality_assignment_tbl.`final_inspection_due_date` AS `date`,
  bu_qam_tbl.userid AS bu_qam,
 (SELECT
  COUNT(final_inspection_tbl.id) AS id
FROM
  `final_inspection_tbl`
   WHERE ref_id=  quality_assignment_tbl.id) AS ref_count,
   IF(
  partial_ship_tbl.partial_ship_qty != 0,
  (SELECT 
    SUM(
      final_inspection_tbl.`ship_qty`
    ) AS shipped_qty 
  FROM
    `final_inspection_tbl` 
  WHERE final_inspection_tbl.ref_id = quality_assignment_tbl.id 
    AND final_inspection_tbl.`partial_ship_qty` = 1),
  0
) AS shipped_qty,
IF(
  partial_ship_tbl.partial_ship_qty != 0,
  (SELECT 
    SUM(
      final_inspection_tbl.`pack_qty`
    ) AS pack_qty 
  FROM
    `final_inspection_tbl` 
  WHERE final_inspection_tbl.ref_id = quality_assignment_tbl.id 
    AND final_inspection_tbl.`partial_ship_qty` = 1),
  0
) AS pack_qty 

FROM
  quality_assignment_tbl
  LEFT JOIN `contact_info`
    ON quality_assignment_tbl.`final_inspection_assign_to` = contact_info.id
  LEFT JOIN company_tbl AS ofc_tbl
    ON ofc_tbl.id = quality_assignment_tbl.select_factory
  LEFT JOIN fpo_tbl
    ON fpo_tbl.`id` = quality_assignment_tbl.`fpoid`
  LEFT JOIN fpo_details
    ON fpo_tbl.id = fpo_details.group_ref
  LEFT JOIN bpo_tbl
    ON (bpo_tbl.id = quality_assignment_tbl.`select_po`)
  LEFT JOIN style_tbl
    ON style_tbl.`id` = quality_assignment_tbl.`select_style`
  left JOIN company_tbl AS bc_tbl
    ON (bc_tbl.id = fpo_tbl.`buyer`)
  LEFT JOIN `system_user` AS bu_qam_tbl
    ON (
      bu_qam_tbl.`contact_id` = quality_assignment_tbl.`entryby`
    )
    LEFT JOIN `final_inspection_tbl` AS partial_ship_tbl 
    ON (
      partial_ship_tbl.ref_id = quality_assignment_tbl.`id`
    )
WHERE  quality_assignment_tbl.final_inspection_assign_to=?
GROUP BY quality_assignment_tbl.id  order by quality_assignment_tbl.id desc limit $min,$max";

        $last_entry="SELECT 
  `final_inspection_tbl`.`ref_id` AS last_update_id 
FROM
  `final_inspection_tbl` 
WHERE final_inspection_tbl.`entryby` = ? 
ORDER BY final_inspection_tbl.id DESC 
LIMIT 1  ";

        $final_inspection_last_entry = $this->getRepository()->getResults($last_entry, array($this->getUser()->contact_id));
        $final_inspection = $this->getRepository()->getResults($sql, array($this->getUser()->contact_id));

        $values=array(
            'list' => $final_inspection,
            'last_id' => empty($final_inspection_last_entry) ? null: $final_inspection_last_entry[0]['last_update_id']
        );
        return new JsonResponse($values);
    }

    public function saveAction(Request $request) {
         $checkData =$data= json_decode($request->getContent(), true);
       file_put_contents('final_erV2dd.txt', $request->getContent());
        // return new JsonResponse($checkData);

        unset($checkData['latitude'],$checkData['longitude'],$checkData['inspection_start_time'],$checkData['inspection_finish_time'],$checkData['images'],$checkData['other_parameter'],$checkData['carton']
            ,$checkData['fabric'],$checkData['construction_stitching'],$checkData['embellishments']
            ,$checkData['wash'],$checkData['cleanliness'],$checkData['packaging'],$checkData['accessories_checklist']);
        $array = array(
            'other_parameter'=>'other_parameter','carton'=>'carton',
            'fabric'=>'fabric','construction_stitching'=>'construction_stitching','embellishments'=>'embellishments',
            'wash'=>'wash','cleanliness'=>'cleanliness','packaging'=>'packaging'
        );
        $checkData['entryby'] = $this->getUser()->contact_id;
        $checkData['status'] = 2;


        $checkData = array_filter($checkData);

        $whereArray = array();
        foreach($checkData as $key => $value) {
            $whereArray["$key=?"] = $value;
        }

        if($this->getRepository()->from('final_inspection_tbl')->findBy($whereArray)->rowCount()>0) {
            return new JsonResponse(array('error'=>409), 409);
        }

        $checkData['entrytime'] = date('Y-m-d H:i:s');

        if(isset($data['accessories_checklist']))
        {
            $accessories_checklist = $this->valuesWithComma($data['accessories_checklist']);
            $checkData['accessories_checklist'] =$accessories_checklist;
        }
        if(isset($data['inspection_start_time'])!='' )
        {
            $checkData['inspection_start_time'] =DateTimeUtil::parseTime($data['inspection_start_time']);

        }
        else{
            $checkData['inspection_start_time'] ='00:00:00';
        }
        if(isset($data['inspection_finish_time'])!='')
        {

            $checkData['inspection_finish_time'] =DateTimeUtil::parseTime($data['inspection_finish_time']);
        }
        else{
            $checkData['inspection_finish_time'] ='00:00:00';
        }



        //return new JsonResponse($checkData['accessories_checklist']);

        $checkData['latitude'] = $data['latitude'];
        $checkData['longitude'] = $data['longitude'];
        
        $id = $this->getRepository()->from('final_inspection_tbl')->insert($checkData);

        foreach ($array as $lvl => $values)
        {
            foreach ($data as $key => $val)
            {
                if($values==$key)
                {
                    $loop=$this->CommonLopping($id,$values, $data);
                    //return new JsonResponse($key);

                }

            }

        }

        $this->uploadImage($id, $data);

        return new JsonResponse(array(
                'id' => $id
            //,'data' => $checkData
            )
        );

    }
    public function uploadImage($id, $data)
    {
        if (!isset($data['images']) || empty($data['images'])) {
            return;
        }
        $photos = FileUtil::uploadFileFromDataUrls(
            realpath(WEB_DIR . "/../mis/images/final_inspection_photo/"),
            "{$id}_final_inspection_photo_",
            $data['images']
        );

        $this->getRepository()->from('final_inspection_tbl')->update(array(
            'photo' => implode(',', $photos)
        ), array('id=?' => $id));
    }
    private function CommonLopping($id, $values,$data)
    {
        $i=0;
        $type='';
        if($values=='other_parameter')
        {
            $type=FinalInspectionType::OTHER_PARAMETER;
        }
        elseif($values=='carton')
        {
            $type=FinalInspectionType::CARTON;
        }
        elseif($values=='carton_check')
        {
            $type=FinalInspectionType::CARTON_CHECK;
        }

        elseif($values=='fabric')
        {
            $type=FinalInspectionType::FABRIC;
        }
        elseif($values=='construction_stitching')
        {
            $type=FinalInspectionType::CONSTRUCTION;
        }
        elseif($values=='embellishments')
        {
            $type=FinalInspectionType::EMBELLISHMENTS;
        }
        elseif($values=='wash')
        {
            $type=FinalInspectionType::WASH;
        }
        elseif($values=='cleanliness')
        {
            $type=FinalInspectionType::CLEANLINESS;
        }
        elseif($values=='packaging')
        {
            $type=FinalInspectionType::PACKAGING;
        }
        foreach ($data[$values] as $key => $value) {

            $i++;



            foreach ($value as $lbl => $val) {
                $this->insertIntoFinalInspectionChildTbl($id, $lbl, $val, $i, $type);

            }

        }
    }
    private function insertIntoFinalInspectionChildTbl($id, $lbl, $val, $i, $type)
    {
        $tbl=array(
            'finall_ins_id' => $id,
            'type' => $type,
            'label' => $lbl,
            'value' => $val,
            'col' => $i
        );
        $this->getRepository()->from('final_inspection_child_tbl')->insert($tbl);

    }

    protected function valuesWithComma($data)
    {
        $value='';
        $i=0;
        foreach($data as  $val) {

            $withoutCommaval= str_replace(",","[&m^j&]",$val);
            if($i==0)
            {
                $i++;
                $value.=$withoutCommaval;
            }
            else
            {
                $value .=','.$withoutCommaval;
            }
        }
        return $value;
    }

    public function detailsAction(Request $request)
    {
        $detailsFor= json_decode($request->getContent(), true);
        $sql= "SELECT
  final_inspection_tbl.id,
  DATE_FORMAT(final_inspection_tbl.entrytime,'%d-%m-%Y %h:%i %p')AS entrytime,
  quality_assignment_tbl.`final_inspection_due_date` AS `date`,
  IF(
    final_inspection_tbl.photo = '',
    '0',
    LENGTH(
      final_inspection_tbl.photo
    ) - LENGTH(
      REPLACE(
        final_inspection_tbl.photo,
        ',',
        ''
      )
    ) + 1
  ) AS photos,
  final_inspection_tbl.photo AS images
FROM
  `final_inspection_tbl`
  LEFT JOIN quality_assignment_tbl
    ON (
      final_inspection_tbl.`ref_id` = quality_assignment_tbl.id
    )
WHERE final_inspection_tbl.ref_id =?";

        $final_inspection_details = $this->getRepository()->getResults($sql, array($detailsFor));
        return new JsonResponse($final_inspection_details);

    }

}