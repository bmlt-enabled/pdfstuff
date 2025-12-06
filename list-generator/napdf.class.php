<?php

/**
    \file napdf.class.php

    \brief This is the structural implementation of a PDF-printable list. It is meant to be a base for focused lists.
*/

require_once(dirname(__FILE__) . '/fpdf/fpdf.php');                         ///< This is the FPDF class, used to create the PDF.

/**
    \brief This class sets up a boilerplate FPDF instance to be used to generate the PDF.

    It is a SINGLETON pattern. This should be created in bunches, because the objects can be quite big, and the static elements
    make the key sorting easier.
*/
class napdf extends FPDF
{
    static $fpdf_instance = null;   ///< This is a SINGLETON, so we'll be using this as our instance.

    public $sort_order_keys = null; ///< This specifies which keys will be sorted. See the set_sort function for a description of how this works.
    public $meeting_data = null;   ///< This will be an array, with the returned meeting data.
    public $format_data = null;    ///< This will be an array, with the returned format data.
    public $lang_search = 'en';    ///< Set this to the specific language[s], if we are looking for formats in a specific language. Default is 'en'.
    public $my_font_family = 'Helvetica';  ///< The font family to use for this.
    public $my_page_height = 0.0;  ///< The height of the page, in inches.
    public $my_page_width = 0.0;   ///< The width of the page, in inches.

    /**
        \brief Fetches the JSON data, and loads up our internal data array with all the meeting data. It creates an associative array
        of the meeting data.

        This loads the internal $meeting_data nested array, in which each meeting is one row, then an associative array of meeting data values.
    */
    private function FetchJSON(
        $inRootURI,             ///< The URI of the Root Server we are querying.
        $in_http_vars = null    ///< These contain alternatives to the $_GET and/or $_POST parameters. Default is null.
    ) {
        if (!isset($in_http_vars) || !is_array($in_http_vars) || !count($in_http_vars)) {
            $in_http_vars = array ('lang_enum' => $this->lang_search);
        } else {
            $in_http_vars['lang_enum'] = $this->lang_search;
        }

        $callCurlURL = $inRootURI . 'client_interface/json/?switcher=GetSearchResults';

        foreach ($in_http_vars as $key => $value) {
            if (isset($value)) {
                if (is_array($value)) {
                    if (count($value)) {
                        foreach ($value as $val) {
                            $callCurlURL .= "&$key" . "[]=$val";
                        }
                    } else {
                        $callCurlURL .= "&$key";
                    }
                } else {
                    $callCurlURL .= "&$key=$value";
                }
            } else {
                $callCurlURL .= "&$key";
            }
        }

        // Get both meetings and formats in a single request
        $response = $this->call_curl($callCurlURL . '&get_used_formats=1', false);

        if ($response) {
            $response_data = json_decode($response, true);

            if (is_array($response_data)) {
                // Extract formats
                if (isset($response_data['formats']) && is_array($response_data['formats'])) {
                    $this->format_data = array();

                    foreach ($response_data['formats'] as $format) {
                        if (is_array($format) && isset($format['id']) && isset($format['lang'])) {
                            $this->format_data[$format['id'] . '_' . $format['lang']] = $format;
                        }
                    }
                }

                // Extract meetings
                if (isset($response_data['meetings']) && is_array($response_data['meetings'])) {
                    $this->meeting_data = $response_data['meetings'];
                }
            }
        }
    }

    /**
        \brief  This is the object factory function. It will return the new instance of the napdf class.

        NOTE: This ALWAYS destroys the original object, so you need to keep that in mind!

        \returns A reference to an instance of napdf
    */
    static function MakeNAPDF(
        $inRootURI,             ///< The Root URI
        $in_x,                  ///< The width of each printed page, in $in_units
        $in_y,                  ///< The height of each printed page, in $in_units
        $in_http_vars,          ///< The various parameters used to dictate the meeting search.
        $in_units = 'in',       /**< The measurement units.
         - 'in' (Inches)
         - 'mm' (Millimeters)
         - 'cm' (Centimeters)
         - 'pt' (Points)
         Default is 'in'.
         */
        $in_orientation = 'P',  /**< The orientation
         - 'P' (Portrait)
         - 'L' (Landscape)
         Default is 'P'
         */
        $in_font_family = 'Helvetica',  ///< Optional. The font family to use for this instance. Default is Helvetica.
        $in_keys = null,        ///< Optional. If the sort keys are passed in here, we'll sort the data.
        $in_lang_search = null  ///< Optional. An array of language enums, used to extract the correct format codes.
    ) {
        self::$fpdf_instance = null;
        self::$fpdf_instance = new napdf($inRootURI, $in_x, $in_y, $in_http_vars, $in_units, $in_orientation, $in_lang_search);

        return self::$fpdf_instance;
    }

