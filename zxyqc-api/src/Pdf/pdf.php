<?php
namespace pdf;

use mPDF;

class Pdf
{
    public static function GeneratePDF($id,$filename,$html)
    {

        ob_start();

        /*

        $html=ob_get_clean();

        $html = utf8_encode($html);
        */


        include('mpdf60/mpdf.php');

        $mpdf = new mPDF();

        $mpdf->allow_charset_conversion = true;

        $mpdf->charset_in = 'UTF-8';

        $mpdf->writeHTML($html);

        //$mpdf->output('meu-pdf', 'I');
        $mpdf->Output('../mis/testPDF/'.$id.'_'.$filename.'.pdf','F');

        exit();
    }
}