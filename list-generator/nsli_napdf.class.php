<?php

/**
    \file sas_napdf.class.php

    \brief This file creates and dumps a Rockland meeting list in PDF form.
*/

// Get the napdf class, which is used to fetch the data and construct the file.
require_once(dirname(__FILE__) . '/flex_napdf.class.php');

define("_NSLI_LIST_HELPLINE", "Regional Helpline: (212) 929-NANA (6262)");
define("_NSLI_LIST_SUBCOMMITTEE_HEADER", "COMMITTEE MEETINGS");
define("_NSLI_LIST_ROOT_URI", "https://bmlt.newyorkna.org/main_server/");
define("_NSLI_LIST_CREDITS", "Meeting List Printed by the Heart of Long Island Area");
define("_NSLI_LIST_URL", "Web Site: https://heartoflongislandna.org");
define("_NSLI_LIST_FOOTER", "NA meetings are 90 minutes (an hour and a half) long, unless otherwise noted (in parentheses).");
define("_NSLI_LIST_BANNER_1", "NA Meetings");
define("_NSLI_LIST_BANNER_2", "On");
define("_NSLI_LIST_BANNER_3", "Long Island, New York");
define("_NSLI_DATE_FORMAT", '\R\e\v\i\s\e\d F, Y');
define("_NSLI_FILENAME_FORMAT", 'Printable_PDF_NA_Meeting_List_%s.pdf');
define("_NSLI_IMAGE_POSIX_PATH", 'images/HOLI' . (isset($in_http_vars['color']) ? '-Color' : '') . '.png');
define("_NSLI_WEEK_STARTS", 2);
define("_NSLI_VARIABLE_FONT", 9);

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
        $this->variable_font_size = _NSLI_VARIABLE_FONT;    ///< The variable font starting point.
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

        $in_http_vars['layout'] = 'two-fold-tabloid';
        $in_http_vars['columns'] = 4;

        parent::__construct($in_http_vars);

        $red_color = isset($in_http_vars['color']) ? [190, 32, 45] : 0;

        $this->weekday_header_fill_color = $red_color;
        $this->format_header_fill_color = $red_color;
        $this->subcommittee_header_fill_color = $red_color;
    }

    /********************************************************************
    */
    function DrawFrontPanel(
        $fixed_font_size,
        $left,
        $top,
        $right,
        $bottom
    ) {
        parent::DrawFrontPanel($fixed_font_size, $left, $top, $right, $bottom);
        $this->DrawSubcommittees($left, $top, $right, $bottom);
        $y = $bottom;
        $decrement = 2;

        do {
            $this->napdf_instance->SetFont($this->napdf_instance->getFontFamily(), 'I', $this->font_size - $decrement);
            $stringWidth = $this->napdf_instance->GetStringWidth(_NSLI_LIST_FOOTER);
            $cellleft = (($right + $left) - $stringWidth) / 2;
            $decrement += 0.5;
        } while ($left > $cellleft);

        $this->napdf_instance->SetXY($cellleft, $y + 0.005);
        $this->napdf_instance->Cell(0, 0.125, _NSLI_LIST_FOOTER);
    }

    /********************************************************************
    */
    function DrawSubcommittees(
        $left,
        $top,
        $right,
        $bottom
    ) {
        $y = $this->napdf_instance->GetY();

        if ($y > $this->page_margins) {
            $y += $this->page_margins;
        }

        $column_width = $right - $left;

        $fontFamily = $this->napdf_instance->getFontFamily();
        $fontSize = $this->font_size;

        $s_array = array();
        $na_dom = new DOMDocument();
        if ($na_dom) {
            if (@$na_dom->loadHTML($this->napdf_instance->call_curl("https://heartoflongislandna.org/subcommittee-meetings/"))) {
                $div_contents = $na_dom->getElementByID("meeting_times");

                if ($div_contents) {
                    $p_list = $div_contents->getElementsByTagName("p");
                    if ($p_list && $p_list->length) {
                        for ($i = 0; $i < $p_list->length; $i++) {
                            $the_item = $p_list->item($i);
                            if ($the_item) {
                                $a = null;

                                if ("first" == $the_item->getAttribute("class")) {
                                    $p_list2 = $the_item->getElementsByTagName('b');

                                    if (!$p_list2 || !$p_list2->item(0)) {
                                        $p_list2 = $the_item->getElementsByTagName('strong');
                                    }

                                    if ($p_list2 && $p_list2->item(0) && $p_list2->item(0)->nodeValue) {
                                        $a['_name'] = $p_list2->item(0)->nodeValue;
                                        $a['_description'] = '';

                                        while ($p_list->item($i + 1) && ("first" != $p_list->item($i + 1)->getAttribute("class"))) {
                                            if ($a['_description']) {
                                                $a['_description'] .= "\n";
                                            }
                                            $a['_description'] .= $p_list->item(++$i)->nodeValue;
                                        }
                                    }
                                }

                                if ($a) {
                                    array_push($s_array, $a);
                                }
                            }
                        }
                    }
                }
            }
        }

        $fill_color = isset($this->subcommittee_header_fill_color) ? $this->subcommittee_header_fill_color : 0;
        $text_color = isset($this->subcommittee_header_text_color) ? $this->subcommittee_header_text_color : 255;

        if (is_array($fill_color) && (3 == count($fill_color))) {
            $this->napdf_instance->SetFillColor($fill_color[0], $fill_color[1], $fill_color[2]);
        } else {
            $this->napdf_instance->SetFillColor($fill_color);
        }

        if (is_array($text_color) && (3 == count($text_color))) {
            $this->napdf_instance->SetTextColor($text_color[0], $text_color[1], $text_color[2]);
        } else {
            $this->napdf_instance->SetTextColor($text_color);
        }
        $headerString = _NSLI_LIST_SUBCOMMITTEE_HEADER;

        $this->napdf_instance->Rect($left, $y, ($right - $left), 0.18, "F");

        $y += 0.08;

        $this->napdf_instance->SetFont($fontFamily, 'B', 9);
        $stringWidth = $this->napdf_instance->GetStringWidth($headerString);

        $cellleft = (($right + $left) - $stringWidth) / 2;
        $this->napdf_instance->SetXY($cellleft, $y + 0.0125);
        $this->napdf_instance->Cell(0, 0, $headerString);

        $y += 0.125;

        if (is_array($s_array) && count($s_array)) {
            $heading_height = $fontSize + 1;
            $height = ($heading_height / 72) * 1.07;

            for ($c = 0; $c < count($s_array); $c++) {
                $this->napdf_instance->SetFillColor(255);
                $this->napdf_instance->SetTextColor(0);

                $this->napdf_instance->SetFont($fontFamily, 'B', $heading_height);
                $stringWidth = $this->napdf_instance->GetStringWidth($s_array[$c]['_name']);
                $cellleft = $left;
                $this->napdf_instance->SetXY($cellleft, $y + 0.005);
                $this->napdf_instance->Cell(0, $height, $s_array[$c]['_name']);
                $y += $height + .02;

                $this->napdf_instance->SetFillColor(255);
                $this->napdf_instance->SetTextColor(0);
                $this->napdf_instance->SetFont($fontFamily, '', ($fontSize));
                $this->napdf_instance->SetLeftMargin($left);
                $this->napdf_instance->SetXY($left, $y);
                $this->napdf_instance->MultiCell(($right - $left), ($fontSize) / 72, $s_array[$c]['_description'], 0, "L");
                $y = $this->napdf_instance->GetY() + 0.1;
            }
        }
    }
};
