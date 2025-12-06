<?php

/**
    \file printableList.class.php

    \brief This file contains the exported interface for subclasses
*/

/**
    This is the interface, describing the exported functions.
*/
require_once(dirname(__FILE__) . '/napdf.class.php');
interface IPrintableList
{
    function AssemblePDF();
    function OutputPDF();
};

/**
    This is the base class for use by specialized printing classes.
*/
class printableList
{
    public $page_x = 8.5;      ///< The width, in inches, of each page
    public $page_y = 11;       ///< The height, in inches, of each page.
    public $units = 'in';      ///< The measurement units (inches)
    public $orientation = 'P'; ///< The orientation (portrait)
    /// These are the sort keys, for sorting the meetings before display
    public $sort_keys = array ();
    /// These are the parameters that we send over to the root server, in order to get our meetings.
    public $out_http_vars = array ();
    /// This contains the instance of napdf that we use to extract our data from the server, and to hold onto it.
    public $napdf_instance = null;
    public $font = 'Times'; ///< The font we'll use
    public $font_size = 9;      ///< The font size we'll use
    public $weekday_names = array ("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");
    public $weekday_names_short = array ("Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat");
    public $continued = "Continued";
    public $continued_short = "Cont'd";
    public $format_header = "FORMAT LEGEND";
    public $pos = array ('start' => 1, 'end' => '', 'count' => 0, 'y' => 0, 'weekday' => 1);
    public $formats = 0;
    public $list_name_header = 'Name';
    public $list_number_header = 'Phone #';

    /**
        \brief  The constructor for this class does a lot. It creates the instance of the napdf class, gets the data from the
        server, then sorts it. When the constructor is done, the data is ready to be assembled into a PDF.

        If the napdf object does not successfully get data from the server, then it is set to null.
    */
    protected function __construct(
        $inRootURI,     ///< The Root Server URI.
        $in_http_vars,  ///< The HTTP parameters we'd like to send to the server.
        $in_lang_search = null  ///< An array of language enums, used to extract the correct format codes.
    ) {
        $this->napdf_instance = napdf::MakeNAPDF($inRootURI, $this->page_x, $this->page_y, $this->out_http_vars, $this->units, $this->orientation, 'Helvetica', $this->sort_keys, $in_lang_search);
        if (!($this->napdf_instance instanceof napdf)) {
            error_log('printableList: Failed to create napdf instance');
            $this->napdf_instance = null;
        } else {
            error_log('printableList: Successfully created napdf instance with ' . count($this->napdf_instance->meeting_data) . ' meetings');
        }
    }

    /*************************** INTERFACE FUNCTIONS ***************************/
    /********************************************************************
    */
    function DrawFormats(
        $left,
        $y,
        $right,
        $bottom,
        $formats,
        $useDescription = true,
        $twoColumns = false
    ) {
        $sorter = function ($a, $b) {
            return ($a['key_string'] < $b['key_string']) ? -1 : (($a['key_string'] > $b['key_string']) ? 1 : 0);
        };

        usort($formats, $sorter);

        $fontFamily = $this->napdf_instance->getFontFamily();
        $font_size = $this->font_size;

        $headerString = $this->format_header;

        $this->napdf_instance->SetFont($fontFamily, 'B', 9);
        $stringWidth = $this->napdf_instance->GetStringWidth($headerString);

        $cellleft = (($right + $left) - $stringWidth) / 2;

        $this->napdf_instance->SetFillColor(0);
        $this->napdf_instance->SetTextColor(255);

        $this->napdf_instance->Rect($left, $y, ($right - $left), 0.18, "F");

        $y += 0.08;

        $this->napdf_instance->SetXY($cellleft, $y + 0.0125);
        $this->napdf_instance->Cell(0, 0, $headerString);

        $this->napdf_instance->SetFillColor(255);
        $this->napdf_instance->SetTextColor(0);

        $y += $this->page_margins;

        $count = count($formats);
        $fSize = $this->font_size - 2;

        $this->napdf_instance->SetY($y);

        $column_width = $right - $left;
        $column_width /= ($twoColumns ? 2.0 : 1.0);
        $left_margin = $left;
        $first_right_column_index = $twoColumns ? intval(($count + 1) / 2) : -1;
        $index = 0;
        $maxY = 0;

        foreach ($formats as $format) {
            if ($index++ == $first_right_column_index) {
                $left_margin = $left + $column_width;
                $this->napdf_instance->SetY($y);
            }
            $this->napdf_instance->SetFont($fontFamily, 'B', $fSize);
            $this->napdf_instance->SetLeftMargin($left_margin);
            $str = $format['key_string'];
            $this->napdf_instance->SetX($left_margin);
            $this->napdf_instance->Cell(0, 0.13, $str);
            $this->napdf_instance->SetFont($fontFamily, '', $fSize);
            $str = $format[($useDescription ? 'description_string' : 'name_string')];
            $this->napdf_instance->SetLeftMargin($left_margin + ($this->page_margins * 1.15));
            $this->napdf_instance->SetX($left_margin + ($this->page_margins * 1.15));
            $this->napdf_instance->MultiCell($column_width - ($this->page_margins * 1.15), 0.13, $str, 0, 'L');
            $this->napdf_instance->SetY($this->napdf_instance->GetY() + 0.01);
            $maxY = max($maxY, $this->napdf_instance->GetY());
        }

        $this->napdf_instance->SetY($maxY);
    }


