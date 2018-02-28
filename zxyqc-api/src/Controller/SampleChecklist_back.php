<?php

namespace Controller;

use Framework\Controller\AppBaseController;
use Framework\Controller\TokenAuthenticatedController;
use Helper\FileUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class SampleChecklist extends AppBaseController implements TokenAuthenticatedController
{
    public function indexAction($limit)
    {
		$max= 10;
        $min=$limit*$max;
		
        $sql= "SELECT
  quality_assignment_tbl.id AS id,
  bc_tbl.`ol_name` AS buyer,
  division_tbl.`name`AS bu,
  ofc_tbl.`ol_name` AS `factory`,
  IF(style_tbl.`name`!='',style_tbl.`name`,'---') AS `style_name`,
  IF(style_tbl.`main_source_ref`!='',style_tbl.`main_source_ref`,'---') AS `style`,
  IF(quality_assignment_tbl.`colors`!='',quality_assignment_tbl.`colors`,'---' ) AS colors,
  IF(quality_assignment_tbl.`sizes`!='',quality_assignment_tbl.`sizes`,'---') AS `sizes`,
  IF(brnad_tbl.`name`!='',brnad_tbl.`name`,'---' ) AS brand,
  bpo_tbl.`bpo_ref_no` AS po,
  `bpo_tbl`.`zxy_code` AS zxy_code,
  style_tbl.`fabric` AS `fabric`,
  quality_assignment_tbl.`sample_check_list_due_date` AS `date`,
   CONCAT_WS(
    ' ',
    bu_qam_tbl.fname,
    bu_qam_tbl.mname,
    bu_qam_tbl.lname
  ) AS bu_qam,
     CONCAT_WS(
    ' ',
    bu_mch_tbl.fname,
    bu_mch_tbl.mname,
    bu_mch_tbl.lname
  ) AS merchandiser,
  (SELECT
  COUNT(sample_check_list_tbl.id) AS id
FROM
  sample_check_list_tbl
   WHERE ref_id=  quality_assignment_tbl.id) AS ref_count
FROM
  quality_assignment_tbl

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
    LEFT JOIN `brand`
    ON `brand`.`company_id`=`bpo_tbl`.`pqf_ref`
      LEFT JOIN `option_details` AS brnad_tbl
    ON `brnad_tbl`.`id`=`brand`.`brand`
  LEFT JOIN company_tbl AS bc_tbl
    ON (bc_tbl.id = fpo_tbl.`buyer`)
  LEFT JOIN `contact_info` AS bu_qam_tbl
    ON (
      bu_qam_tbl.`id` = quality_assignment_tbl.`qam`
    )
    LEFT JOIN `contact_info` AS bu_mch_tbl
    ON (
      bu_mch_tbl.`id` = quality_assignment_tbl.`mch`
    )
     LEFT JOIN `division_tbl`
    ON (
      division_tbl.`id` = quality_assignment_tbl.`select_bu`
    )
WHERE  quality_assignment_tbl.sample_check_list_assign_to=?
GROUP BY id order by id DESC limit $min,$max ";

        $last_entry="SELECT 
  `sample_check_list_tbl`.`ref_id` AS last_update_id 
FROM
  `sample_check_list_tbl` 
WHERE sample_check_list_tbl.`entryby` = ? 
ORDER BY sample_check_list_tbl.id DESC 
LIMIT 1  ";

        $sample_check_last_entry = $this->getRepository()->getResults($last_entry, array($this->getUser()->contact_id));
        $sample_check = $this->getRepository()->getResults($sql, array($this->getUser()->contact_id));

        $values=array(
            'list' => $sample_check,
            'last_id' => empty($sample_check_last_entry) ? null: $sample_check_last_entry[0]['last_update_id']
        );
        return new JsonResponse($values);
    }

    public function saveAction(Request $request) {
        $checkData = $data = json_decode($request->getContent(), true);
        
        file_put_contents('sampleChkV2.txt', $request->getContent());

        unset($checkData['images']);

        $checkData['entryby'] = $this->getUser()->contact_id;

        $checkData = array_filter($checkData);


        $whereArray = array();
        foreach($checkData as $key => $value) {
            $whereArray["$key=?"] = $value;
        }

        if($this->getRepository()->from('sample_check_list_tbl')->findBy($whereArray)->rowCount()>0) {
            return new JsonResponse(array('error'=>409), 409);
        }

        $checkData['entrytime'] = date('Y-m-d H:i:s');

        $id = $this->getRepository()->from('sample_check_list_tbl')->insert($checkData);

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
            realpath(WEB_DIR . "/../mis/images/sample_check/"),
            "{$id}_sample_check_list_photo_",
            $data['images']
        );

        $this->getRepository()->from('sample_check_list_tbl')->update(array(
            'photo' => implode(',', $photos)
        ), array('id=?' => $id));
    }

    public function detailsAction(Request $request)
    {
        $detailsFor= json_decode($request->getContent(), true);
        $sql= "SELECT
  sample_check_list_tbl.id,
  DATE_FORMAT(sample_check_list_tbl.entrytime,'%d-%m-%Y %h:%i %p')AS entrytime,
  quality_assignment_tbl.`sample_check_list_due_date` AS `date`,
  IF(
    sample_check_list_tbl.photo = '',
    '0',
    LENGTH(
      sample_check_list_tbl.photo
    ) - LENGTH(
      REPLACE(
        sample_check_list_tbl.photo,
        ',',
        ''
      )
    ) + 1
  ) AS photos,
  sample_check_list_tbl.photo AS images
FROM
  `sample_check_list_tbl`
  LEFT JOIN quality_assignment_tbl
    ON (
      sample_check_list_tbl.`ref_id` = quality_assignment_tbl.id
    )
WHERE sample_check_list_tbl.ref_id =?";

        $sample_check_list_details = $this->getRepository()->getResults($sql, array($detailsFor));
        return new JsonResponse($sample_check_list_details);

    }

}