    /**
        \brief  This is the way you sort the meeting data.
    */
    function set_sort()
    {
            $data = $this->meeting_data;
            $ret = usort($data, [$this, 'sort_meeting_data_callback']);
            $this->meeting_data = $data;
            return $ret;
    }

    /**
        \brief  This is a static callback function to be used for sorting the multi-dimensional meeting_data
                array. It uses the sort_order_keys array to determine the sort.

        \returns an integer. -1 if a < b, 0 if a == b, or 1 if a > b.
    */
    function sort_meeting_data_callback(
        $in_a,      ///< The first meeting array to compare
        $in_b       ///< The second meeting array to compare
    ) {
        $ret = 0;

        if (is_array($in_a) && is_array($in_b) && is_array($this->sort_order_keys)) {
            // We reverse the array, in order to sort from least important to most important.
            $week_starts_on = intval($this->sort_order_keys['week_starts']);
            $sort_keys = array_reverse($this->sort_order_keys, true);
            if (isset($sort_keys['week_starts'])) {
                unset($sort_keys['week_starts']);
            }
            foreach ($sort_keys as $key => $value) {
                if (isset($in_a[$key]) && isset($in_b[$key])) {
                    $val_a = trim($in_a[$key]);
                    $val_b = trim($in_b[$key]);
                    if (('weekday_tinyint' == $key) && ($week_starts_on > 1) && ($week_starts_on < 8)) {
                        $val_a -= $week_starts_on;

                        if ($val_a < 0) {
                            $val_a += 8;
                        } else {
                            $val_a += 1;
                        }

                        $val_b -= $week_starts_on;

                        if ($val_b < 0) {
                            $val_b += 8;
                        } else {
                            $val_b += 1;
                        }
                    }

                    // We know a few keys already, and we can determine how the sorting goes from there.
                    switch ($key) {
                        case 'start_time':
                        case 'duration_time':
                            $val_a = strtotime($val_a);
                            $val_b = strtotime($val_b);
                            // Fall through intentional
                        case 'id_bigint':
                        case 'shared_group_id_bigint':
                        case 'weekday_tinyint':
                        case 'service_body_bigint':
                            $val_a = intval($val_a);
                            $val_b = intval($val_b);
                            // Fall through intentional
                        case 'longitude':
                        case 'latitude':
                            if ($val_a > $val_b) {
                                $ret = 1;
                            } elseif ($val_b > $val_a) {
                                $ret = -1;
                            }
                            break;

                        default:
                            // We ignore blank values
                            if (strlen($val_a) && strlen($val_b)) {
                                $tmp = strcmp(strtolower($val_a), strtolower($val_b));

                                if ($tmp != 0) {
                                    $ret = 0 > $tmp ? -1 : 1;
                                }
                            }
                            break;
                    }
                }
            }
        }

        return $ret;
    }

    /**
        \brief  The class constructor. Sets up the instance, and reads in the meeting data.
    */
    public function __construct(
        $inRootURI,             ///< The Root Server URI.
        $in_x,                  ///< The width of each printed page, in $in_units
        $in_y,                  ///< The height of each printed page, in $in_units
        $in_http_vars,          ///< The various parameters used to dictate the meeting search.
        $in_units = 'in',       /**< The measurement units.
         - 'in' (Inches)
         - 'mm' (Millimeters)
         - 'cm' (Centimeters)
         - 'pt' (Points)
         Default is 'in'.
         */
        $in_orientation = 'P',  /**< The orientation
         - 'P' (Portrait)
         - 'L' (Landscape)
         Default is 'P'
         */
        $in_lang_search = null  ///< An array of language enums, used to extract the correct format codes.
    ) {
        if (is_array($in_lang_search) && count($in_lang_search)) {
            $this->lang_search = $in_lang_search;
        }

        parent::__construct($in_orientation, $in_units, array ($in_x, $in_y));
        $this->SetAutoPageBreak(0);
        $this->SetAuthor("BMLT");
        $this->SetCreator("BMLT");
        $this->SetSubject("Printable Meeting List");
        $this->SetTitle("Printable Meeting List");
        $this->FetchJSON($inRootURI, $in_http_vars);
        // Okay, at this point, we've set up the PDF object, and have done the meeting search. Our meetings are waiting for us to start making the list.
    }

