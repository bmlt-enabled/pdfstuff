<?php
ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);
set_time_limit ( 300 );

Make_List();

/**
    \brief This function actually gets the CSV data from the root server, and creates a PDF file from it, using FPDF.
*/
function Make_List() {
    $in_http_vars = $_GET;
    
    require_once (dirname ( __FILE__ )."/nsli_napdf.class.php");
    $class_instance = new nsli_napdf ( $in_http_vars );
    if ($class_instance->AssemblePDF()) {
        $class_instance->OutputPDF();
    }
}
?>
