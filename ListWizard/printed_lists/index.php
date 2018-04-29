<?php
ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);
set_time_limit ( 300 );

Make_List();

/**
    \brief This function actually gets the CSV data from the root server, and creates a PDF file from it, using FPDF.
*/
function Make_List() {
    $in_http_vars = array_merge_recursive ( $_GET, $_POST );
    
    require_once (dirname ( __FILE__ )."/flex_napdf.class.php");
    $class_instance = new flex_napdf ( $in_http_vars );
    if ($class_instance->AssemblePDF()) {
        $class_instance->OutputPDF();
    }
}
?>