    /********************************************************************
        \brief A simple accessor for the associated font family.

        \returns a string, containing the font family.
    */
    public function getFontFamily()
    {
        return $this->my_font_family;
    }

    /********************************************************************
        \brief This is a function that returns the results of an HTTP call to a URI.
        It is a lot more secure than file_get_contents, but does the same thing.

        \returns a string, containing the response. Null if the call fails to get any data.

        \throws an exception if the call fails.
    */
    static function call_curl(
        $in_uri,             ///< A string. The URI to call.
        $in_post = true,        ///< If false, the transaction is a GET, not a POST. Default is true.
        &$http_status = null    ///< Optional reference to a string. Returns the HTTP call status.
    ) {
        $ret = null;

    // If the curl extension isn't loaded, we try one backdoor thing. Maybe we can use file_get_contents.
        if (!extension_loaded('curl')) {
            if (ini_get('allow_url_fopen')) {
                $ret = file_get_contents($in_uri);
            }
        } else {
            // Create a new cURL resource.
            $resource = curl_init();

            // If we will be POSTing this transaction, we split up the URI.
            if ($in_post) {
                $spli = explode("?", $in_uri, 2);

                if (is_array($spli) && count($spli)) {
                    $in_uri = $spli[0];
                    $in_params = $spli[1];

                    curl_setopt($resource, CURLOPT_POST, true);
                    curl_setopt($resource, CURLOPT_POSTFIELDS, $in_params);
                }
            }

            // Set url to call.
            curl_setopt($resource, CURLOPT_URL, $in_uri);

            // Make curl_exec() function (see below) return requested content as a string (unless call fails).
            curl_setopt($resource, CURLOPT_RETURNTRANSFER, true);

            // By default, cURL prepends response headers to string returned from call to curl_exec().
            // You can control this with the below setting.
            // Setting it to false will remove headers from beginning of string.
            // If you WANT the headers, see the Yahoo documentation on how to parse with them from the string.
            curl_setopt($resource, CURLOPT_HEADER, false);

            // Allow  cURL to follow any 'location:' headers (redirection) sent by server (if needed set to true, else false- defaults to false anyway).
            // Disabled, because some servers disable this for security reasons.
    //      curl_setopt ($resource, CURLOPT_FOLLOWLOCATION, true);

            // Set maximum times to allow redirection (use only if needed as per above setting. 3 is sort of arbitrary here).
            curl_setopt($resource, CURLOPT_MAXREDIRS, 3);

            // Set connection timeout in seconds (very good idea).
            curl_setopt($resource, CURLOPT_CONNECTTIMEOUT, 10);

            // Direct cURL to send request header to server allowing compressed content to be returned and decompressed automatically (use only if needed).
            curl_setopt($resource, CURLOPT_ENCODING, 'gzip,deflate');

            // Execute cURL call and return results in $content variable.
            $content = curl_exec($resource);

            // Check if curl_exec() call failed (returns false on failure) and handle failure.
            if ($content === false) {
                // Cram as much info into the exception as possible.
                throw new Exception("curl failure calling $in_uri, " . curl_error($resource) . ", " . curl_errno($resource));
            } else {
                // Do what you want with returned content (e.g. HTML, XML, etc) here or AFTER curl_close() call below as it is stored in the $content variable.

                // You MIGHT want to get the HTTP status code returned by server (e.g. 200, 400, 500).
                // If that is the case then this is how to do it.
                $http_status = curl_getinfo($resource, CURLINFO_HTTP_CODE);
            }

            // Close cURL and free resource.
            curl_close($resource);

            // Maybe echo $contents of $content variable here.
            if ($content !== false) {
                $ret = $content;
            }
        }

        return $ret;
    }
};
