<?php
ob_start();



$html=ob_get_clean();

$html = utf8_encode($html);

$html ='set value hear';

include("folder name/mpdf.php");

$mpdf = new mPDF();

$mpdf -> allow_charset_conversion = true ;

$mpdf -> charset_in = 'UTF-8' ;

$mpdf -> writeHTML($html);

$mpdf -> output('meu-pdf','I');

exit() ;