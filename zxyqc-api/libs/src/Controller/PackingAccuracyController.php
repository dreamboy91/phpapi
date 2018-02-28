<?php

namespace Controller;

use Framework\Controller\AppBaseController;
use Framework\Controller\TokenAuthenticatedController;
use Helper\FileUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class PackingAccuracyController extends AppBaseController implements TokenAuthenticatedController
{

    public function indexAction($limit)
    {
		$max= 10;
        $min=$limit*$max;
		
        $sql= "SELECT
  quality_assignment_tbl.id AS id,
  bc_tbl.`ol_name` AS customer,
  ofc_tbl.`ol_name` AS `factory`,
      quality_assignment_tbl.`packing_accuracy_due_date` AS `date`,
  IF(
    style_tbl.`name` != '',
    style_tbl.`name`,
    '---'
  ) AS `style_name`,
   IF(style_tbl.`main_source_ref`!='',style_tbl.`main_source_ref`,'---') AS `style`,

  bpo_tbl.`bpo_ref_no` AS po,
  `bpo_tbl`.`zxy_code` AS zxy_code,
   quality_assignment_tbl.`style_quantity` AS quantity,
  IF(
    style_tbl.`fabric` != '',
    style_tbl.`fabric`,
    '---'
  ) AS `fabric`,
 IF(quality_assignment_tbl.`colors`!='',quality_assignment_tbl.`colors`,'---' ) AS colors,
  IF(quality_assignment_tbl.`sizes`!='',quality_assignment_tbl.`sizes`,'---') AS `sizes`,
  IF(
    option_brand_name.name != '',
    option_brand_name.name,
    '---'
  ) AS brand,
     IF(CONCAT_WS(' ', bu_qam_tbl.fname, bu_qam_tbl.mname,bu_qam_tbl.lname)!='',CONCAT_WS(' ',bu_qam_tbl.fname,bu_qam_tbl.mname,
    bu_qam_tbl.lname
  ),'---') AS bu_qam,
  (SELECT
  COUNT(packing_accuracy_ptbl.id) AS id
FROM
  `packing_accuracy_ptbl`
   WHERE packing_accuracy_ptbl.ref_id=  quality_assignment_tbl.id) AS ref_count
FROM
  quality_assignment_tbl
  LEFT JOIN `contact_info`
    ON quality_assignment_tbl.packing_accuracy_assign_to = contact_info.id
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
  LEFT JOIN brand AS brand_tbl
    ON (
      bc_tbl.id = brand_tbl.`company_id`
    )
  LEFT JOIN `option_details` AS option_brand_name
    ON (
      option_brand_name.id = brand_tbl.`brand`
    )
  LEFT JOIN `contact_info` AS bu_qam_tbl
    ON (
      bu_qam_tbl.`id` = quality_assignment_tbl.`qam`
    )
WHERE quality_assignment_tbl.packing_accuracy_assign_to =?
GROUP BY quality_assignment_tbl.id order by quality_assignment_tbl.id desc limit $min,$max  ";

        $last_entry="SELECT 
  `packing_accuracy_ptbl`.`ref_id` AS last_update_id 
FROM
  `packing_accuracy_ptbl` 
WHERE packing_accuracy_ptbl.`entryby` = ? 
ORDER BY packing_accuracy_ptbl.id DESC 
LIMIT 1  ";

        $packing_accuracy_last_entry = $this->getRepository()->getResults($last_entry, array($this->getUser()->contact_id));
        $packing_accuracy = $this->getRepository()->getResults($sql, array($this->getUser()->contact_id));

        $values=array(
            'list' => $packing_accuracy,
            'last_id' => empty($packing_accuracy_last_entry) ? null: $packing_accuracy_last_entry[0]['last_update_id']
        );
        return new JsonResponse($values);
    }

    public function saveAction(Request $request) {
        $data = json_decode($request->getContent(),true);
        file_put_contents('packingV2.txt', $request->getContent());
        //return new JsonResponse($data);
      $parent_tbl = array(
          'status' => 2,
          'ref_id' => '',
          'order_hangtag_position' => '',
          'order_qty_per_carton' => '',
          'order_sell_price' => '',
          'comments' => '',
          'latitude' => '',
          'longitude' => '',
          'map_address' => '',
          'carton_dimension' => '',
          'ratio' => ''

      );
      foreach ($parent_tbl as $key => $_d) {
        if (!isset($data[$key])) {
          continue;
        }
        $parent_tbl[$key] = $data[$key];
      }
      $parent_tbl['entryby'] = $this->getUser()->contact_id;
      $parent_tbl = array_filter($parent_tbl);

            $whereArray = array();
            foreach ($parent_tbl as $key => $value) {
              $whereArray["$key=?"] = $value;
            }

            if ($this->getRepository()->from('packing_accuracy_ptbl')->findBy($whereArray)->rowCount() > 0) {
              return new JsonResponse(array('error' => 409), 409);
            }

      $parent_tbl['entrytime'] = date('Y-m-d H:i:s');
      $id = $this->getRepository()->from('packing_accuracy_ptbl')->insert($parent_tbl);

      foreach ($data['carton_marking'] as $key => $value) {
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
        realpath(WEB_DIR . "/../mis/images/packing_accuracy_photo/"),
        "{$id}_packing_accuracy_photo_",
        $data['images']
    );

    $this->getRepository()->from('packing_accuracy_ptbl')->update(array(
        'photo' => implode(',', $photos)
    ), array('id=?' => $id));
  }

  protected function insertIntoSizeMeasurementInspectionDescTbl($id, $data)
  {

    $data['packing_accuracy_ptbl_id'] = $id;
    $this->getRepository()->from('packing_accuracy_child_tbl')->insert($data);
  }

    public function detailsAction(Request $request)
    {
        $detailsFor= json_decode($request->getContent(), true);
        $sql= "SELECT
  packing_accuracy_ptbl.id,
  DATE_FORMAT(packing_accuracy_ptbl.entrytime,'%d-%m-%Y %h:%i %p')AS entrytime,
  quality_assignment_tbl.`daily_washing_report_due_date` AS `date`,
  IF(
    packing_accuracy_ptbl.photo = '',
    '0',
    LENGTH(
      packing_accuracy_ptbl.photo
    ) - LENGTH(
      REPLACE(
        packing_accuracy_ptbl.photo,
        ',',
        ''
      )
    ) + 1
  ) AS photos,
  packing_accuracy_ptbl.photo AS images
FROM
  `packing_accuracy_ptbl`
  LEFT JOIN quality_assignment_tbl
    ON (
      packing_accuracy_ptbl.`ref_id` = quality_assignment_tbl.id
    )
WHERE packing_accuracy_ptbl.ref_id = ?";

        $packing_accuracy_details = $this->getRepository()->getResults($sql, array($detailsFor));
        return new JsonResponse($packing_accuracy_details );

    }
}