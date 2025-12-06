<?php
date_default_timezone_set('UTC');
ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);
set_time_limit ( 300 );

Make_List();

/**
    \brief This function actually gets the CSV data from the root server, and creates a PDF file from it, using FPDF.
*/
function Make_List() {
    $in_http_vars = $_GET;
    
    $list = isset($in_http_vars['use_list']) ? $in_http_vars['use_list'] : 'nsli';
    
    require_once (dirname ( __FILE__ )."/$list"."_napdf.class.php");
    $class_name = $list.'_napdf';
    $class_instance = new $class_name ( $in_http_vars );
    if ($class_instance->AssemblePDF()) {
        $class_instance->OutputPDF();
    }
}
?>
