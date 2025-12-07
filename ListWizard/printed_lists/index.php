<?php

date_default_timezone_set('UTC');
// Don't display errors to stdout (would break PDF output)
// But log them to stderr so they appear in Docker/Apache logs
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php://stderr');
error_reporting(E_ALL);
set_time_limit(300);

Make_List();

/**
    \brief This function actually gets the CSV data from the root server, and creates a PDF file from it, using FPDF.
*/
function Make_List()
{
    $in_http_vars = array_merge($_GET, $_POST);

    // Determine which list class to use
    $list = isset($in_http_vars['use_list']) ? $in_http_vars['use_list'] : 'nsli';

    try {
        // Load the appropriate class file
        require_once(dirname(__FILE__) . "/$list" . "_napdf.class.php");
        $class_name = $list . '_napdf';
        
        // Create instance and generate PDF
        $class_instance = new $class_name($in_http_vars);
        if ($class_instance->AssemblePDF()) {
            $class_instance->OutputPDF();
        } else {
            throw new Exception('Failed to assemble PDF');
        }
    } catch (Exception $e) {
        // Log error
        error_log('PDF Generation Error: ' . $e->getMessage());
        
        // Return error response
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to generate PDF',
            'message' => $e->getMessage()
        ]);
    }
}