    /********************************************************************
    */
    function DrawPhoneList(
        $left,
        $top,
        $right,
        $bottom
    ) {
        $y = $top;

        $y += 0.25;

        $fontFamily = $this->napdf_instance->getFontFamily();
        $fontSize = $this->font_size - 1.5;
        $this->napdf_instance->SetFillColor(255);
        $this->napdf_instance->SetTextColor(0);
        $this->napdf_instance->SetDrawColor(0);

        $cellleft = $left;
        $this->napdf_instance->SetXY($cellleft, $y);
        $st_width = $this->napdf_instance->GetStringWidth($this->list_name_header);
        $this->napdf_instance->Cell(0, 0, $this->list_name_header);
        $stringWidth = $this->napdf_instance->GetStringWidth($this->list_number_header);
        $this->napdf_instance->SetXY($right - $stringWidth, $y);
        $this->napdf_instance->Cell(0, 0, $this->list_number_header);
        $y += $this->page_margins;

        $this->napdf_instance->SetFont($fontFamily, '', $fontSize);

        $this->napdf_instance->SetLineWidth(0.02);
        $this->napdf_instance->Line($left + 0.0625, $y, $right, $y);

        while ($y < ($bottom - 0.25)) {
            $y += 0.3;
            $this->napdf_instance->Line($left + 0.0625, $y, $right, $y);
        }
    }

    /********************************************************************
    */
    function GetParsedHTMLFile($inURI)
    {
        $ret = array();
        $na_dom = new DOMDocument();
        if ($na_dom) {
            if (@$na_dom->loadHTML($this->napdf_instance->call_curl($inURI))) {
                $div_list = $na_dom->getElementsByTagName("div");
                if ($div_list && $div_list->length) {
                    for ($i = 0; $i < $div_list->length; $i++) {
                        $the_item = $div_list->item($i);
                        if ($the_item) {
                            $retObject = array('header' => '', 'body' => array());
                            $header_list = $the_item->getElementsByTagName("h1");
                            if ($header_list && $header_list->length) {
                                $retObject['header'] = $header_list->item(0)->nodeValue;
                            }
                            $body_list = $the_item->getElementsByTagName("p");
                            if ($body_list && $body_list->length) {
                                for ($i2 = 0; $i2 < $body_list->length; $i2++) {
                                    $bodyItem = $body_list->item($i2);
                                    array_push($retObject['body'], $bodyItem->nodeValue);
                                }
                            }

                            array_push($ret, $retObject);
                        }
                    }
                }
            }
        }

        return $ret;
    }

