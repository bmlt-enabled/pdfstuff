<?php

/**
    \file sas_napdf.class.php

    \brief This file creates and dumps a Rockland meeting list in PDF form.
*/

// Get the napdf class, which is used to fetch the data and construct the file.
require_once(dirname(__FILE__) . '/flex_napdf.class.php');

define("_NSLI_LIST_HELPLINE", "Regional Helpline: (212) 929-NANA (6262)");
define("_NSLI_LIST_ROOT_URI", "https://bmlt.newyorkna.org/main_server/");
define("_NSLI_LIST_CREDITS", "Meeting List Printed by the <TBD> Area");
define("_NSLI_LIST_URL", "Web Site: http://<TBD>");
define("_NSLI_LIST_BANNER_1", "NA Meetings");
define("_NSLI_LIST_BANNER_2", "On");
define("_NSLI_LIST_BANNER_3", "Long Island, New York");
define("_NSLI_DATE_FORMAT", '\R\e\v\i\s\e\d F, Y');
define("_NSLI_FILENAME_FORMAT", 'Printable_PDF_NA_Meeting_List_%s.pdf');
define("_NSLI_IMAGE_POSIX_PATH", 'images/Sunburst_Cover_Logo.png');
define("_NSLI_WEEK_STARTS", 2);

/**
    \brief  This creates and manages an instance of the napdf class, and creates
    the PDF file.
*/
class nsli_napdf extends flex_napdf
{
    /********************************************************************
        \brief  The constructor for this class does a lot. It creates the instance of the napdf class, gets the data from the
        server, then sorts it. When the constructor is done, the data is ready to be assembled into a PDF.

        If the napdf object does not successfully get data from the server, then it is set to null.
    */
    function __construct(  $in_http_vars   ///< The HTTP parameters we'd like to send to the server.
                            )
    {
        $this->helpline_string = _NSLI_LIST_HELPLINE;       ///< This is the default string we use for the Helpline.
        $this->credits_string = _NSLI_LIST_CREDITS;         ///< The credits for creation of the list.
        $this->web_uri_string = _NSLI_LIST_URL;             ///< The Web site URI.
        $this->banner_1_string = _NSLI_LIST_BANNER_1;       ///< The First Banner String.
        $this->banner_2_string = _NSLI_LIST_BANNER_2;       ///< The Second Banner String.
        $this->banner_3_string = _NSLI_LIST_BANNER_3;       ///< The Third Banner String.
        $this->week_starts_1_based_int = _NSLI_WEEK_STARTS; ///< The Day of the week (1-based integer, with 1 as Sunday) that our week starts.

        $this->image_path_string = _NSLI_IMAGE_POSIX_PATH;  ///< The POSIX path to the image, relative to this file.
        $this->filename = sprintf(_NSLI_FILENAME_FORMAT, date("Y_m_d"));  ///< The output name for the file.
        $this->root_uri = _NSLI_LIST_ROOT_URI;                  ///< This is the default Root Server URL.
        $this->date_header_format_string = _NSLI_DATE_FORMAT;   ///< This is the default string we use for the date attribution line at the top.

        $this->font = 'Helvetica';      ///< The font we'll use.

        $this->sort_keys = array (  'weekday_tinyint' => true,          ///< First, sort by weekday
                                    'start_time' => true,               ///< Next, the meeting start time
                                    'location_municipality' => true,    ///< Next, the town.
                                    'week_starts' => $this->week_starts_1_based_int ///< Our week starts on this day
                                    );

        /// These are the parameters that we send over to the root server, in order to get our meetings.
        $this->out_http_vars = array ( 'services' => array (  ///< We will be asking for meetings in specific Service Bodies.
                                                            1001,   ///< SSAASC
                                                            1002,   ///< NASC
                                                            1003,   ///< ELIASC
                                                            1004,   ///< SSSAC
                                                            1067    ///< NSLI
                                                           ),
                                        'sort_key' => 'time'
                                    );

        parent::__construct($in_http_vars);
    }
};
