<?php

namespace Controller;

use Constant\SampleReviewType;
use Framework\Controller\AppBaseController;
use Framework\Controller\TokenAuthenticatedController;
use Helper\FileUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class SampleReviewController extends AppBaseController implements TokenAuthenticatedController
{

    public function indexAction($limit)
    {
		$max= 10;
        $min=$limit*$max;
		
        $sql= "SELECT
  quality_assignment_tbl.id AS id,

  IF(bc_tbl.`ol_name`!='',bc_tbl.`ol_name`,'---') AS customer,
  ofc_tbl.`ol_name` AS `factory`,
  IF(style_tbl.`name`!='',style_tbl.`name`,'---') AS `style_name`,
    IF(quality_assignment_tbl.`colors`!='',quality_assignment_tbl.`colors`,'---') AS `colors`,
   IF(style_tbl.`main_source_ref`!='',style_tbl.`main_source_ref`,'---') AS `style`,
  bpo_tbl.`bpo_ref_no` AS po,
  `bpo_tbl`.`zxy_code` AS zxy_code,
  quality_assignment_tbl.`style_quantity` AS quantity,
  division_tbl.`name` AS `bu`,
  hr_tbl.`preffered_name` AS `qa`,

  quality_assignment_tbl.`simple_review_due_date` AS `date`,
   IF(CONCAT_WS(' ', bu_qam_tbl.fname, bu_qam_tbl.mname,bu_qam_tbl.lname)!='',CONCAT_WS(' ',bu_qam_tbl.fname,bu_qam_tbl.mname,
    bu_qam_tbl.lname
  ),'---') AS qam,
  
  (SELECT
  COUNT(sample_review_ptbl.id) AS id
FROM
  `sample_review_ptbl`
   WHERE sample_review_ptbl.ref_id=  quality_assignment_tbl.id) AS ref_count

FROM
  quality_assignment_tbl
  LEFT JOIN `hr_tbl`
    ON quality_assignment_tbl.`simple_review_assign_to` = hr_tbl.`contact_id`
    LEFT JOIN division_tbl
    ON division_tbl.`id` = quality_assignment_tbl.`select_bu`
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
  LEFT JOIN company_tbl AS bc_tbl
    ON (bc_tbl.id = fpo_tbl.`buyer`)
  LEFT JOIN `contact_info` AS bu_qam_tbl
    ON (
      bu_qam_tbl.`id` = quality_assignment_tbl.`qam`
    )

WHERE  quality_assignment_tbl.simple_review_assign_to=?
GROUP BY quality_assignment_tbl.id order by quality_assignment_tbl.id desc limit $min,$max";

        $last_entry="SELECT 
  `sample_review_ptbl`.`ref_id` AS last_update_id 
FROM
  `sample_review_ptbl` 
WHERE sample_review_ptbl.`entryby` = ? 
ORDER BY sample_review_ptbl.id DESC 
LIMIT 1  ";

        $simple_review_last_entry = $this->getRepository()->getResults($last_entry, array($this->getUser()->contact_id));
        $simple_review = $data =$this->getRepository()->getResults($sql, array($this->getUser()->contact_id));

        $values=array(
            'list' => $simple_review,
            'last_id' => empty($simple_review_last_entry) ? null: $simple_review_last_entry[0]['last_update_id']
        );
        return new JsonResponse($values);
    }

  public function saveAction(Request $request) {
    $checkData = $data = json_decode($request->getContent(), true);
       file_put_contents('smpl_rev.txt', $request->getContent());
      //return new JsonResponse($data);

    unset(
        $checkData['images'],
        $checkData['fabric_quality'],
        $checkData['prinny'],
        $checkData['washing_dry'],
        $checkData['other_applications'],
        $checkData['pattern'],
        $checkData['iron'],
        $checkData['comments_Bulk'],
        $checkData['amendements']
    );
    $checkData['entryby'] = $this->getUser()->contact_id;
    $checkData['status'] = 2;
    $DateTimeArray=array(
        0=>'p_cut_date',
        1=> 'p_start_date',
        2=> 'delivery_date',
        3=> 'pps_date',
        4=>'techp_date',
        5=> 'po_date',
        6=>'comment_date'
    );
    foreach($DateTimeArray as $fields)
    {
      $date=$this->SeparatedDateInDatetime($fields, $checkData);
     $checkData["$fields"] = $date;
    }

    $checkData = array_filter($checkData);

    $whereArray = array();
    foreach($checkData as $key => $value) {
      $whereArray["$key=?"] = $value;
    }

    if($this->getRepository()->from('sample_review_ptbl')->findBy($whereArray)->rowCount()>0) {
      return new JsonResponse(array('error'=>409), 409);
    }

    $checkData['entrytime'] = date('Y-m-d H:i:s');


    $id = $this->getRepository()->from('sample_review_ptbl')->insert($checkData);

    $array = array(
        'fabric_quality'=>'fabric_quality',
        'prinny'=>'prinny',
        'washing_dry'=>'washing_dry',
        'other_applications'=>'other_applications',
        'pattern'=>'pattern',
        'construction'=>'construction',
        'iron'=>'iron',
        'packing'=>'packing',
        'comments_Bulk'=>'comments_Bulk',
        'comments_hereby'=>'comments_hereby',
        'comments'=>'comments',
        'amendements'=>'amendements'
    );

    if(array_intersect_key($array, $data)==true) {

      foreach ($array as $lvl => $values) {

        $this->CommonLopping($id,$values, $data);

      }
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
        if(!isset($data['images']) || empty($data['images'])) {
            return;
        }

        $photos = FileUtil::uploadFileFromDataUrls(
            realpath(WEB_DIR . "/../mis/images/sample_review_photo/"),
            "{$id}_sample_review_photo_",
            $data['images']
        );

        $this->getRepository()->from('sample_review_ptbl')->update(array(
            'photo' => implode(',', $photos)
        ), array('id=?' => $id));
    }


    private function CommonLopping($id, $values,$data)
  {
    $i=0;
    $type='';

    if($values=='comments  ')
    {
      $type=SampleReviewType::COMMENTS;
    }
    elseif($values=='fabric_quality')
    {
      $type=SampleReviewType::FABRIC_QUALITY;
    }
    elseif($values=='prinny')
    {
      $type=SampleReviewType::PRINNY;
    }
    elseif($values=='washing_dry')
    {
      $type=SampleReviewType::WASHING_DRY;
    }
    elseif($values=='other_applications')
    {
      $type=SampleReviewType::OTHER_APPLICATIONS;
    }
    elseif($values=='pattern')
    {
      $type=SampleReviewType::PATTERN;
    }

    elseif($values=='iron')
    {
      $type=SampleReviewType::IRON;
    }

    elseif($values=='comments_Bulk')
    {
      $type=SampleReviewType::COMMENTS_BULK;
    }

    elseif($values=='amendements')
    {
        $type=SampleReviewType::AMENDEMENTS;
    }


    foreach ($data[$values] as $key => $value) {

      $i++;
      foreach ($value as $lbl => $val) {

        $this->InsertIntoSampleReviewChildTbl($id, $lbl, $val, $i, $type);

      }

    }
  }
  private function InsertIntoSampleReviewChildTbl($id, $lbl, $val, $i, $type)
  {
    $tbl=array(
        'sample_review_ptbl_id' => $id,
        'type' => $type,
        'label' => $lbl,
        'value' => $val,
        'col' => $i
    );
    $this->getRepository()->from('sample_review_child_tbl')->insert($tbl);

  }

  private function SeparatedDateInDatetime($fields, $checkData)
  {
    $dateTime = $checkData[$fields];
    $date =substr($dateTime , 0,10);
    return  $date;

  }

    public function detailsAction(Request $request)
    {
        $detailsFor= json_decode($request->getContent(), true);
        $sql= "SELECT
  sample_review_ptbl.id,
  DATE_FORMAT(sample_review_ptbl.entrytime,'%d-%m-%Y %h:%i %p')AS entrytime,
  quality_assignment_tbl.`daily_washing_report_due_date` AS `date`,
  IF(
    sample_review_ptbl.photo = '',
    '0',
    LENGTH(
      sample_review_ptbl.photo
    ) - LENGTH(
      REPLACE(
        sample_review_ptbl.photo,
        ',',
        ''
      )
    ) + 1
  ) AS photos,
  sample_review_ptbl.photo AS images
FROM
  `sample_review_ptbl`
  LEFT JOIN quality_assignment_tbl
    ON (
      sample_review_ptbl.`ref_id` = quality_assignment_tbl.id
    )
WHERE sample_review_ptbl.ref_id = ?";

        $simple_review_details = $this->getRepository()->getResults($sql, array($detailsFor));
        return new JsonResponse($simple_review_details);

    }
}