    /********************************************************************
    */
    function DrawListPage(
        $left,
        $top,
        $right,
        $bottom,
        $margin,
        $columns
    ) {
        $meetings = $this->napdf_instance->meeting_data;
        $count_max = count($meetings);

        $this->napdf_instance->SetFont($this->font, '', $this->font_size - 5);
        $fontFamily = $this->napdf_instance->getFontFamily();
        $fontSize = $this->font_size - 1.5;

        if (1 == $columns) {
            $right += $margin;
            $margin = 0;
            $column_width = $right - $left;
        } else {
            $margin_slop = max(0, ($columns - 2) * $margin);
            $column_width = (($right - $left) - $margin_slop) / $columns;
        }

        $this->napdf_instance->SetXY($left, $top);

        $heading_height = 9;
        $height = ($heading_height / 72) + 0.01;
        $gap2 = 0.02;

        $fSize = $fontSize / 70;
        $fSizeSmall = ($fontSize - 1) / 70;

        $height_one_meeting = 0;

        $y_offset = $bottom - $fSize;

        $current_day = $this->pos['weekday'];

        $extra_height = $height + 0.05;

        if ($this->pos['start']) {
            $current_day = -100;
            $this->pos['count'] = 0;
            $this->pos['start'] = false;
        }

        $average_meeting_height_accum = 0;
        $meeting_count_column = 0;

        for ($column = 0; ($column < $columns) && !$this->pos['end']; $column++) {
            $y = $top;
            $column_left = $left + (($margin + $column_width) * $column);

            while (!$this->pos['end'] && (($y + ((0 < $meeting_count_column) ? $average_meeting_height_accum / $meeting_count_column : 0)) < $y_offset)) {
                $meeting = $meetings[intval($this->pos['count'])];
                $meeting_day = isset($meeting['weekday_tinyint']) ? intval($meeting['weekday_tinyint']) : 1;

                $its_a_new_day = $meeting_day != $current_day;

                if ($its_a_new_day || ($y == $top)) {
                    $continued = true;

                    if ($current_day != $meeting_day) {
                        $current_day = $meeting_day;
                        $this->pos['weekday'] = $current_day;
                        $continued = false;
                    }

                    $y += ($y == $top) ? 0 : 0.075;
                    $y = $this->DrawWeekdayHeader($column_left, $y, $column_width, $current_day, $continued);
                } else {
                    $this->napdf_instance->SetDrawColor(0);
                    $y += 0.05;
                    $this->napdf_instance->Line($column_left, $y, $column_left + $column_width, $y);
                }

                $y += 0.05;
                $y_start = $y;
                $y = $this->DrawOneMeeting($column_left, $y, $column_width, $meeting);

                $average_meeting_height_accum += ($y - $y_start);
                $meeting_count_column++;

                if (++$this->pos['count'] == $count_max) {
                    $this->pos['end'] = 1;
                }
            }
        }
    }

    /********************************************************************
    */
    function DrawWeekdayHeader(
        $left,
        $top,
        $column_width,
        $weekday,
        $continued = false
    ) {
        $heading_height = 8;
        $height = 0.15;

        $fontFamily = $this->napdf_instance->getFontFamily();

        $this->napdf_instance->SetFillColor(0);
        $this->napdf_instance->SetTextColor(255);
        $this->napdf_instance->Rect($left, $top, $column_width, $height, "F");

        $header = $this->weekday_names[$weekday - 1];
        $header .= $continued ? ' (' . $this->continued . ')' : '';

        $this->napdf_instance->SetFont($fontFamily, 'B', $heading_height);
        $stringWidth = $this->napdf_instance->GetStringWidth($header);

        if ($stringWidth >= ($column_width - 0.125)) {
            $this->napdf_instance->SetFont($fontFamily, 'B', $heading_height - 1);
            $header = $this->weekday_names_short[$weekday - 1];
            $header .= $continued ? ' (' . $this->continued . ')' : '';

            $stringWidth = $this->napdf_instance->GetStringWidth($header);
        }

        if ($stringWidth >= ($column_width - 0.125)) {
            $this->napdf_instance->SetFont($fontFamily, 'B', $heading_height - 2);
            $header = $this->weekday_names_short[$weekday - 1];
            $header .= $continued ? ' (' . $this->continued_short . ')' : '';

            $stringWidth = $this->napdf_instance->GetStringWidth($header);
        }

        $cellleft = (($column_width - $stringWidth) / 2) + $left;

        $this->napdf_instance->SetXY($cellleft, $top);
        $this->napdf_instance->Cell(0, $height, $header);

        return $top + $height;
    }

