<?php

namespace Controller;

use Framework\Controller\AppBaseController;
use Framework\Controller\TokenAuthenticatedController;
use Helper\FileUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class SizeMeasurementController extends AppBaseController implements TokenAuthenticatedController
{

    public function indexAction($limit)
    {
		$max= 10;
        $min=$limit*$max;
		
        $sql= "SELECT
  quality_assignment_tbl.id AS id,
  division_tbl.`name`AS bu,
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
  quality_assignment_tbl.`size_measurment_inspection_due_date` AS `date`,
  bu_qam_tbl.userid AS bu_qam,
  (SELECT
  COUNT(size_measurement_inspection_ptbl.id) AS id
FROM
  size_measurement_inspection_ptbl
   WHERE size_measurement_inspection_ptbl.ref_id=  quality_assignment_tbl.id) AS ref_count
FROM
  quality_assignment_tbl
  LEFT JOIN `contact_info`
    ON quality_assignment_tbl.`size_measurment_inspection_assign_to` = contact_info.id
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
  LEFT JOIN `system_user` AS bu_qam_tbl
    ON (
      bu_qam_tbl.`contact_id` = quality_assignment_tbl.`entryby`
    )
    LEFT JOIN `division_tbl`
    ON (
      division_tbl.`id` = quality_assignment_tbl.`select_bu`
    )
WHERE  quality_assignment_tbl.size_measurment_inspection_assign_to=?
GROUP BY quality_assignment_tbl.id order by quality_assignment_tbl.id desc limit $min,$max ";

        $last_entry="SELECT 
  `size_measurement_inspection_Ptbl`.`ref_id` AS last_update_id 
FROM
  `size_measurement_inspection_Ptbl` 
WHERE size_measurement_inspection_Ptbl.`entryby` = ? 
ORDER BY size_measurement_inspection_Ptbl.id DESC 
LIMIT 1  ";

        $size_measurment_inspection_last_entry = $this->getRepository()->getResults($last_entry, array($this->getUser()->contact_id));
        $size_measurment_inspection = $this->getRepository()->getResults($sql, array($this->getUser()->contact_id));

        $values=array(
            'list' => $size_measurment_inspection,
            'last_id' => empty($size_measurment_inspection_last_entry) ? null: $size_measurment_inspection_last_entry[0]['last_update_id']
        );
        return new JsonResponse($values);
    }

    public function saveAction(Request $request) {
        $data = json_decode($request->getContent(), true);
     //file_put_contents('size.txt', $request->getContent());
    //return new JsonResponse($data);
      $parent_tbl = array(
          'status' => 2,
          'ref_id' => '',
          'stage' => 0,
          'comments' => '',
          'description' => '',
          'latitude' => '',
          'longitude' => '',
          'note' => ''

          );
      foreach ($parent_tbl as $key => $_d) {
        if (!isset($data[$key])) {
          continue;
        }
        $parent_tbl[$key] = $data[$key];
      }
      $parent_tbl['entryby'] = $this->getUser()->contact_id;
      $parent_tbl = array_filter($parent_tbl);
/*
      $whereArray = array();
      foreach ($parent_tbl as $key => $value) {
        $whereArray["$key=?"] = $value;
      }

      if ($this->getRepository()->from('size_measurement_inspection_Ptbl')->findBy($whereArray)->rowCount() > 0) {
        return new JsonResponse(array('error' => 409), 409);
      }
*/
      $parent_tbl['entrytime'] = date('Y-m-d H:i:s');
      $id = $this->getRepository()->from('size_measurement_inspection_Ptbl')->insert($parent_tbl);

      foreach ($data['size_measurement'] as $key => $value) {
        $this->insertIntoSizeMeasurementInspectionDescTbl($id, $value);
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
        realpath(WEB_DIR . "/../mis/images/size_msrmnt_insp_photo/"),
        "{$id}_size_msrmnt_insp_photo_",
        $data['images']
    );

    $this->getRepository()->from('size_measurement_inspection_Ptbl')->update(array(
        'photo' => implode(',', $photos)
    ), array('id=?' => $id));
  }

  protected function insertIntoSizeMeasurementInspectionDescTbl($id, $data)
  {

    $data['size_msrmnt_Pid'] = $id;
    $this->getRepository()->from('size_measurement_inspection_desc_tbl')->insert($data);
  }

    public function detailsAction(Request $request)
    {
        $detailsFor= json_decode($request->getContent(), true);
        $sql= "SELECT
  size_measurement_inspection_ptbl.id,
  DATE_FORMAT(size_measurement_inspection_ptbl.entrytime,'%d-%m-%Y %h:%i %p')AS entrytime,
  quality_assignment_tbl.`size_measurment_inspection_due_date` AS `date`,
  IF(
    size_measurement_inspection_ptbl.photo = '',
    '0',
    LENGTH(
      size_measurement_inspection_ptbl.photo
    ) - LENGTH(
      REPLACE(
        size_measurement_inspection_ptbl.photo,
        ',',
        ''
      )
    ) + 1
  ) AS photos,
  size_measurement_inspection_ptbl.photo AS images
FROM
  `size_measurement_inspection_ptbl`
  LEFT JOIN quality_assignment_tbl
    ON (
      size_measurement_inspection_ptbl.`ref_id` = quality_assignment_tbl.id
    )
WHERE size_measurement_inspection_ptbl.ref_id =?";

        $size_measurment_inspection_details = $this->getRepository()->getResults($sql, array($detailsFor));
       /*
	    return new JsonResponse(array(
            'details_list' => $size_measurment_inspection_details
        ));
*/
 return new JsonResponse( $size_measurment_inspection_details);
    }

}


