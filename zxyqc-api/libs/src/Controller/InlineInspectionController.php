<?php

namespace Controller;

use Constant\CommentsType;
use Framework\Controller\AppBaseController;
use Framework\Controller\TokenAuthenticatedController;
use Helper\FileUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class InlineInspectionController extends AppBaseController implements TokenAuthenticatedController
{
    public function indexAction($limit)
    {
		$max= 10;
        $min=$limit*$max;

        $conn = $this->getRepository();
        $conn->query("SET group_concat_max_len = 1000000");

        $user_id = $this->getUser()->contact_id;
        $arr2 = $this->getDesignation($user_id);

		
        $sql= "SELECT
  quality_assignment_tbl.id AS id,
  CONCAT_WS(
    ' ',
    `contact_info`.`fname`,
    contact_info.`mname`,
    contact_info.`lname`
  ) AS inline_inspection_assign_to,
  quality_assignment_tbl.`select_style`,
  quality_assignment_tbl.`colors`,
  quality_assignment_tbl.`select_po`,
  quality_assignment_tbl.`fpoid`,
  bc_tbl.`ol_name` AS buyer,
  ofc_tbl.`ol_name` AS `factory`,
  if(style_tbl.`name`!='',style_tbl.`name`,'---') AS `style_name`,
   IF(style_tbl.`main_source_ref`!='',style_tbl.`main_source_ref`,'---') AS `style`,
  if(bpo_tbl.`bpo_ref_no`!='',bpo_tbl.`bpo_ref_no`,'---') AS po,
  `bpo_tbl`.`zxy_code` AS zxy_code,
    quality_assignment_tbl.`style_quantity` AS quantity,
  style_tbl.`fabric` AS `fabric`,
  quality_assignment_tbl.`inline_inspection_due_date` AS `date`,
 
   IF(CONCAT_WS(' ', bu_qam_tbl.fname, bu_qam_tbl.mname,bu_qam_tbl.lname)!='',CONCAT_WS(' ',bu_qam_tbl.fname,bu_qam_tbl.mname,
    bu_qam_tbl.lname
  ),'---') AS bu_qam,
  (SELECT
  COUNT(inline_inspection_tbl.id) AS id
FROM
  `inline_inspection_tbl`
   WHERE inline_inspection_tbl.ref_id=  quality_assignment_tbl.id) AS ref_count
FROM
  quality_assignment_tbl
  LEFT JOIN `contact_info`
    ON quality_assignment_tbl.inline_inspection_assign_to = contact_info.id
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
  LEFT JOIN `contact_info` AS bu_qam_tbl
    ON (
      bu_qam_tbl.`id` = quality_assignment_tbl.`qam`
    )
  WHERE  quality_assignment_tbl.inline_inspection_assign_to In ($arr2)
GROUP BY id order by id desc limit $min,$max";

        $last_entry="SELECT 
  `inline_inspection_tbl`.`ref_id` AS last_update_id 
FROM
  `inline_inspection_tbl` 
WHERE inline_inspection_tbl.`entryby` = ? 
ORDER BY inline_inspection_tbl.id DESC 
LIMIT 1  ";

        $inline_inspection_last_entry = $this->getRepository()->getResults($last_entry, array($this->getUser()->contact_id));
        $inline_inspection = $this->getRepository()->getResults($sql);

        $style_id = array();
        $bpo_id = array();


        foreach ($inline_inspection as $key=>$value) {
            $style_id[] = $value['select_style'];
            $bpo_id[] = $value['select_po'];
        }

        $styleids = implode(",",$style_id);
        $bpo_id = implode(",",$bpo_id);
        if(!empty($style_id)){
            $sql3 = "SELECT  q.field_ref  AS bpo_id,`q`.`style_id` AS style_id, `q`.`color` AS colors,  q.quantity AS color_quantity 
                         FROM  `quantity_rate_tbl` AS q WHERE `q`.flag = 0   AND q.`style_id` IN ($styleids)  AND q.`field_ref` IN ($bpo_id)
                         AND q.`quantity` != 0 ";

            $colors_qty = $this->getRepository()->getResults($sql3);
         }



        $color = array();
        $color2 = array();


        foreach ($inline_inspection as $keys=>$val) {
            $color_d = array();
            $color_ar = explode(",",$val['colors']);

            foreach($color_ar as $ar ){

                $qty = 0;
               
            foreach ($colors_qty as $k => $cr) {

                if($val['select_style']==$cr['style_id'] && $val['select_po']==$cr['bpo_id']) {

                    if($ar==$cr['colors']){
                        $qty = $qty+$cr['color_quantity'];
                        $color["color"]=$cr['colors'];
                    }



                }

            }
                $color["color_qty"]=$qty;
                $color_d[] = $color;
            }

            $val['colors']=$color_d;
            unset($val['select_style'],$val['select_po']);

            $color2[] = $val;


        }

        $values=array(
            'list' => $color2,
            'last_id' => empty($inline_inspection_last_entry) ? null: $inline_inspection_last_entry[0]['last_update_id']
        );
        return new JsonResponse($values);
    }

    public function saveAction(Request $request) {
        $checkData =$data = json_decode($request->getContent(), true);

        file_put_contents('inline'.date('d-m-Y').'.txt', $request->getContent());
//        return new JsonResponse($data);
        unset(
            $checkData['images'],
            $checkData['fabricDefect'],
            $checkData['cuttingDefect'],
            $checkData['sewingDefect'],
            $checkData['labellingOrTrimDefect'],
            $checkData['packingProblem'],
            $checkData['stylingDefects'],
            $checkData['otherIssues'],
            $checkData['finishingDefect'],
            $checkData['quantityCut'],
            $checkData['quantityStitched'],
            $checkData['quantityEmbroidery'],
            $checkData['quantityFinished'],
            $checkData['quantityPacked'],
            $checkData['quantityChecked']
        );

        $fabric_array = array(
            'fabricDefect' =>'fabricDefect',
            'cuttingDefect'=>'cuttingDefect',
            'sewingDefect'=>'sewingDefect',
            'labellingOrTrimDefect'=>'labellingOrTrimDefect',
            'finishingDefect'=>'finishingDefect',
            'packingProblem'=>'packingProblem',
            'stylingDefects'=>'stylingDefects',
            'otherIssues'=> 'otherIssues'
        );

        $array = array(
            1=>'fabricDefect',
            2=>'cuttingDefect',
            3=>'sewingDefect',
            4=>'labellingOrTrimDefect',
            5=>'finishingDefect',
            6=>'packingProblem',
            7=>'stylingDefects',
            8=>'otherIssues'

        );


        $checkData['entryby'] = $this->getUser()->contact_id;
        $checkData['status'] = 2;

        $checkData = array_filter($checkData);

        $whereArray = array();
        foreach($checkData as $key => $value) {
            $whereArray["$key=?"] = $value;
        }

        if($this->getRepository()->from('inline_inspection_tbl')->findBy($whereArray)->rowCount()>0) {
            return new JsonResponse(array('error'=>409), 409);
        }

        $checkData['entrytime'] = date('Y-m-d H:i:s');

        $json_array['qty']["quantityCut"] = $data['quantityCut'];
        $json_array['qty']["quantityStitched"] = $data['quantityStitched'];
        $json_array['qty']["quantityEmbroidery"] = $data['quantityEmbroidery'];
        $json_array['qty']["quantityFinished"] = $data['quantityFinished'];
        $json_array['qty']["quantityPacked"] = $data['quantityPacked'];
        $json_array['qty']["quantityChecked"] = $data['quantityChecked'];

        $checkData['qty'] = json_encode($json_array);

        $id = $this->getRepository()->from('inline_inspection_tbl')->insert($checkData);




      //  return new JsonResponse(array('da'=>$data,'de'=>$deffect_array));

        foreach ($array as $type => $values2)
        {
            foreach ($data as $key2 => $val2)
            {

                if($values2==$key2)
                {
                    $loop=$this->CommonLopping($id,$type,$values2, $data);
                    $images=$this->insertIntoInlineImagesTbl($id,$type,$values2,$data);

                }

            }

        }


//        return new JsonResponse(array('da'=>$data,'de'=>$deffect_array));
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
    public function uploadImage($ids, $values2, $data)
    {
        if(isset($data[$values2]['images'])) {
            if(! $data[$values2]['images'] || empty($data[$values2]['images'])) {
                return;
            }

            $photos = FileUtil::uploadFileFromDataUrls(
                realpath(WEB_DIR . "/../mis/images/inline_inspection_photo/"),
                "{$ids}_inline_inspection_photo_",
                $data[$values2]['images']
            );

            $this->getRepository()->from('inline_images_tbl')->update(array(
                'photo' => implode(',', $photos)
            ), array('id=?' => $ids));

        }

    }

    private function CommonLopping($id,$type,$values, $data)
    {
//        return new JsonResponse($data[$values]["defects"]);
        if(isset($data[$values]["defects"])) {
            foreach ($data[$values]["defects"] as $value) {

                $this->insertIntoInlineSubTbl($id,$type,$value);

            }
        }


    }
    private function insertIntoInlineSubTbl($id, $type, $value)
    {
        $deffect_array['inline_ref_id'] = $id;
        $deffect_array['comments_type'] = $type;
        $deffect_array['major'] = isset($value['major'])?$value['major']:'';
        $deffect_array['minor'] = isset($value['minor'])?$value['minor']:'';
        $deffect_array['comments'] = isset($value['defectName'])?$value['defectName']:'';
        $deffect_array['responsible'] = isset($value['responsible'])?$value['responsible']:'';
        $deffect_array['remarks'] = isset($value['remarks'])?$value['remarks']:'';
        $deffect_array['entryby'] = $this->getUser()->contact_id;
        $deffect_array['entrytime'] = date('Y-m-d H:i:s');
        $this->getRepository()->from('inline_sub_tbl')->insert($deffect_array);



    }

    private function insertIntoInlineImagesTbl($id, $type,$values2,$data)
    {
        $image_array['ref_id'] = $id;
        $image_array['comments_type'] = $type;
        $image_array['entryby'] = $this->getUser()->contact_id;
        $image_array['entrytime'] = date('Y-m-d H:i:s');
        $ids = $this->getRepository()->from('inline_images_tbl')->insert($image_array);

        $this->uploadImage($ids, $values2,$data);

    }
    public function detailsAction(Request $request)
    {
        $detailsFor= json_decode($request->getContent(), true);
        $sql= "SELECT
          inline_inspection_tbl.id,
          DATE_FORMAT(inline_inspection_tbl.entrytime,'%d-%m-%Y %h:%i %p')AS entrytime,
          quality_assignment_tbl.`inline_inspection_due_date` AS `date`,
          IF(
            inline_inspection_tbl.photo = '',
            '0',
            LENGTH(
              inline_inspection_tbl.photo
            ) - LENGTH(
              REPLACE(
                inline_inspection_tbl.photo,
                ',',
                ''
              )
            ) + 1
          ) AS photos,
          inline_inspection_tbl.photo AS images
        FROM
          `inline_inspection_tbl`
          LEFT JOIN quality_assignment_tbl
            ON (
              inline_inspection_tbl.`ref_id` = quality_assignment_tbl.id
            )
        WHERE inline_inspection_tbl.ref_id = ?";

        $inline_inspection_details = $this->getRepository()->getResults($sql, array($detailsFor));
        return new JsonResponse($inline_inspection_details);

    }
    public function photoSaveAction(Request $request){

        $data = json_decode($request->getContent(), true);
        file_put_contents('inline-photo.txt', $request->getContent());
        $id = $data['ref_id'];
        $res =  $this->uploadInlineInspectionImage($id,$data);
        return new JsonResponse(array(
                'id' => $res
            )
        );
    }


    /**
     * @param $id
     * @param $data
     * @return string
     * @throws \Exception
     */
    public function uploadInlineInspectionImage($id, $data)
    {
        if(!isset($data['uploadImageData']) || empty($data['uploadImageData'])) {
            return;
        }

        $sql = "SELECT photo,ref_id FROM inline_inspection_tbl WHERE inline_inspection_tbl.id=$id";
        $result = $this->getRepository()->getSingleResults($sql);
        $array = explode(',',$result['photo']);
        if($result['photo']!='') {
            $index = count($array)>0?count($array):0;
        }
       else
       {
           $index = 0;
       }

        $photos = FileUtil::uploadFileFromDataUrls(
            realpath(WEB_DIR . "/../mis/images/inline_inspection_photo/"),
            "{$id}_inline_inspection_photo_",
            $data['uploadImageData'],
            $index
        );



        if($result['photo']!='')
        {
            $upload = '';
            $i=0;
            foreach ($photos as $photo)
            {
                if($i==0)
                {
                    $upload.=$photo;
                    $i=$i+1;
                }
                else
                {
                    $upload.=','.$photo;
                }
            }

            $image = $result['photo'].','.$upload;
        }
        else
        {
            $image = implode(',',$photos);
        }



        $this->getRepository()->from('inline_inspection_tbl')->update(array(
            'photo' =>$image
        ), array('id=?' => $id));

        return $result['ref_id'];
    }

    public function getDesignation($id) {



        $sql2 ="SELECT 
                  hr_tbl.id,
                  hr_tbl.contact_id,
                  designation_tbl.id AS designation_id,
                  designation_tbl.name AS designation_name
                FROM
                  hr_tbl 
                  INNER JOIN option_details AS designation_tbl 
                    ON hr_tbl.designation = designation_tbl.id 
                  LEFT JOIN department_tbl 
                    ON hr_tbl.department = department_tbl.id 
                WHERE department_tbl.name = 'Quality' 
                AND (designation_tbl.name !='Technician - Sample')
                 AND (designation_tbl.name != 'Technician - Quality')
                GROUP BY designation_id";
        $designation= $this->getRepository()->getResults($sql2);

        $sql2 ="SELECT                   
                  hr_tbl.designation AS designation_id,
                  hr_tbl.dept AS dept,
                  hr_tbl.department AS department
                  
                FROM
                  hr_tbl 
                where hr_tbl.contact_id =$id";
        $desig= $this->getRepository()->getResults($sql2);

        foreach ($desig  as $v) {
            $id2 = $v['designation_id'];
            $dept = $v['dept'];
            $department = $v['department'];
        }

        if($department==47 || $department==48) {

            $sql3 ="Select  GROUP_CONCAT(hr_tbl.contact_id) as id from hr_tbl where hr_tbl.designation in(237,642,2097,659,9268,9348)";
            $array_contact_id = $this->getRepository()->getResults($sql3);

            foreach ($array_contact_id as $k1) {
                if($k1['id']!=''){
                    $contact_id = $k1['id'].','.$id;
                }
                else
                {
                    $contact_id =$id;
                }


            }
        }

        else {
            $array = array();
            switch ($id2) {

                case 237: //qc
                    $contact_id=$id;

                    break ;
                case 642: //qa
                    $sql3 ="Select  GROUP_CONCAT(hr_tbl.contact_id) as id from hr_tbl where hr_tbl.designation in(237) and  hr_tbl.dept=$dept";
                    $array_contact_id = $this->getRepository()->getResults($sql3);

                    foreach ($array_contact_id as $k1) {
                        if($k1['id']!=''){
                            $contact_id = $k1['id'].','.$id;
                        }
                        else
                        {
                            $contact_id =$id;
                        }
                    }

                    break ;
                case 2097: // qa

                    $sql3 ="Select  GROUP_CONCAT(hr_tbl.contact_id) as id from hr_tbl where hr_tbl.designation in(237,642) and  hr_tbl.dept=$dept";
                    $array_contact_id = $this->getRepository()->getResults($sql3);

                    foreach ($array_contact_id as $k1) {
                        if($k1['id']!=''){
                            $contact_id = $k1['id'].','.$id;
                        }
                        else
                        {
                            $contact_id =$id;
                        }
                    }
                    break ;
                case 659:


                    $sql3 ="Select  GROUP_CONCAT(hr_tbl.contact_id) as id from hr_tbl where hr_tbl.designation in(237,642,2097) and  hr_tbl.dept=$dept";
                    $array_contact_id = $this->getRepository()->getResults($sql3);

                    foreach ($array_contact_id as $k1) {
                        if($k1['id']!=''){
                            $contact_id = $k1['id'].','.$id;
                        }
                        else
                        {
                            $contact_id =$id;
                        }


                    }

                    break ;
                case 9268:

                    $sql3 ="Select  GROUP_CONCAT(hr_tbl.contact_id) as id from hr_tbl where hr_tbl.designation in(237,642,2097,659) and  hr_tbl.dept=$dept";
                    $array_contact_id = $this->getRepository()->getResults($sql3);

                    foreach ($array_contact_id as $k1) {
                        if($k1['id']!=''){
                            $contact_id = $k1['id'].','.$id;
                        }
                        else
                        {
                            $contact_id =$id;
                        }


                    }

                    break ;
                case 9348:

                    $sql3 ="Select  GROUP_CONCAT(hr_tbl.contact_id) as id from hr_tbl where hr_tbl.designation in(237,642,2097,659,9268) and  hr_tbl.dept=$dept";
                    $array_contact_id = $this->getRepository()->getResults($sql3);

                    foreach ($array_contact_id as $k1) {
                        if($k1['id']!=''){
                            $contact_id = $k1['id'].','.$id;
                        }
                        else
                        {
                            $contact_id =$id;
                        }


                    }
                    break ;


            }

        }
        return $contact_id;

    }


}
