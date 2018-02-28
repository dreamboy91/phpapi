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
		
        $sql= "SELECT
  quality_assignment_tbl.id AS id,
  CONCAT_WS(
    ' ',
    `contact_info`.`fname`,
    contact_info.`mname`,
    contact_info.`lname`
  ) AS inline_inspection_assign_to,
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
  WHERE  quality_assignment_tbl.inline_inspection_assign_to=?
GROUP BY id order by id desc limit $min,$max";

        $last_entry="SELECT 
  `inline_inspection_tbl`.`ref_id` AS last_update_id 
FROM
  `inline_inspection_tbl` 
WHERE inline_inspection_tbl.`entryby` = ? 
ORDER BY inline_inspection_tbl.id DESC 
LIMIT 1  ";

        $inline_inspection_last_entry = $this->getRepository()->getResults($last_entry, array($this->getUser()->contact_id));
        $inline_inspection = $this->getRepository()->getResults($sql, array($this->getUser()->contact_id));

        $values=array(
            'list' => $inline_inspection,
            'last_id' => empty($inline_inspection_last_entry) ? null: $inline_inspection_last_entry[0]['last_update_id']
        );
        return new JsonResponse($values);
    }

    public function saveAction(Request $request) {
        $checkData =$data = json_decode($request->getContent(), true);

        file_put_contents('inline'.date('d-m-Y').'.txt', $request->getContent());
       // return new JsonResponse($data);
        unset(
            $checkData['images'],
            $checkData['fabric_defects'],
            $checkData['cutting_defects'],
            $checkData['sewing_defects'],
            $checkData['labeling_trim_defects'],
            $checkData['finishing_defects'],
            $checkData['packing_problem'],
            $checkData['styling_defects'],
            $checkData['other_issues']
        );

//        $array = array(
//            1=>'fabric',
//            2=>'cutting',
//            3=>'sewing',
//            4=>'labeling',
//            5=>'finishing',
//            6=>'packing',
//            7=>'styling',
//            8=>'other'
//
//        );

        $array = array(
            1=>'febric',
            2=>'cutting',
            3=>'sewing',
            4=>'labeling',
            5=>'finishing',
            6=>'packing',
            7=>'styling',
            8=>'other'

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


        $id = $this->getRepository()->from('inline_inspection_tbl')->insert($checkData);

        $data_array =  array();

        $deffect_array = array();

            if( $data["fabric_defects"]['fabric_slub_knot']==1)
            {
                $data_array["comments"] = "Slub/Knot";
                $data_array["major"] = $data["fabric_defects"]['fabric_slub_major'];
                $data_array["minor"] = $data["fabric_defects"]['fabric_slub_minor'];
                $data_array["corrective_action"] = $data["fabric_defects"]['fabric_slub_corrective_action'];
                $data_array["responsible"] = $data["fabric_defects"]['fabric_slub_responsible'];
                $deffect_array["febric"][] = $data_array;
            }
            if( $data["fabric_defects"]['fabric_barre']==1)
            {
                
                $data_array["comments"] = "Barre";
                $data_array["major"] = $data["fabric_defects"]['fabric_barre_major'];
                $data_array["minor"] = $data["fabric_defects"]['fabric_barre_minor'];
                $data_array["corrective_action"] = $data["fabric_defects"]['fabric_barre_corrective_action'];
                $data_array["responsible"] = $data["fabric_defects"]['fabric_barre_responsible'];
                $deffect_array["febric"][] = $data_array;
            }



            if( $data["fabric_defects"]['fabric_hole_loose_knit']==1)
           {
               $data_array["comments"] = "Hole/ Loose Knit";
               $data_array["major"] = $data["fabric_defects"]['fabric_hole_major'];
               $data_array["minor"] = $data["fabric_defects"]['fabric_hole_minor'];
               $data_array["corrective_action"] = $data["fabric_defects"]['fabric_hole_corrective_action'];
               $data_array["responsible"] = $data["fabric_defects"]['fabric_hole_responsible'];
               $deffect_array["febric"][] = $data_array;
           }


            if( $data["fabric_defects"]['fabric_run']==1)
           {
               $data_array["comments"] = "Fabric Run";
               $data_array["major"] = $data["fabric_defects"]['fabric_run_major'];
               $data_array["minor"] = $data["fabric_defects"]['fabric_run_minor'];
               $data_array["corrective_action"] = $data["fabric_defects"]['fabric_run_corrective_action'];
               $data_array["responsible"] = $data["fabric_defects"]['fabric_run_responsible'];
               $deffect_array["febric"][] = $data_array;

           }

            if( $data["fabric_defects"]['fabric_yarn_contamination']==1)
           {
               $data_array["comments"] = "Yarn Contamination";
               $data_array["major"] = $data["fabric_defects"]['fabric_yarn_major'];
               $data_array["minor"] = $data["fabric_defects"]['fabric_yarn_minor'];
               $data_array["corrective_action"] = $data["fabric_defects"]['fabric_yarn_corrective_action'];
               $data_array["responsible"] = $data["fabric_defects"]['fabric_yarn_responsible'];
               $deffect_array["febric"][] = $data_array;

           }

         if( $data["fabric_defects"]['fabric_snag_nylon_visible_elastic']==1)
           {
               $data_array["comments"] = "Snag/Nylon Visible/Elastic";
               $data_array["major"] = $data["fabric_defects"]['fabric_snag_major'];
               $data_array["minor"] = $data["fabric_defects"]['fabric_snag_minor'];
               $data_array["corrective_action"] = $data["fabric_defects"]['fabric_snag_corrective_action'];
               $data_array["responsible"] = $data["fabric_defects"]['fabric_snag_responsible'];
               $deffect_array["febric"][] = $data_array;

           }

            if($data["fabric_defects"]['fabric_shading_hand_feel']==1)
           {
               $data_array["comments"] = "Shading /Hand Feel";
               $data_array["major"] = $data["fabric_defects"]['fabric_shading_major'];
               $data_array["minor"] = $data["fabric_defects"]['fabric_shading_minor'];
               $data_array["corrective_action"] = $data["fabric_defects"]['fabric_shading_corrective_action'];
               $data_array["responsible"] = $data["fabric_defects"]['fabric_shading_responsible'];
               $deffect_array["febric"][] = $data_array;

           }

            if( $data["fabric_defects"]['fabric_dye_finishing_streaks']==1)
           {
               $data_array["comments"] = "Dye/ Finishing Streaks/ Puckering yarn";
               $data_array["major"] = $data["fabric_defects"]['fabric_dye_finishing_major'];
               $data_array["minor"] = $data["fabric_defects"]['fabric_dye_finishing_minor'];
               $data_array["corrective_action"] = $data["fabric_defects"]['fabric_dye_finishing_corrective_action'];
               $data_array["responsible"] = $data["fabric_defects"]['fabric_dye_finishing_responsible'];
               $deffect_array["febric"][] = $data_array;

           }
            if( $data["fabric_defects"]['fabric_miscellaneous_fabric_defect']==1)
           {
               $data_array["comments"] = "Shading /Hand Feel";
               $data_array["major"] = $data["fabric_defects"]['fabric_miscellaneous_major'];
               $data_array["minor"] = $data["fabric_defects"]['fabric_miscellaneous_minor'];
               $data_array["corrective_action"] = $data["fabric_defects"]['fabric_miscellaneous_corrective_action'];
               $data_array["responsible"] = $data["fabric_defects"]['fabric_miscellaneous_responsible'];
               $deffect_array["febric"][] = $data_array;
           }


           if( $data["fabric_defects"]['fabric_newly_found_defect']==1) {

               foreach ($data["fabric_defects"]['fabric'] as $type => $values)
               {
                   $deffect_array["febric"][] = $values;

               }

           }

        if( $data["cutting_defects"]['cutting_defects_newly_found_defect']==1) {

            foreach ($data["cutting_defects"]['cutting'] as $type => $values)
            {
                $deffect_array["cutting"][] = $values;

            }

        }

        if( $data["sewing_defects"]['broken_drop']==1) {

            $data_array["comments"] = "Broken/Drop/Skip Stitch";
            $data_array["major"] = $data["sewing_defects"]['construction_broken_major'];
            $data_array["minor"] = $data["sewing_defects"]['construction_broken_minor'];
            $data_array["corrective_action"] = $data["sewing_defects"]['construction_broken_corrective_action'];
            $data_array["responsible"] = $data["sewing_defects"]['construction_broken_responsible'];
            $deffect_array["sewing"][] = $data_array;


        }

        if( $data["sewing_defects"]['row_edges_open']==1) {

            $data_array["comments"] = "Raw Edges/Open/Kno";
            $data_array["major"] = $data["sewing_defects"]['construction_raw_major'];
            $data_array["minor"] = $data["sewing_defects"]['construction_raw_minor'];
            $data_array["corrective_action"] = $data["sewing_defects"]['construction_raw_corrective_action'];
            $data_array["responsible"] = $data["sewing_defects"]['construction_raw_responsible'];
            $deffect_array["sewing"][] = $data_array;

        }

        if( $data["sewing_defects"]['overrun_stitches']==1) {

            $data_array["comments"] = "Overrun Stitches";
            $data_array["major"] = $data["sewing_defects"]['construction_overrun_major'];
            $data_array["minor"] = $data["sewing_defects"]['construction_overrun_minor'];
            $data_array["corrective_action"] = $data["sewing_defects"]['construction_overrun_corrective_action'];
            $data_array["responsible"] = $data["sewing_defects"]['construction_overrun_responsible'];
            $deffect_array["sewing"][] = $data_array;

        }

        if( $data["sewing_defects"]['uneven_wavy']==1) {

            $data_array["comments"] = "Uneven/Wavy";
            $data_array["major"] = $data["sewing_defects"]['construction_uneven_major'];
            $data_array["minor"] = $data["sewing_defects"]['construction_uneven_minor'];
            $data_array["corrective_action"] = $data["sewing_defects"]['uneven_wavy_corrective_action'];
            $data_array["responsible"] = $data["sewing_defects"]['uneven_wavy_responsible'];
            $deffect_array["sewing"][] = $data_array;

        }

        if( $data["sewing_defects"]['missing_insecure_component']==1) {

            $data_array["comments"] = "Missing/Insecure Component, Trim, Label";
            $data_array["major"] = $data["sewing_defects"]['construction_missing_major'];
            $data_array["minor"] = $data["sewing_defects"]['construction_missing_minor'];
            $data_array["corrective_action"] = $data["sewing_defects"]['construction_missing_corrective_action'];
            $data_array["responsible"] = $data["sewing_defects"]['construction_missing_responsible'];
            $deffect_array["sewing"][] = $data_array;

        }

        if( $data["sewing_defects"]['puckering_pleated']==1) {

            $data_array["comments"] = "Puckering/Pleated";
            $data_array["major"] = $data["sewing_defects"]['construction_puckering_major'];
            $data_array["minor"] = $data["sewing_defects"]['construction_puckering_minor'];
            $data_array["corrective_action"] = $data["sewing_defects"]['puckering_pleated_corrective_action'];
            $data_array["responsible"] = $data["sewing_defects"]['puckering_pleated_corrective_action_responsible'];
            $deffect_array["sewing"][] = $data_array;

        }

        if( $data["sewing_defects"]['needle_hole_chew']==1) {

            $data_array["comments"] = "Needle Hole/Chew";
            $data_array["major"] = $data["sewing_defects"]['construction_needle_major'];
            $data_array["minor"] = $data["sewing_defects"]['construction_needle_minor'];
            $data_array["corrective_action"] = $data["sewing_defects"]['needle_hole_chew_corrective_action'];
            $data_array["responsible"] = $data["sewing_defects"]['needle_hole_chew_corrective_action_responsible'];
            $deffect_array["sewing"][] = $data_array;

        }
        if( $data["sewing_defects"]['twisted_roping_uneven_hem']==1) {

            $data_array["comments"] = "Twisted roping or Uneven Hem";
            $data_array["major"] = $data["sewing_defects"]['construction_twisted_major'];
            $data_array["minor"] = $data["sewing_defects"]['construction_twisted_minor'];
            $data_array["corrective_action"] = $data["sewing_defects"]['twisted_roping_uneven_hem_corrective_action'];
            $data_array["responsible"] = $data["sewing_defects"]['twisted_roping_uneven_hem_responsible'];
            $deffect_array["sewing"][] = $data_array;

        }

        if( $data["sewing_defects"]['slated']==1) {

            $data_array["comments"] = "Slanted";
            $data_array["major"] = $data["sewing_defects"]['construction_slanted_major'];
            $data_array["minor"] = $data["sewing_defects"]['construction_slanted_minor'];
            $data_array["corrective_action"] = $data["sewing_defects"]['slated_corrective_action'];
            $data_array["responsible"] = $data["sewing_defects"]['slated_responsible'];
            $deffect_array["sewing"][] = $data_array;

        }

        if( $data["sewing_defects"]['noticeable_repair']==1) {

            $data_array["comments"] = "Noticeable Repair";
            $data_array["major"] = $data["sewing_defects"]['construction_noticeable_major'];
            $data_array["minor"] = $data["sewing_defects"]['construction_noticeable_minor'];
            $data_array["corrective_action"] = $data["sewing_defects"]['noticeable_repair_corrective_action'];
            $data_array["responsible"] = $data["sewing_defects"]['noticeable_repair_responsible'];
            $deffect_array["sewing"][] = $data_array;

        }


        if( $data["sewing_defects"]['incorrect_color_combination']==1) {

            $data_array["comments"] = "Incorrect color combination";
            $data_array["major"] = $data["sewing_defects"]['construction_incorrect_color_major'];
            $data_array["minor"] = $data["sewing_defects"]['construction_incorrect_color_minor'];
            $data_array["corrective_action"] = $data["sewing_defects"]['incorrect_color_corrective_action'];
            $data_array["responsible"] = $data["sewing_defects"]['incorrect_color_responsible'];
            $deffect_array["sewing"][] = $data_array;

        }

        if( $data["sewing_defects"]['high_low']==1) {

            $data_array["comments"] = "High / low";
            $data_array["major"] = $data["sewing_defects"]['construction_high_low_major'];
            $data_array["minor"] = $data["sewing_defects"]['construction_high_low_minor'];
            $data_array["corrective_action"] = $data["sewing_defects"]['high_low_corrective_action'];
            $data_array["responsible"] = $data["sewing_defects"]['high_low_responsible'];
            $deffect_array["sewing"][] = $data_array;

        }

        if( $data["sewing_defects"]['construction_specified']==1) {

            $data_array["comments"] = "Construction not as specified";
            $data_array["major"] = $data["sewing_defects"]['construction_not_as_major'];
            $data_array["minor"] = $data["sewing_defects"]['construction_not_as_minor'];
            $data_array["corrective_action"] = $data["sewing_defects"]['construction_specified_corrective_action'];
            $data_array["responsible"] = $data["sewing_defects"]['construction_specified_responsible'];
            $deffect_array["sewing"][] = $data_array;

        }

        if( $data["sewing_defects"]['incorrect_placement']==1) {

            $data_array["comments"] = "Incorrect Placement";
            $data_array["major"] = $data["sewing_defects"]['construction_incorrect_placement_major'];
            $data_array["minor"] = $data["sewing_defects"]['construction_incorrect_placement_minor'];
            $data_array["corrective_action"] = $data["sewing_defects"]['incorrect_placement_corrective_action'];
            $data_array["responsible"] = $data["sewing_defects"]['incorrect_placement_responsible'];
            $deffect_array["sewing"][] = $data_array;

        }

        if( $data["sewing_defects"]['miscellaneous_construction_defects']==1) {

            $data_array["comments"] = "Miscellaneous Construction defects";
            $data_array["major"] = $data["sewing_defects"]['construction_miscellaneous_major'];
            $data_array["minor"] = $data["sewing_defects"]['construction_miscellaneous_minor'];
            $data_array["corrective_action"] = $data["sewing_defects"]['miscellaneous_construction_defects_corrective_action'];
            $data_array["responsible"] = $data["sewing_defects"]['miscellaneous_construction_defects_responsible'];
            $deffect_array["sewing"][] = $data_array;

        }

        if( $data["sewing_defects"]['construction_newly_found_defect']==1) {


            foreach ($data["sewing_defects"]['sewing'] as $type => $values)
            {
                $deffect_array["sewing"][] = $values;

            }


        }

        if( $data["labeling_trim_defects"]['slanted_label']==1) {

            $data_array["comments"] = "Slanted Label";
            $data_array["major"] = $data["labeling_trim_defects"]['slanted_label_major'];
            $data_array["minor"] = $data["labeling_trim_defects"]['slanted_label_minor'];
            $data_array["corrective_action"] = $data["labeling_trim_defects"]['slanted_label_corrective_action'];
            $data_array["responsible"] = $data["labeling_trim_defects"]['slanted_label_responsible'];
            $deffect_array["labeling"][] = $data_array;

        }

        if( $data["labeling_trim_defects"]['displace_label']==1) {

            $data_array["comments"] = "Displace Label";
            $data_array["major"] = $data["labeling_trim_defects"]['displace_label_major'];
            $data_array["minor"] = $data["labeling_trim_defects"]['displace_label_minor'];
            $data_array["corrective_action"] = $data["labeling_trim_defects"]['displace_label_corrective_action'];
            $data_array["responsible"] = $data["labeling_trim_defects"]['displace_label_responsible'];
            $deffect_array["labeling"][] = $data_array;

        }

        if( $data["labeling_trim_defects"]['labeling_trim_defects_newly_found_defect']==1) {


            foreach ($data["labeling_trim_defects"]['labeling'] as $type => $values)
            {
                $deffect_array["labeling"][] = $values;

            }


        }

        if( $data["finishing_defects"]['poor_pressing']==1) {

            $data_array["comments"] = "Poor pressing (lnk shine, moire, color change, scorching etc.)";
            $data_array["major"] = $data["finishing_defects"]['wash_poor_pressing_major'];
            $data_array["minor"] = $data["finishing_defects"]['wash_poor_pressing_minor'];
            $data_array["corrective_action"] = $data["finishing_defects"]['wash_poor_pressing_corrective_action'];
            $data_array["responsible"] = $data["finishing_defects"]['wash_poor_pressing_responsible'];
            $deffect_array["finishing"][] = $data_array;

        }


        if( $data["finishing_defects"]['moist_bag']==1) {

            $data_array["comments"] = "Moist(bag,condensation,mildew)";
            $data_array["major"] = $data["finishing_defects"]['wash_moist_major'];
            $data_array["minor"] = $data["finishing_defects"]['wash_moist_minor'];
            $data_array["corrective_action"] = $data["finishing_defects"]['moist_bag_corrective_action'];
            $data_array["responsible"] = $data["finishing_defects"]['moist_bag_responsible'];
            $deffect_array["finishing"][] = $data_array;

        }

        if( $data["finishing_defects"]['poor_handfeel']==1) {

            $data_array["comments"] = "Poor hand feel";
            $data_array["major"] = $data["finishing_defects"]['wash_poor_hand_major'];
            $data_array["minor"] = $data["finishing_defects"]['wash_poor_hand_minor'];
            $data_array["corrective_action"] = $data["finishing_defects"]['poor_handfeel_corrective_action'];
            $data_array["responsible"] = $data["finishing_defects"]['poor_handfeel_responsible'];
            $deffect_array["finishing"][] = $data_array;

        }
        if( $data["finishing_defects"]['excessive_fraying_piling']==1) {

            $data_array["comments"] = "Excessive fraying / piling";
            $data_array["major"] = $data["finishing_defects"]['wash_excessive_major'];
            $data_array["minor"] = $data["finishing_defects"]['wash_excessive_minor'];
            $data_array["corrective_action"] = $data["finishing_defects"]['excessive_fraying_piling_corrective_action'];
            $data_array["responsible"] = $data["finishing_defects"]['excessive_fraying_piling_responsible'];
            $deffect_array["finishing"][] = $data_array;

        }

        if( $data["finishing_defects"]['excessive_residual_debris']==1) {

            $data_array["comments"] = "Excessive residual debris (stones, Sand)";
            $data_array["major"] = $data["finishing_defects"]['wash_excessive_residual_major'];
            $data_array["minor"] = $data["finishing_defects"]['wash_excessive_residual_minor'];
            $data_array["corrective_action"] = $data["finishing_defects"]['excessive_residual_debris_corrective_action'];
            $data_array["responsible"] = $data["finishing_defects"]['excessive_residual_debris_responsible'];
            $deffect_array["finishing"][] = $data_array;

        }

        if( $data["finishing_defects"]['garment_wash_dye_shading']==1) {

            $data_array["comments"] = "Garment wash / dye shading";
            $data_array["major"] = $data["finishing_defects"]['wash_garment_wash_major'];
            $data_array["minor"] = $data["finishing_defects"]['wash_garment_wash_minor'];
            $data_array["corrective_action"] = $data["finishing_defects"]['garment_wash_dye_shading_corrective_action'];
            $data_array["responsible"] = $data["finishing_defects"]['garment_wash_dye_shading_responsible'];
            $deffect_array["finishing"][] = $data_array;

        }

        if( $data["finishing_defects"]['measurement_discrepancy']==1) {

            $data_array["comments"] = "Measurement discrepancy";
            $data_array["major"] = $data["finishing_defects"]['wash_measurement_major'];
            $data_array["minor"] = $data["finishing_defects"]['wash_measurement_minor'];
            $data_array["corrective_action"] = $data["finishing_defects"]['measurement_discrepancy_corrective_action'];
            $data_array["responsible"] = $data["finishing_defects"]['measurement_discrepancy_responsible'];
            $deffect_array["finishing"][] = $data_array;

        }

        if( $data["finishing_defects"]['garment_wash_dye_within_color']==1) {

            $data_array["comments"] = "Garment wash / dye not within color standard";
            $data_array["major"] = $data["finishing_defects"]['wash_garment_wash_dye_major'];
            $data_array["minor"] = $data["finishing_defects"]['wash_garment_wash_dye_minor'];
            $data_array["corrective_action"] = $data["finishing_defects"]['garment_wash_dye_within_color_corrective_action'];
            $data_array["responsible"] = $data["finishing_defects"]['garment_wash_dye_within_color_responsible'];
            $deffect_array["finishing"][] = $data_array;

        }

        if( $data["finishing_defects"]['improper_shape']==1) {

            $data_array["comments"] = "Improper shape";
            $data_array["major"] = $data["finishing_defects"]['wash_improper_shape_major'];
            $data_array["minor"] = $data["finishing_defects"]['wash_improper_shape_minor'];
            $data_array["corrective_action"] = $data["finishing_defects"]['improper_shape_corrective_action'];
            $data_array["responsible"] = $data["finishing_defects"]['improper_shape_responsible'];
            $deffect_array["finishing"][] = $data_array;

        }

        if( $data["finishing_defects"]['poor_attachment_miscellaneous_wash']==1) {

            $data_array["comments"] = "Poor attachment / miscellaneous wash defect";
            $data_array["major"] = $data["finishing_defects"]['wash_poor_attachment_major'];
            $data_array["minor"] = $data["finishing_defects"]['wash_poor_attachment_minor'];
            $data_array["corrective_action"] = $data["finishing_defects"]['poor_attachment_miscellaneous_wash_corrective_action'];
            $data_array["responsible"] = $data["finishing_defects"]['poor_attachment_miscellaneous_wash_responsible'];
            $deffect_array["finishing"][] = $data_array;

        }

        if( $data["finishing_defects"]['oil']==1) {

            $data_array["comments"] = "Oil";
            $data_array["major"] = $data["finishing_defects"]['cleanliness_oil_major'];
            $data_array["minor"] = $data["finishing_defects"]['cleanliness_oil_minor'];
            $data_array["corrective_action"] = $data["finishing_defects"]['oil_corrective_action'];
            $data_array["responsible"] = $data["finishing_defects"]['oil_responsible'];
            $deffect_array["finishing"][] = $data_array;

        }

        if( $data["finishing_defects"]['spot_dirty']==1) {

            $data_array["comments"] = "Spot/dirty";
            $data_array["major"] = $data["finishing_defects"]['cleanliness_spot_major'];
            $data_array["minor"] = $data["finishing_defects"]['cleanliness_spot_minor'];
            $data_array["corrective_action"] = $data["finishing_defects"]['spot_dirty_corrective_action'];
            $data_array["responsible"] = $data["finishing_defects"]['spot_dirty_responsible'];
            $deffect_array["finishing"][] = $data_array;

        }

        if( $data["finishing_defects"]['loose_uncut_thread']==1) {

            $data_array["comments"] = "Loose & uncut thread";
            $data_array["major"] = $data["finishing_defects"]['cleanliness_loose_major'];
            $data_array["minor"] = $data["finishing_defects"]['cleanliness_loose_minor'];
            $data_array["corrective_action"] = $data["finishing_defects"]['loose_uncut_thread_corrective_action'];
            $data_array["responsible"] = $data["finishing_defects"]['loose_uncut_thread_responsible'];
            $deffect_array["finishing"][] = $data_array;

        }

        if( $data["finishing_defects"]['flying_dust']==1) {

            $data_array["comments"] = "Flying dust";
            $data_array["major"] = $data["finishing_defects"]['cleanliness_flying_major'];
            $data_array["minor"] = $data["finishing_defects"]['cleanliness_flying_minor'];
            $data_array["corrective_action"] = $data["finishing_defects"]['flying_dust_corrective_action'];
            $data_array["responsible"] = $data["finishing_defects"]['flying_dust_responsible'];
            $deffect_array["finishing"][] = $data_array;

        }

        if( $data["finishing_defects"]['wash_finish_dye_newly_found_defect'] ==1) {


            foreach ($data["finishing_defects"]['finishing'] as $type => $values)
            {
                $deffect_array["finishing"][] = $values;

            }


        }

        if( $data["packing_problem"]['missing_incorrect_information']==1) {

            $data_array["comments"] = "Missing/ incorrect information on poly bag";
            $data_array["major"] = $data["packing_problem"]['packaging_missing_incorrect_major'];
            $data_array["minor"] = $data["packing_problem"]['packaging_missing_incorrect_minor'];
            $data_array["corrective_action"] = $data["packing_problem"]['missing_incorrect_information_corrective_action'];
            $data_array["responsible"] = $data["packing_problem"]['missing_incorrect_information_responsible'];
            $deffect_array["packing"][] = $data_array;

        }


        if( $data["packing_problem"]['incorrect_poly_bag_size']==1) {

            $data_array["comments"] = "Incorrect poly bag size";
            $data_array["major"] = $data["packing_problem"]['packaging_incorrect_poly_bag_major'];
            $data_array["minor"] = $data["packing_problem"]['packaging_incorrect_poly_bag_minor'];
            $data_array["corrective_action"] = $data["packing_problem"]['incorrect_poly_bag_size_corrective_action'];
            $data_array["responsible"] = $data["packing_problem"]['incorrect_poly_bag_size_responsible'];
            $deffect_array["packing"][] = $data_array;

        }


        if( $data["packing_problem"]['carton_contents_shipping_mark']==1) {

            $data_array["comments"] = "carton contents shipping mark incorrect";
            $data_array["major"] = $data["packing_problem"]['packaging_carton_contents_major'];
            $data_array["minor"] = $data["packing_problem"]['packaging_carton_contents_minor'];
            $data_array["corrective_action"] = $data["packing_problem"]['carton_contents_shipping_mark_corrective_action'];
            $data_array["responsible"] = $data["packing_problem"]['carton_contents_shipping_mark_responsible'];
            $deffect_array["packing"][] = $data_array;

        }

        if( $data["packing_problem"]['incorrect_carton_count_ratio']==1) {

            $data_array["comments"] = "Incorrect carton count/ ratio";
            $data_array["major"] = $data["packing_problem"]['packaging_incorrect_carton_major'];
            $data_array["minor"] = $data["packing_problem"]['packaging_incorrect_carton_minor'];
            $data_array["corrective_action"] = $data["packing_problem"]['incorrect_carton_count_ratio_corrective_action'];
            $data_array["responsible"] = $data["packing_problem"]['incorrect_carton_count_ratio_responsible'];
            $deffect_array["packing"][] = $data_array;

        }

        if( $data["packing_problem"]['over_packed_under_packed_carton']==1) {

            $data_array["comments"] = "Over packed under packed carton ";
            $data_array["major"] = $data["packing_problem"]['packaging_over_packed_major'];
            $data_array["minor"] = $data["packing_problem"]['packaging_over_packed_minor'];
            $data_array["corrective_action"] = $data["packing_problem"]['over_packed_under_packed_carton_corrective_action'];
            $data_array["responsible"] = $data["packing_problem"]['over_packed_under_packed_carton_responsible'];
            $deffect_array["packing"][] = $data_array;

        }

        if( $data["packing_problem"]['missing_incorrect_upc']==1) {

            $data_array["comments"] = "Missing/ incorrct UPC Sticker. H. Tag etc ";
            $data_array["major"] = $data["packing_problem"]['packaging_missing_incorrect_upc_major'];
            $data_array["minor"] = $data["packing_problem"]['packaging_missing_incorrect_upc_minor'];
            $data_array["corrective_action"] = $data["packing_problem"]['missing_incorrect_upc_corrective_action'];
            $data_array["responsible"] = $data["packing_problem"]['missing_incorrect_upc_responsible'];
            $deffect_array["packing"][] = $data_array;

        }

        if( $data["packing_problem"]['mixed_sizes']==1) {

            $data_array["comments"] = "Mixed Sizes ";
            $data_array["major"] = $data["packing_problem"]['packaging_mixed_sizes_major'];
            $data_array["minor"] = $data["packing_problem"]['packaging_mixed_sizes_minor'];
            $data_array["corrective_action"] = $data["packing_problem"]['mixed_sizes_corrective_action'];
            $data_array["responsible"] = $data["packing_problem"]['mixed_sizes_responsible'];
            $deffect_array["packing"][] = $data_array;

        }

        if( $data["packing_problem"]['foreign_objects']==1) {

            $data_array["comments"] = "Foreign objects ( Staples, pins, needles etc ) ";
            $data_array["major"] = $data["packing_problem"]['packaging_foreign_major'];
            $data_array["minor"] = $data["packing_problem"]['packaging_foreign_minor'];
            $data_array["corrective_action"] = $data["packing_problem"]['foreign_objects_corrective_action'];
            $data_array["responsible"] = $data["packing_problem"]['foreign_objects_responsible'];
            $deffect_array["packing"][] = $data_array;

        }

        if( $data["packing_problem"]['damaged_open_poly_bag']==1) {

            $data_array["comments"] = "Damaged or open poly bag";
            $data_array["major"] = $data["packing_problem"]['packaging_damaged_major'];
            $data_array["minor"] = $data["packing_problem"]['packaging_damaged_minor'];
            $data_array["corrective_action"] = $data["packing_problem"]['damaged_open_poly_bag_corrective_action'];
            $data_array["responsible"] = $data["packing_problem"]['damaged_open_poly_bag_responsible'];
            $deffect_array["packing"][] = $data_array;

        }

        if( $data["packing_problem"]['miscellaneous_packing_defects']==1) {

            $data_array["comments"] = "Miscellaneous packaging defects";
            $data_array["major"] = $data["packing_problem"]['packaging_miscellaneous_packing_major'];
            $data_array["minor"] = $data["packing_problem"]['packaging_miscellaneous_packing_minor'];
            $data_array["corrective_action"] = $data["packing_problem"]['miscellaneous_packing_defects_corrective_action'];
            $data_array["responsible"] = $data["packing_problem"]['miscellaneous_packing_defects_responsible'];
            $deffect_array["packing"][] = $data_array;

        }

        if( $data["packing_problem"]['crease_mark']==1) {

            $data_array["comments"] = "Crease mark";
            $data_array["major"] = $data["packing_problem"]['packaging_crease_mark_major'];
            $data_array["minor"] = $data["packing_problem"]['packaging_crease_mark_minor'];
            $data_array["corrective_action"] = $data["packing_problem"]['crease_mark_corrective_action'];
            $data_array["responsible"] = $data["packing_problem"]['crease_mark_responsible'];
            $deffect_array["packing"][] = $data_array;

        }

        if( $data["packing_problem"]['packaging_newly_found_defect']==1) {


            foreach ($data["packing_problem"]['packing'] as $type => $values)
            {
                $deffect_array["packing"][] = $values;

            }


        }

        if( $data["styling_defects"]['styling_defects_newly_found_defect']==1) {


            foreach ($data["styling_defects"]['styling'] as $type => $values)
            {
                $deffect_array["styling"][] = $values;

            }


        }

        if( $data["other_issues"]['other_issue_newly_found_defect']==1) {


            foreach ($data["other_issues"]['other'] as $type => $values)
            {
                $deffect_array["other"][] = $values;

            }


        }

      //  return new JsonResponse(array('da'=>$data,'de'=>$deffect_array));

        foreach ($array as $type => $values2)
        {
            foreach ($deffect_array as $key2 => $val2)
            {

                if($values2==$key2)
                {
                    $loop=$this->CommonLopping($id,$type,$values2, $deffect_array);
                    //return new JsonResponse($key);

                }

            }

        }

        $this->uploadImage($id, $data);
        return new JsonResponse(array('da'=>$data,'de'=>$deffect_array));
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
        if(! $data['images'] || empty($data['images'])) {
            return;
        }

        $photos = FileUtil::uploadFileFromDataUrls(
            realpath(WEB_DIR . "/../mis/images/inline_inspection_photo/"),
            "{$id}_inline_inspection_photo_",
            $data['images']
        );

        $this->getRepository()->from('inline_inspection_tbl')->update(array(
            'photo' => implode(',', $photos)
        ), array('id=?' => $id));
    }




    private function CommonLopping($id,$type,$values, $deffect_array)
    {

        foreach ($deffect_array[$values]as $value) {

            $this->insertIntoInlineSubTbl($id, $type, $value);

        }
    }
    private function insertIntoInlineSubTbl($id, $type, $deffect_array)
    {
        $deffect_array['inline_ref_id'] = $id;
        $deffect_array['comments_type'] = $type;
        $deffect_array['entryby'] = $this->getUser()->contact_id;
        $deffect_array['entrytime'] = date('Y-m-d H:i:s');
        $this->getRepository()->from('inline_sub_tbl')->insert($deffect_array);


    }

    public function detailsAction(Request $request)
    {
        $detailsFor= json_decode($request->getContent(), true);
        $sql= "SELECT
  inline_inspection_tbl.id,
  DATE_FORMAT(inline_inspection_tbl.entrytime,'%d-%m-%Y %h:%i %p')AS entrytime,
  quality_assignment_tbl.`daily_washing_report_due_date` AS `date`,
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

}
