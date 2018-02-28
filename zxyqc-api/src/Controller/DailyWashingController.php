<?php

namespace Controller;

use Framework\Controller\AppBaseController;
use Framework\Controller\TokenAuthenticatedController;
use Helper\DateTimeUtil;
use Helper\FileUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class DailyWashingController extends AppBaseController implements TokenAuthenticatedController
{

    public function indexAction($limit)
    {
		$max= 10;
        $min=$limit*$max;
		
        $sql= "SELECT
  quality_assignment_tbl.id AS id,
  bc_tbl.`ol_name` AS customer,
  ofc_tbl.`ol_name` AS `factory`,
  IF(style_tbl.`name`!='',style_tbl.`name`,'---') AS `style_name`,
  IF(style_tbl.`main_source_ref`!='',style_tbl.`main_source_ref`,'---') AS `style`,
  IF(quality_assignment_tbl.`colors`!='',quality_assignment_tbl.`colors`,'---') AS `colors`,
  IF(quality_assignment_tbl.`sizes`!='',quality_assignment_tbl.`sizes`,'---') AS `sizes`,
  bpo_tbl.`bpo_ref_no` AS po,
  `bpo_tbl`.`zxy_code` AS zxy_code,
    quality_assignment_tbl.`style_quantity` AS quantity,
  style_tbl.`fabric` AS `fabric`,
  quality_assignment_tbl.`daily_washing_report_due_date` AS `date`,
 IF(CONCAT_WS(' ', bu_qam_tbl.fname, bu_qam_tbl.mname,bu_qam_tbl.lname)!='',CONCAT_WS(' ',bu_qam_tbl.fname,bu_qam_tbl.mname,
    bu_qam_tbl.lname
  ),'---') AS bu_qam,
  (SELECT
  COUNT(daily_washing_ptbl.id) AS id
FROM
  `daily_washing_ptbl`
   WHERE daily_washing_ptbl.ref_id=  quality_assignment_tbl.id) AS ref_count
FROM
  quality_assignment_tbl
  LEFT JOIN `contact_info`
    ON quality_assignment_tbl.daily_washing_report_assign_to = contact_info.id
  LEFT JOIN company_tbl AS ofc_tbl
    ON ofc_tbl.id = quality_assignment_tbl.select_factory
  LEFT JOIN fpo_tbl
    ON fpo_tbl.`id` = quality_assignment_tbl.`fpoid`
  LEFT JOIN fpo_details
    ON fpo_tbl.id = fpo_details.group_ref
  LEFT JOIN bpo_tbl
    ON (bpo_tbl.id = quality_assignment_tbl.select_po)
  LEFT JOIN style_tbl
    ON style_tbl.`id` = quality_assignment_tbl.`select_style`
  left JOIN company_tbl AS bc_tbl
    ON (bc_tbl.id = fpo_tbl.`buyer`)
  LEFT JOIN `contact_info` AS bu_qam_tbl
    ON (
      bu_qam_tbl.`id` = quality_assignment_tbl.`qam`
    )
WHERE  quality_assignment_tbl.daily_washing_report_assign_to=?
GROUP BY quality_assignment_tbl.id order by quality_assignment_tbl.id desc limit $min,$max";


        $last_entry="SELECT 
  daily_washing_ptbl.`ref_id` AS last_update_id 
FROM
  `daily_washing_ptbl` 
WHERE daily_washing_ptbl.`entryby` = ? 
ORDER BY daily_washing_ptbl.id DESC 
LIMIT 1 ";

        $daily_washing_last_entry = $this->getRepository()->getResults($last_entry, array($this->getUser()->contact_id));
        $daily_washing = $this->getRepository()->getResults($sql, array($this->getUser()->contact_id));

        $values=array(
            'list' => $daily_washing,
            'last_id' => empty($daily_washing_last_entry) ? null: $daily_washing_last_entry[0]['last_update_id']
        );
        return new JsonResponse($values);
    }

    public function saveAction(Request $request) {

        $data = json_decode($request->getContent(), true);
        file_put_contents('dwash_V2'.date('d-m-Y').'.txt', $request->getContent());
       // return new JsonResponse($data);

        $parent_tbl=array(
            'status' => 2,
            'ref_id' => $data['ref_id'],
            'washing_plant' => $data['washing_plant'],
            'qc_name' => $data['qc_name'],
            'comments' => $data['comments'],
            'ratio_wash' => $data['ratio_wash'],
            'measurment_comments' => $data['measurment_comments'],
            'rectify_details' => $data['rectify_details'],
            'loading_f_up' => $data['loading_f_up'],
            'loading_f_down' => $data['loading_f_down'],
            'desize_f_up' => $data['desize_f_up'],
            'desize_f_down' => $data['desize_f_down'],
            'dyeing_f_up' => $data['dyeing_f_up'],
            'dyeing_f_down' => $data['dyeing_f_down'],
            'fixing_f_up' => $data['fixing_f_up'],
            'fixing_f_down' => $data['fixing_f_down'],
            'rinse_f_up' => $data['rinse_f_up'],
            'rinse_f_down' => $data['rinse_f_down'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'map_address' => $data['map_address']

        );

        $parent_tbl['entryby'] = $this->getUser()->contact_id;

        $parent_tbl = array_filter($parent_tbl);

        $whereArray = array();
        foreach($parent_tbl as $key => $value) {
            $whereArray["$key=?"] = $value;
        }

        if($this->getRepository()->from('daily_washing_ptbl')->findBy($whereArray)->rowCount()>0) {
            return new JsonResponse(array('error'=>409), 409);
        }
        $parent_tbl['entrytime'] = date('Y-m-d H:i:s');
        $id = $this->getRepository()->from('daily_washing_ptbl')->insert($parent_tbl);



        foreach($data['washing'] as $washing)
        {
            $washing['washing_ref_id'] = $id;
            $washing['s_time'] = DateTimeUtil::parseTime($washing['s_time']);
            $washing['qcsig_time'] =DateTimeUtil::parseTime($washing['qcsig_time']);
            $washing['entryby'] = $this->getUser()->contact_id;
            $washing['entrytime'] = date('Y-m-d H:i:s');

            $this->getRepository()->from('daily_washing_batch_ctbl')->insert($washing);

        }
        foreach($data['reject_ratio'] as $reject_ratio)
        {
            $reject_ratio['washing_ref_id'] = $id;
            $reject_ratio['entryby'] = $this->getUser()->contact_id;
            $reject_ratio['entrytime'] = date('Y-m-d H:i:s');

            $this->getRepository()->from('daily_washing_ratio_ctbl')->insert($reject_ratio);

        }


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
            realpath(WEB_DIR . "/../mis/images/daily_washing_photo/"),
            "{$id}_daily_washing_photo_",
            $data['images']
        );

        $this->getRepository()->from('daily_washing_ptbl')->update(array(
            'photo' => implode(',', $photos)
        ), array('id=?' => $id));
    }

    public function detailsAction(Request $request)
    {
        $detailsFor= json_decode($request->getContent(), true);
        $sql= "SELECT
  daily_washing_ptbl.id,
  DATE_FORMAT(daily_washing_ptbl.entrytime,'%d-%m-%Y %h:%i %p')AS entrytime,
  quality_assignment_tbl.`daily_washing_report_due_date` AS `date`,
  IF(
    daily_washing_ptbl.photo = '',
    '0',
    LENGTH(
      daily_washing_ptbl.photo
    ) - LENGTH(
      REPLACE(
        daily_washing_ptbl.photo,
        ',',
        ''
      )
    ) + 1
  ) AS photos,
  daily_washing_ptbl.photo AS images
FROM
  `daily_washing_ptbl`
  LEFT JOIN quality_assignment_tbl
    ON (
      daily_washing_ptbl.`ref_id` = quality_assignment_tbl.id
    )
WHERE daily_washing_ptbl.ref_id = ?";

        $daily_washing_details = $this->getRepository()->getResults($sql, array($detailsFor));
        return new JsonResponse($daily_washing_details);

    }

}