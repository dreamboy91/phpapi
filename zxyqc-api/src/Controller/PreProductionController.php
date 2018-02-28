<?php

namespace Controller;

use Framework\Controller\AppBaseController;
use Framework\Controller\TokenAuthenticatedController;
use Helper\DateTimeUtil;
use Helper\FileUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class PreProductionController extends AppBaseController implements TokenAuthenticatedController
{

    public function indexAction($limit)
    {
		$max= 10;
        $min=$limit*$max;
		
        $sql= "SELECT
  quality_assignment_tbl.id AS id,

  IF(bc_tbl.`ol_name` != '',bc_tbl.`ol_name`,'---') AS customer,
  ofc_tbl.`ol_name` AS `factory`,
  IF(style_tbl.`name`!='',style_tbl.`name`,'---') AS `style_name`,
  IF(style_tbl.`main_source_ref`!='',style_tbl.`main_source_ref`,'---') AS `style`,
  IF(quality_assignment_tbl.`colors`!='',quality_assignment_tbl.`colors`,'---' ) AS colors,
  bpo_tbl.`bpo_ref_no` AS po,
  `bpo_tbl`.`zxy_code` AS zxy_code,
   quality_assignment_tbl.`style_quantity` AS order_qty,
  style_tbl.`fabric` AS `fabric`,
  quality_assignment_tbl.`pre_production_meeting_due_date` AS `date`,
 
   IF(CONCAT_WS(' ', bu_qam_tbl.fname, bu_qam_tbl.mname,bu_qam_tbl.lname)!='',CONCAT_WS(' ',bu_qam_tbl.fname,bu_qam_tbl.mname,
    bu_qam_tbl.lname
  ),'---') AS bu_qam,
  (SELECT
  COUNT(pre_production_tbl.id) AS id
FROM
  `pre_production_tbl`
   WHERE pre_production_tbl.ref_id=  quality_assignment_tbl.id) AS ref_count

FROM
  quality_assignment_tbl
  LEFT JOIN `contact_info`
    ON quality_assignment_tbl.`pre_production_meeting_assign_to` = contact_info.id
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
WHERE  quality_assignment_tbl.pre_production_meeting_assign_to=?
GROUP BY quality_assignment_tbl.id  order by quality_assignment_tbl.id desc limit $min,$max";

        $last_entry="SELECT 
  `pre_production_tbl`.`ref_id` AS last_update_id 
FROM
  `pre_production_tbl` 
WHERE pre_production_tbl.`entryby` = ? 
ORDER BY pre_production_tbl.id DESC 
LIMIT 1  ";

        $pre_production_meeting_last_entry = $this->getRepository()->getResults($last_entry, array($this->getUser()->contact_id));
        $pre_production_meeting = $this->getRepository()->getResults($sql, array($this->getUser()->contact_id));

        $values=array(
            'list' => $pre_production_meeting,
            'last_id' => empty($pre_production_meeting_last_entry) ? null: $pre_production_meeting_last_entry[0]['last_update_id']
        );
        return new JsonResponse($values);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function saveAction(Request $request) {
        $checkData = $data = json_decode($request->getContent(), true);
        file_put_contents('pperrorV2.txt', $request->getContent());

        unset(
            $checkData['images'],
            $checkData['recorded'],
            $checkData['meeting_zxy'],
            $checkData['meeting_factory'],
            $checkData['workmanship'],
            $checkData['measurement'],
            $checkData['fit_appearance_ironing'],
            $checkData['embroidery_print'],
            $checkData['process']
        );
        $array = array(
            1=>'workmanship',
            2=>'measurement',
            3=>'fit_appearance_ironing',
            4=>'embroidery_print'
        );

        if (isset($checkData['pps_approval_date'])!=null)
        {
            $ppsdateTime = $checkData['pps_approval_date'];
            $ppsdate =substr($ppsdateTime , 0,10);
            $checkData['pps_approval_date']=$ppsdate;
        }
        else
        {
            $checkData['pps_approval_date']='0000-00-00';
        }


        if (isset($checkData['tpack_approval_date'])!=null)
        {
            $tpackdatetime = $checkData['tpack_approval_date'];
            $tpackdate = substr($tpackdatetime , 0,10);
            $checkData['tpack_approval_date']=$tpackdate;
        }
        else
        {
            $checkData['tpack_approval_date']='0000-00-00';
        }

        if (isset($checkData['revised_sample_date'])!=null)
        {
            $reviseddateTime = $checkData['revised_sample_date'];
            $revisddate =substr($reviseddateTime , 0,10);
            $checkData['revised_sample_date']=$revisddate;
        }
        else
        {
            $checkData['revised_sample_date']='0000-00-00';
        }

        $checkData['entryby'] = $this->getUser()->contact_id;


        if(isset($checkData['special_machine']))
        {
            $checkData['special_machine']=$this->valuesWithComma($checkData['special_machine']);
        }
        if(isset($checkData['critical_operation_taken']))
        {
            $checkData['critical_operation_taken']=$this->valuesWithComma($checkData['critical_operation_taken']);
        }
        if(isset($checkData['quality_points']))
        {
            $checkData['quality_points']=$this->valuesWithComma($checkData['quality_points']);
        }
        if(isset($checkData['measuring_points']))
        {
            $checkData['measuring_points']=$this->valuesWithComma($checkData['measuring_points']);
        }
        if(isset($checkData['mid_line_check']))
        {
            $checkData['mid_line_check']=$this->valuesWithComma($checkData['mid_line_check']);
        }
        if(isset($checkData['check_points']))
        {
            $checkData['check_points']=$this->valuesWithComma($checkData['check_points']);
        }

        /*
                $checkData['special_machine']=$this->valuesWithComma($checkData['special_machine']);
                $checkData['critical_operation_taken']=$this->valuesWithComma($checkData['critical_operation_taken']);
                $checkData['quality_points']=$this->valuesWithComma($checkData['quality_points']);
                $checkData['measuring_points']=$this->valuesWithComma($checkData['measuring_points']);
                $checkData['mid_line_check']=$this->valuesWithComma($checkData['mid_line_check']);
                $checkData['check_points']=$thi>valuesWithComma($checkData['check_points']);

                $checkData['special_machine']=$special_machine;
                $checkData['critical_operation_taken']=$critical_operation_taken;
                $checkData['quality_points']=$quality_points;
                $checkData['measuring_points']=$measuring_points;
                $checkData['mid_line_check']=$mid_line_check;
                $checkData['check_points']=$check_points;
                $checkData = array_filter($checkData);
        */
        $whereArray = array();
        foreach($checkData as $key => $value) {
            $whereArray["$key=?"] = $value;
        }

        if($this->getRepository()->from('pre_production_tbl')->findBy($whereArray)->rowCount()>0) {
            return new JsonResponse(array('error'=>409), 409);
        }
        $checkData['entrytime'] = date('Y-m-d H:i:s');
        $checkData['status'] = 2;
        $id = $this->getRepository()->from('pre_production_tbl')->insert($checkData);

        if(isset($data['meeting_zxy'])) {
            foreach ($data['meeting_zxy'] as $meeting_zxy) {
                $this->insertIntoPreProductionPersonTbl($id, $meeting_zxy, 'zxy');
            }
        }

        if(isset($data['meeting_factory'])) {
            foreach ($data['meeting_factory'] as $meeting_factory) {
                $this->insertIntoPreProductionPersonTbl($id, $meeting_factory, 'factory');
            }
        }

        if(isset($data['process'])) {
            foreach ($data['process'] as $key => $value) {
                $this->insertIntoPreProductionProcessTbl($id, $value);
            }
        }

        foreach ($array as $type => $values)
        {
            foreach ($data as $key => $val)
            {
                if($values==$key)
                {
                    $loop=$this->CommonLopping($id,$type,$values, $data);
                    //return new JsonResponse($key);

                }

            }

        }

        $this->uploadImage($id, $data);
        $this->uploadRecording($id, $data);
        return new JsonResponse(array(
                'id'=>$id
                //,'val' => $checkData,
                //'val2' => $data
            )
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
            realpath(WEB_DIR . "/../mis/images/pre_production_photo/"),
            "{$id}_pre_production_photo_",
            $data['images']
        );

        $this->getRepository()->from('pre_production_tbl')->update(array(
            'photo' => implode(',', $photos)
        ), array('id=?' => $id));
    }

    public function uploadRecording($id, $data)
    {
        if(!isset($data['recorded']) || empty($data['recorded'])) {
            return;
        }

        $audios = FileUtil::uploadFileFromDataUrls(
            realpath(WEB_DIR . "/../mis/audio/pre_production_meeting_audio/"),
            "{$id}_pre_production_meeting_audio",
            array($data['recorded'])
        );
//return new JsonResponse($id);
        $this->getRepository()->from('pre_production_tbl')->update(array(
            'recorded' => implode(',', $audios)
        ), array('id=?' => $id));
    }

    protected function insertIntoPreProductionPersonTbl($id, $data,$from)
    {
        if($from=='zxy')
        {
            $from=1;
        }
        elseif($from=='factory')
        {
            $from=2;
        }
        else{
            $from=0;
        }

        $data['pre_ref_id'] = $id;
        $data['froms'] = $from;
        $this->getRepository()->from('pre_production_person_tbl')->insert($data);
    }
    protected function insertIntoPreProductionProcessTbl($id, $data)
    {

        $data['pre_ref_id'] = $id;
        $start_dateTime = $data['start_date'];
        $start_date =substr($start_dateTime , 0,10);
        $data['start_date']=$start_date;
        $end_dateTime = $data['end_date'];
        $end_date =substr($end_dateTime , 0,10);
        $data['end_date']=$end_date;

        $this->getRepository()->from('pre_production_process_tbl')->insert($data);
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

    private function CommonLopping($id,$type,$values, $data)
    {

        foreach ($data[$values]as $value) {

            $this->insertIntoPreProductionSubTbl($id, $type, $value);

        }
    }
    private function insertIntoPreProductionSubTbl($id, $type, $data)
    {
        $data['pre_ref_id'] = $id;
        $data['comments_type'] = $type;
        $this->getRepository()->from('pre_production_sub_tbl')->insert($data);


    }

    public function detailsAction(Request $request)
    {
        $detailsFor= json_decode($request->getContent(), true);
        $sql= "SELECT
  pre_production_tbl.id,
  DATE_FORMAT(pre_production_tbl.entrytime,'%d-%m-%Y %h:%i %p')AS entrytime,
  quality_assignment_tbl.`daily_washing_report_due_date` AS `date`,
  pre_production_tbl.recorded,
  IF(
    pre_production_tbl.photo = '',
    '0',
    LENGTH(
      pre_production_tbl.photo
    ) - LENGTH(
      REPLACE(
        pre_production_tbl.photo,
        ',',
        ''
      )
    ) + 1
  ) AS photos,
  pre_production_tbl.photo AS images
FROM
  `pre_production_tbl`
  LEFT JOIN quality_assignment_tbl
    ON (
      pre_production_tbl.`ref_id` = quality_assignment_tbl.id
    )
WHERE pre_production_tbl.ref_id = ?";

        $pre_production_details = $this->getRepository()->getResults($sql, array($detailsFor));
        return new JsonResponse($pre_production_details);

    }


}