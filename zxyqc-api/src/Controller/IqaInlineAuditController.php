<?php

namespace Controller;


use Framework\Controller\AppBaseController;
use Framework\Controller\TokenAuthenticatedController;
use Helper\FileUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class IqaInlineAuditController extends AppBaseController implements TokenAuthenticatedController
{
    public function indexAction($limit)
    {
		$max= 10;
        $min=$limit*$max;
		
        $sql= "SELECT
  quality_assignment_tbl.id AS id,
  ofc_tbl.`ol_name` AS `factory`,
  quality_assignment_tbl.`iqa_inline_due_date` AS `date`,
  CONCAT_WS(
    ' ',
    `contact_info`.`fname`,
    contact_info.`mname`,
    contact_info.`lname`
  ) AS iqa,
     IF(CONCAT_WS(' ', bu_qam_tbl.fname, bu_qam_tbl.mname,bu_qam_tbl.lname)!='',CONCAT_WS(' ',bu_qam_tbl.fname,bu_qam_tbl.mname,
    bu_qam_tbl.lname
  ),'---') AS qam,
  CONCAT_WS(
    ' ',
    `qa_tbl`.`fname`,
    qa_tbl.`mname`,
    qa_tbl.`lname`
  ) AS qa,
  CONCAT_WS(
    ' ',
    `qc_tbl`.`fname`,
    qc_tbl.`mname`,
    qc_tbl.`lname`
  ) AS qc,
  CONCAT_WS(
    ' ',
    `mch_tbl`.`fname`,
    mch_tbl.`mname`,
    mch_tbl.`lname`
  ) AS mch,
  bc_tbl.`ol_name` AS buyer,
  IF(
    style_tbl.`name` != '',
    style_tbl.`name`,
    '---'
  ) AS `style_name`,
  IF(style_tbl.`main_source_ref`!='',style_tbl.`main_source_ref`,'---') AS `style`,
  IF(`quality_assignment_tbl`.`colors`!='',`quality_assignment_tbl`.`colors`,'---') AS colors,
  IF(
    bpo_tbl.`bpo_ref_no` != '',
    bpo_tbl.`bpo_ref_no`,
    '---'
  ) AS po,
  `bpo_tbl`.`zxy_code` AS zxy_code,
  quality_assignment_tbl.`style_quantity` AS po_qty,
  style_tbl.`fabric` AS `fabric`,
  style_tbl.`name` AS `description`,
  aql_tbl.`name` AS aql,
  (SELECT
    COUNT(iqa_inline_audit_p_tbl.id) AS id
  FROM
    `iqa_inline_audit_p_tbl`
  WHERE iqa_inline_audit_p_tbl.ref_id = quality_assignment_tbl.id) AS ref_count
FROM
  quality_assignment_tbl
  LEFT JOIN `contact_info`
    ON quality_assignment_tbl.iqa_inline_assign_to = contact_info.id
  LEFT JOIN `contact_info` AS qa_tbl
    ON quality_assignment_tbl.qa = qa_tbl.id
  LEFT JOIN `contact_info` AS qc_tbl
    ON quality_assignment_tbl.qc = qc_tbl.id
  LEFT JOIN `contact_info` AS mch_tbl
    ON quality_assignment_tbl.mch = mch_tbl.id
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
  LEFT JOIN `option_details` AS aql_tbl
    ON (aql_tbl.`id` = bpo_tbl.aql)
WHERE quality_assignment_tbl.iqa_inline_assign_to =?
GROUP BY quality_assignment_tbl.id order by quality_assignment_tbl.id DESC limit $min,$max ";

        $last_entry="SELECT 
  iqa_inline_audit_p_tbl.`ref_id` AS last_update_id 
FROM
  `iqa_inline_audit_p_tbl` 
WHERE iqa_inline_audit_p_tbl.`entryby` = ? 
ORDER BY iqa_inline_audit_p_tbl.id DESC 
LIMIT 1 ";
        $iqa_last_entry = $this->getRepository()->getResults($last_entry, array($this->getUser()->contact_id));
        $iqa_inline_audit = $this->getRepository()->getResults($sql, array($this->getUser()->contact_id));

        $values=array(
             'list' => $iqa_inline_audit,
             'last_id' => empty($iqa_last_entry) ? null: $iqa_last_entry[0]['last_update_id']
        );
        //return new JsonResponse($this->getUser()->division_id);
        return new JsonResponse($values);
    }

    public function saveAction(Request $request)
    {
        $checkData =$data = json_decode($request->getContent(), true);

        file_put_contents('iqa_error_V2'.date('d-m-Y').'.txt', $request->getContent());
        //return new JsonResponse($data);
        unset(
            $checkData['images'],
            $checkData['fabric'],
            $checkData['sewing'],
            $checkData['print_emb'],
            $checkData['washing'],
            $checkData['styling'],
            $checkData['labeling'],
            $checkData['finishing'],
            $checkData['packing'],
            $checkData['measurement']
        );

        $array = array(
       1=>'fabric',
       2=>'sewing',
       3=>'print_emb',
       4=>'washing',
       5=>'styling',
       6=>'labeling',
       7=>'finishing',
       8=>'packing',
       9=>'measurement',

       );
        $checkData['entryby'] = $this->getUser()->contact_id;
        $checkData['status'] = 2;

        $checkData = array_filter($checkData);

        $whereArray = array();
        foreach($checkData as $key => $value) {
            $whereArray["$key=?"] = $value;
        }

        if($this->getRepository()->from('iqa_inline_audit_p_tbl')->findBy($whereArray)->rowCount()>0) {
            return new JsonResponse(array('error'=>409), 409);
        }

        $checkData['entrytime'] = date('Y-m-d H:i:s');


        $id = $this->getRepository()->from('iqa_inline_audit_p_tbl')->insert($checkData);



            foreach ($array as $type => $values) {
				foreach ($data as $key => $val) {
					if($values==$key)
					{
						$loop=$this->CommonLopping($id,$type,$values, $data);
					   //return new JsonResponse($key);
     
					}
					
				}

            }
			

        $this->uploadImage($id, $data);

       // return new JsonResponse($values);
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
            realpath(WEB_DIR . "/../mis/images/iqa_inline_audit_photo/"),
            "{$id}_iqa_inline_audit_photo_",
            $data['images']
        );

        $this->getRepository()->from('iqa_inline_audit_p_tbl')->update(array(
            'photo' => implode(',', $photos)
        ), array('id=?' => $id));
    }




    private function CommonLopping($id,$type,$values, $data)
    {

         foreach ($data[$values]as $value) {

                $this->insertIntoIqaInlineAuditSubTbl($id, $type, $value);

        }
    }
    private function insertIntoIqaInlineAuditSubTbl($id, $type, $data)
    {
        $data['iqa_inline_ref_id'] = $id;
        $data['comments_type'] = $type;

        $this->getRepository()->from('iqa_inline_audit_sub_tbl')->insert($data);

    }

    public function detailsAction(Request $request)
    {
        $detailsFor= json_decode($request->getContent(), true);
        $sql= "SELECT
  iqa_inline_audit_p_tbl.id,
  DATE_FORMAT(iqa_inline_audit_p_tbl.entrytime,'%d-%m-%Y %h:%i %p')AS entrytime,
  quality_assignment_tbl.iqa_inline_due_date AS `date`,
  IF(
    iqa_inline_audit_p_tbl.photo = '',
    '0',
    LENGTH(
      iqa_inline_audit_p_tbl.photo
    ) - LENGTH(
      REPLACE(
        iqa_inline_audit_p_tbl.photo,
        ',',
        ''
      )
    ) + 1
  ) AS photos,
  iqa_inline_audit_p_tbl.photo AS images
FROM
  `iqa_inline_audit_p_tbl`
  LEFT JOIN quality_assignment_tbl
    ON (
      iqa_inline_audit_p_tbl.`ref_id` = quality_assignment_tbl.id
    )
WHERE iqa_inline_audit_p_tbl.ref_id = ?";

        $inline_inspection_details = $this->getRepository()->getResults($sql, array($detailsFor));
        return new JsonResponse($inline_inspection_details);

    }

}