    /********************************************************************
    */
    function DrawOneMeeting(
        $left,
        $top,
        $column_width,
        $meeting
    ) {
        $fontFamily = $this->napdf_instance->getFontFamily();
        $fontSize = $this->font_size - 1.5;

        $fSize = $fontSize / 70;
        $fSizeSmall = ($fontSize - 1) / 70;

        $this->napdf_instance->SetFillColor(255);
        $this->napdf_instance->SetTextColor(0);

        $this->napdf_instance->SetFont($fontFamily, 'B', $fontSize);

        $display_string = $meeting['location_municipality'];
        $this->napdf_instance->SetY($top);
        $this->napdf_instance->SetX($left);
        $this->napdf_instance->MultiCell($column_width, $fSize, mb_convert_encoding($display_string, 'ISO-8859-1', 'UTF-8'), 0, 'L');

        $display_string = '';

        if (isset($meeting['start_time'])) {
            $display_string = self::translate_time($meeting['start_time']);
        }

        if (isset($meeting['duration_time']) && $meeting['duration_time'] && ('01:30:00' != $meeting['duration_time'])) {
            $display_string .= " (" . self::translate_duration($meeting['duration_time']) . ")";
        }

        $this->napdf_instance->SetX($left);

        $this->napdf_instance->MultiCell($column_width, $fSize, mb_convert_encoding($display_string, 'ISO-8859-1', 'UTF-8'));

        $display_string = isset($meeting['meeting_name']) ? $meeting['meeting_name'] : '';

        if (isset($meeting['formats'])) {
            $display_string .= " (" . $this->RearrangeFormats($meeting['formats']) . ")";
        }

        $this->napdf_instance->SetX($left);

        $this->napdf_instance->MultiCell($column_width, $fSize, mb_convert_encoding($display_string, 'ISO-8859-1', 'UTF-8'), 0, 'L');

        $this->napdf_instance->SetFont($fontFamily, '', $fontSize);

        if (isset($meeting['location_neighborhood']) && $meeting['location_neighborhood']) {
            $display_string = $meeting['location_neighborhood'];
            $this->napdf_instance->SetX($left);
            $this->napdf_instance->MultiCell($column_width, $fSize, mb_convert_encoding($display_string, 'ISO-8859-1', 'UTF-8'), 0, 'L');
        }

        $display_string = '';

        if (isset($meeting['location_text']) && $meeting['location_text']) {
            $display_string .= $meeting['location_text'];
        }

        if (isset($meeting['location_info']) && $meeting['location_info']) {
            if ($display_string) {
                $display_string .= ', ';
            }

            $display_string .= " (" . $meeting['location_info'] . ")";
        }

        if ($display_string) {
            $display_string .= ', ';
        }

        $display_string .= isset($meeting['location_info']) ? $meeting['location_street'] : '';

        $this->napdf_instance->SetX($left);
        $this->napdf_instance->MultiCell($column_width, $fSize, mb_convert_encoding($display_string, 'ISO-8859-1', 'UTF-8'), 0, 'L');

        if (isset($meeting['description_string']) && $meeting['description_string']) {
            if ($desc) {
                $desc .= ", ";
            }

            $desc = $meeting['description_string'];
        }

        $desc = '';

        if (isset($meeting['comments']) && $meeting['comments']) {
            if ($desc) {
                $desc .= ", ";
            }

            $desc .= $meeting['comments'];
        }

        $desc = preg_replace("/[\n|\r]/", ", ", $desc);
        $desc = preg_replace("/,\s*,/", ",", $desc);
        $desc = stripslashes(stripslashes($desc));

        if ($desc) {
            $extra = ($fSizeSmall * 3);
            $this->napdf_instance->SetFont($fontFamily, 'I', $fontSize - 1);
            $this->napdf_instance->SetX($left);
            $this->napdf_instance->MultiCell($column_width, $fSizeSmall, mb_convert_encoding($desc, 'ISO-8859-1', 'UTF-8'));
        }

        return $this->napdf_instance->GetY();
    }

    /********************************************************************
    */
    function RearrangeFormats($inFormats)
    {
        $inFormats = explode(",", $inFormats);

        if (!in_array("C", $inFormats) && !in_array("O", $inFormats)) {
            array_push($inFormats, "C");
        }

        if (!in_array("BK", $inFormats) && ((in_array("BT", $inFormats) || in_array("IW", $inFormats) || in_array("JT", $inFormats) || in_array("SG", $inFormats)))) {
            array_push($inFormats, "BK");
        }

        sort($inFormats);

        $tFormats = $inFormats;

        $inFormats = array();

        foreach ($tFormats as $format) {
            $format = trim($format);
            if ($format) {
                array_push($inFormats, $format);
            }
        }

        return join(",", $inFormats);
    }

    /********************************************************************
        \brief This function actually assembles the PDF. It does not output it.

        \returns a boolean. true if successful.
    */
    function AssemblePDF()
    {
        return false;
    }

    /********************************************************************
    */
    function OutputPDF()
    {
    }

    /********************************************************************
    */
    static function break_meetings_by_day($in_meetings_array)
    {
        $last_day = -1;
        $meetings_day = array();

        foreach ($in_meetings_array as $meeting) {
            if ($meeting['weekday_tinyint'] != $last_day) {
                $last_day = $meeting['weekday_tinyint'] - 1;
            }

            $meetings_day[$last_day][] = $meeting;
        }

        return $meetings_day;
    }

    /********************************************************************
    */
    static function sort_cmp(
        $a,
        $b
    ) {
        $order_array = array(   0 => "O", 1 => "C",
                                2 => "ES", 3 => "B", 4 => "M", 5 => "W", 6 => "GL", 7 => "YP", 8 => "BK", 9 => "IP", 10 => "Pi", 11 => "RF", 12 => "Rr",
                                13 => "So", 14 => "St", 15 => "To", 16 => "Tr", 17 => "OE", 18 => "D", 19 => "SD", 20 => "TW", 21 => "IL",
                                22 => "BL", 23 => "IW", 24 => "BT", 25 => "SG", 26 => "JT",
                                27 => "Ti", 28 => "Sm", 29 => "NS", 30 => "CL", 31 => "CS", 32 => "NC", 33 => "SC", 34 => "CH", 35 => "SL", 36 => "WC");

        if (in_array($a, $order_array) || in_array($b, $order_array)) {
            return (array_search($a, $order_array) < array_search($b, $order_array)) ? -1 : 1;
        } else {
            return 0;
        }
    }

    /********************************************************************
    */
    static function translate_time($in_time_string)
    {
        $split = explode(":", $in_time_string);
        if ($in_time_string == "12:00:00") {
            return "Noon";
        } elseif (($split[0] == "23") && (intval($split[1]) > 45)) {
            return "Midnight";
        } else {
            return date("g:i A", strtotime($in_time_string));
        }
    }

    /********************************************************************
    */
    static function translate_duration($in_time_string)
    {
        $t = explode(":", $in_time_string);
        $hours = intval($t[0]);
        $minutes = intval($t[1]);

        $ret = '';

        if ($hours) {
            $ret .= "$hours hour";

            if ($hours > 1) {
                $ret .= "s";
            }

            if ($minutes) {
                $ret .= " and ";
            }
        }

        if ($minutes) {
            $ret .= "$minutes minutes";
        }

        return $ret;
    }

    /********************************************************************
    */
    function DrawFoldGuides($inMargins)
    {
        $size = (($this->orientation == 'L') ? $this->page_y : $this->page_x) / $this->page_sections;
        $this->napdf_instance->SetDrawColor(200);

        for ($index = 1; $index < $this->page_sections; $index++) {
            $cellleft = $size * $index;
            $celltop = $inMargins ;
            $cellBottom = (($this->orientation == 'L') ? $this->page_x : $this->page_y) - $inMargins;

            $this->napdf_instance->SetLineWidth(0.005);
            $this->napdf_instance->Line($cellleft, $celltop, $cellleft, $cellBottom);
        }

        $this->napdf_instance->SetDrawColor(0);
    }
};
