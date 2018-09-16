<?php
/**
	\file sas_napdf.class.php
	
	\brief This file creates and dumps a Rockland meeting list in PDF form.
*/
// Get the napdf class, which is used to fetch the data and construct the file.
require_once (dirname (__FILE__).'/printableList.class.php');

define ("_FLEX_LIST_HELPLINE", "<HELPLINE>");
define ("_FLEX_LIST_ROOT_URI", "<URI>");
define ("_FLEX_LIST_CREDITS", "<CREDITS>");
define ("_FLEX_LIST_URL", "<WEB SITE>");
define ("_FLEX_LIST_BANNER_1", "<BANNER 1>");
define ("_FLEX_LIST_BANNER_2", "<BANNER 2>");
define ("_FLEX_LIST_BANNER_3", "<BANNER 3>");
define ("_FLEX_DATE_FORMAT", '\R\e\v\i\s\e\d F, Y');
define ("_FLEX_FILENAME_FORMAT", 'Printable_PDF_NA_Meeting_List_%s.pdf');
define ("_FLEX_IMAGE_POSIX_PATH", 'images/Sunburst_Cover_Logo.png');
define ("_FLEX_WEEK_STARTS", 2);

/**
	\brief	This creates and manages an instance of the napdf class, and creates
	the PDF file.
*/
class flex_napdf extends printableList {
	/********************************************************************
		\brief	The constructor for this class does a lot. It creates the instance of the napdf class, gets the data from the
		server, then sorts it. When the constructor is done, the data is ready to be assembled into a PDF.
		
		If the napdf object does not successfully get data from the server, then it is set to null.
	*/
	function __construct (  $in_http_vars	///< The HTTP parameters we'd like to send to the server.
					        ) {
		$this->helpline_string = (isset($this->helpline_string) && $this->helpline_string) ? $this->helpline_string :_FLEX_LIST_HELPLINE;   ///< This is the default string we use for the Helpline.
		$this->credits_string = (isset($this->credits_string) && $this->credits_string) ? $this->credits_string : _FLEX_LIST_CREDITS;      ///< The credits for creation of the list.
		$this->web_uri_string = (isset($this->web_uri_string) && $this->web_uri_string) ? $this->web_uri_string : _FLEX_LIST_URL;          ///< The Web site URI.
		$this->banner_1_string = (isset($this->banner_1_string) && $this->banner_1_string) ? $this->banner_1_string : _FLEX_LIST_BANNER_1;  ///< The First Banner String.
		$this->banner_2_string = (isset($this->banner_2_string) && $this->banner_2_string) ? $this->banner_2_string : _FLEX_LIST_BANNER_2;       ///< The Second Banner String.
		$this->banner_3_string = (isset($this->banner_3_string) && $this->banner_3_string) ? $this->banner_3_string : _FLEX_LIST_BANNER_3;  ///< The Third Banner String.
		$this->week_starts_1_based_int = (isset($this->week_starts_1_based_int) && $this->week_starts_1_based_int) ? $this->week_starts_1_based_int : _FLEX_WEEK_STARTS;    ///< The Day of the week (1-based integer, with 1 as Sunday) that our week starts.
		
		$this->image_path_string = (isset($this->image_path_string) && $this->image_path_string) ? $this->image_path_string : _FLEX_IMAGE_POSIX_PATH; ///< The POSIX path to the image, relative to this file.
		$this->filename = (isset($this->filename) && $this->filename) ? $this->filename : sprintf(_FLEX_FILENAME_FORMAT, date ("Y_m_d"));    ///< The output name for the file.
		$this->root_uri = (isset($this->root_uri) && $this->root_uri) ? $this->root_uri : _FLEX_LIST_ROOT_URI;                   ///< This is the default Root Server URL.
		$this->date_header_format_string = (isset($this->date_header_format_string) && $this->date_header_format_string) ? $this->date_header_format_string : _FLEX_DATE_FORMAT;    ///< This is the default string we use for the date attribution line at the top.

		$this->font = (isset($this->font) && $this->font) ? $this->font : 'Helvetica';  ///< The font we'll use. Default is Helvetica.
		
        // NOTE: The default is US Letter (inches).
		$this->units = (isset($this->units) && $this->units) ? $this->units : 'in';     ///< The measurement units (default is inches)
		$this->page_x = (isset($this->page_x) && $this->page_x) ? $this->page_x : 8.5;  ///< The width, in measurement units, of each page
        $this->page_y = (isset($this->page_y) && $this->page_y) ? $this->page_y : 11;   ///< The height, in measurement units, of each page.
        
        $this->page_margins = (isset($this->page_margins) && $this->page_margins) ? $this->page_margins : 0.25; ///< The page margins, in measurement units, of each page.

		$this->font_size = (isset($this->font_size) && $this->font_size) ? $this->font_size : 10;   ///< The font size we'll use
        $this->list_page_sections = (isset($this->list_page_sections) && $this->list_page_sections) ? $this->list_page_sections : 4;    ///< The number of sections for the page.
        $this->page_max = (isset($this->page_max) && $this->page_max) ? $this->page_max : 4;    ///< The number of pages.

        $this->twofold = FALSE;
        $this->foursection = FALSE;
        $this->orientation = 'P';
        
        if (isset($in_http_vars['layout']) && $in_http_vars['layout']) {
            if ('two-fold-tabloid' == strtolower($in_http_vars['layout'])) {
		        $this->units = 'in';		    ///< The measurement units (inches)
                $this->page_x = 8.5;	        ///< The width, in measurement units, of each page
                $this->page_y = 11;             ///< The height, in measurement units, of each page.
                $this->twofold = TRUE;          ///< We have a special layout for this, as we will fold the paper twice vertically.
                unset($in_http_vars['orientation']);    ///< We ignore these.
                unset($in_http_vars['pages']);
                $this->page_max = 5;            ///< We always have 4 pages, but we say 5, because we are cramming 2 pages into one (the cover).
                $in_http_vars['columns'] = isset($in_http_vars['columns']) ? min(4, intval($in_http_vars['columns'])) : 4;    ///< Max. 4 columns.
            } elseif ('two-fold-us-letter' == strtolower($in_http_vars['layout'])) {
		        $this->units = 'in';		    ///< The measurement units (inches)
                $this->page_x = 11;	            ///< The width, in measurement units, of each page
                $this->page_y = 8.5;            ///< The height, in measurement units, of each page.
                $this->twofold = TRUE;          ///< We have a special layout for this, as we will fold the paper twice vertically.
                $this->foursection = TRUE;
                $this->page_max = 3;            ///< We always have 2 pages, but we say 3, because we are cramming 2 pages into one (the cover).
                $this->orientation = 'L';
                $this->list_page_sections = 2;  ///< We always have 2 columns.
                unset($in_http_vars['orientation']);    ///< We ignore these.
                unset($in_http_vars['pages']);
                unset($in_http_vars['columns']);
            } elseif ('two-fold-us-legal' == strtolower($in_http_vars['layout'])) {
		        $this->units = 'in';		    ///< The measurement units (inches)
                $this->page_x = 14;	            ///< The width, in measurement units, of each page
                $this->page_y = 8.5;            ///< The height, in measurement units, of each page.
                $this->twofold = TRUE;          ///< We have a special layout for this, as we will fold the paper twice vertically.
                $this->foursection = TRUE;
                $this->page_max = 3;            ///< We always have 2 pages, but we say 3, because we are cramming 2 pages into one (the cover).
                $this->orientation = 'L';
                unset($in_http_vars['orientation']);    ///< We ignore these.
                unset($in_http_vars['pages']);
                $in_http_vars['columns'] = isset($in_http_vars['columns']) ? min(4, intval($in_http_vars['columns'])) : 4;    ///< Max. 4 columns.
            } elseif ('chapbook' == strtolower($in_http_vars['layout'])) {
		        $this->units = 'in';		    ///< The measurement units (inches)
                $this->page_x = 5.5;	        ///< The width, in inches, of each page
                $this->page_y = 8.5;            ///< The height, in inches, of each page.
                unset($in_http_vars['orientation']);    ///< We ignore the orientation.
            } elseif ('booklet' == strtolower($in_http_vars['layout'])) {
		        $this->units = 'in';	        ///< The measurement units (inches)
                $this->page_x = 4.5;	        ///< The width, in inches, of each page
                $this->page_y = 8;              ///< The height, in inches, of each page.
                unset($in_http_vars['orientation']);    ///< We ignore the orientation.
            } elseif ('usletter' == strtolower($in_http_vars['layout'])) {
		        $this->units = 'in';	        ///< The measurement units (inches)
                $this->page_x = 8.5;	        ///< The width, in inches, of each page
                $this->page_y = 11;             ///< The height, in inches, of each page.
            } elseif ('uslegal' == strtolower($in_http_vars['layout'])) {
		        $this->units = 'in';	        ///< The measurement units (inches)
                $this->page_x = 8.5;	        ///< The width, in inches, of each page
                $this->page_y = 14;             ///< The height, in inches, of each page.
            } elseif ('tabloid' == strtolower($in_http_vars['layout'])) {
		        $this->units = 'in';	        ///< The measurement units (inches)
                $this->page_x = 11;	            ///< The width, in inches, of each page
                $this->page_y = 17;             ///< The height, in inches, of each page.
            }
        }
        
        if (isset($in_http_vars['orientation']) && $in_http_vars['orientation']) {
            if ('l' == strtolower($in_http_vars['orientation'])) {
                $this->orientation = 'L';
                $temp = $this->page_x;
                $this->page_x = $this->page_y;
                $this->page_y = $temp;
            }
        }
        
        if (isset($in_http_vars['pages']) && (intval($in_http_vars['pages']) > 2)) {
            if (('booklet' == strtolower($in_http_vars['layout'])) || ('chapbook' == strtolower($in_http_vars['layout']))) {
                $page_count = intval($in_http_vars['pages']);
                $over = ($page_count > 4) ? (0 != intval($page_count % 4) ? (4 - intval($page_count % 4)) : 0) : (4 - $page_count);
                $this->page_max = $page_count + $over;
            } else {
                $page_count = intval($in_http_vars['pages']);
                $over = ($page_count > 2) ? (0 != intval($page_count % 2) ? (2 - intval($page_count % 2)) : 0) : (2 - $page_count);
                $this->page_max = $page_count + $over;
            }
        }
        
        if (isset($in_http_vars['columns']) && (intval($in_http_vars['columns']) > 0) && (intval($in_http_vars['columns']) < 9)) {
            $this->list_page_sections = intval($in_http_vars['columns']);
        }
        
		$this->sort_keys = isset($this->sort_keys) ? $this->sort_keys : array (	'weekday_tinyint' => true,			///< First, sort by weekday
                                                                                'start_time' => true,               ///< Next, the meeting start time
                                                                                'location_municipality' => true,	///< Next, the town.
                                                                                'week_starts' => $this->week_starts_1_based_int ///< Our week starts on this day
                                                                                );
        
		/// These are the parameters that we send over to the root server, in order to get our meetings.
		$this->out_http_vars = isset($this->out_http_vars) ? $this->out_http_vars : array ( 'services' => array (  ///< We will be asking for meetings in specific Service Bodies.
                                                                                                                1001,	///< SSAASC
                                                                                                                1002,	///< NASC
                                                                                                                1003,	///< ELIASC
                                                                                                                1004,	///< SSSAC
                                                                                                                1067    ///< NSLI
                                                                                                               ),
                                                                                            'sort_key' => 'time'        
                                                                                        );

		parent::__construct ($this->root_uri, $in_http_vars);
	}
	
	/********************************************************************
	*/
	function OutputPDF() {
		$this->napdf_instance->Output($this->filename, "I");
	}

	/********************************************************************
		\brief This function actually assembles the PDF. It does not output it.
		
		\returns a boolean. true if successful.
	*/
	function AssemblePDF() {
        $meeting_data = $this->napdf_instance->meeting_data;
    
        if ($meeting_data) {
            $panelpage['margin'] = $this->page_margins;
            $panelpage['height'] = $this->page_y - ($this->page_margins * 2);
            $panelpage['width'] = $this->page_x - ($this->page_margins * 2);
        
            $listpage['margin'] = $this->page_margins;
            $listpage['height'] = $this->page_y - ($this->page_margins * 2);
            $listpage['width'] = $this->page_x - ($this->page_margins * 2);
        
            // These are the actual drawing areas.
        
            // The panel that is on the back of the folded list.
            $backpanel_x_offset = $panelpage['margin'];
            $backpanel_max_x_offset = $backpanel_x_offset + $panelpage['width'];
            $backpanel_y_offset = $panelpage['margin'];
            $backpanel_max_y_offset = $backpanel_y_offset + $panelpage['height'];

            // The panel that is up front of the folded list.
            $frontpanel_x_offset = $panelpage['margin'];
            $frontpanel_max_x_offset = $frontpanel_x_offset + $panelpage['width'];
            $frontpanel_y_offset = $panelpage['margin'] / 2.0;
            $frontpanel_max_y_offset = $frontpanel_y_offset + $panelpage['height'];
                        
            foreach ($meeting_data as &$meeting) {
                if (isset($meeting['location_text']) && isset($meeting['location_street'])) {
                    $meeting['location'] = $meeting['location_text'].', '.$meeting['location_street'];
                }
            }
        
            $fixed_font_size = $this->font_size;
            $variable_font_size = isset($this->variable_font_size) ? $this->variable_font_size : $this->font_size + 2;
            
            if ($this->foursection) {
                $frontleft = ($this->page_x / 2) + $listpage['margin'];
                $frontright = $this->page_x - $this->page_margins;
                $fright = (($frontright - $frontleft) / 2) + $frontleft - $listpage['margin'];
                $listpage['width'] /= 2.0;
            } else {
                $frontleft = $frontpanel_x_offset;
                $frontright = $frontpanel_max_x_offset;
                $fright = $frontpanel_max_x_offset / 2;
            }
            
            do {
                $pages = 0;

                $this->napdf_instance = napdf::MakeNAPDF ($this->root_uri, $this->page_x, $this->page_y, $this->out_http_vars, $this->units, $this->orientation, $this->sort_keys);
                
                if ($this->napdf_instance instanceof napdf) {
                    $this->napdf_instance->sort_order_keys = $this->sort_keys;
        
                    if (is_array ($this->napdf_instance->meeting_data) && count ($this->napdf_instance->meeting_data)) {
                        $this->napdf_instance->set_sort ();
                    }
                } else {
                    $this->napdf_instance = null;
                    break;
                }
        
                $this->napdf_instance->AddPage ();
                $pages++;
                
                $frontpanel_x_offset = $frontleft;
                $frontpanel_max_x_offset = $frontright;
        
                $this->pos['end'] = FALSE;
                $this->pos['start'] = TRUE;
                $this->pos['count'] = 0;
                
                $extra_margin = $this->foursection ? $listpage['margin'] : 0;
                
                if ($this->twofold) {
                    if ($this->foursection) {
                        $this->font_size = $variable_font_size;
                        $this->DrawListPage ($listpage['margin'], $listpage['margin'], $listpage['width'] - $extra_margin, $listpage['height'], $listpage['margin'], $this->list_page_sections);
                    }
                    $this->DrawRearPanel ($fixed_font_size, $frontpanel_x_offset, $frontpanel_y_offset, $fright, $frontpanel_max_y_offset);
                    $frontpanel_x_offset = $fright + ($listpage['margin'] * 2);
                    if ($this->foursection) {
                        $frontpanel_x_offset += $listpage['margin'];
                    }
                    $frontpanel_max_x_offset = $frontright;
                }
                
                $this->DrawFrontPanel ($fixed_font_size, $frontpanel_x_offset, $frontpanel_y_offset, $frontpanel_max_x_offset, $frontpanel_max_y_offset);

                while (!$this->pos['end']) {
                    $this->napdf_instance->AddPage ();
                    $pages++;
                    $this->font_size = $variable_font_size;
                    $this->DrawListPage ($listpage['margin'], $listpage['margin'], $listpage['width'] - $extra_margin, $listpage['height'], $listpage['margin'], $this->list_page_sections);
                    if ($this->foursection) {
                        $this->DrawListPage ($frontleft, $listpage['margin'], $frontright - $extra_margin, $listpage['height'], $listpage['margin'], $this->list_page_sections);
                    }
                }
                
                $variable_font_size -= 0.125;
            } while ($pages >= $this->page_max);
        }
        
        while ($pages < ($this->page_max - 1)) {
            $this->napdf_instance->AddPage ();
            $pages++;
        }
        
        if (!$this->twofold) {
            $this->napdf_instance->AddPage ();
            $pages++;

            $this->DrawRearPanel ($fixed_font_size, $backpanel_x_offset, $backpanel_y_offset, $backpanel_max_x_offset, $backpanel_max_y_offset);
        }
        
		return TRUE;
	}
	
	/*************************** INTERFACE FUNCTIONS ***************************/
	
	/********************************************************************
	*/
	function DrawFrontPanel (   $fixed_font_size,
	                            $left,
	                            $top,
	                            $right,
	                            $bottom
	                        ) {
        $this->font_size = $fixed_font_size;
        $this->napdf_instance->SetFont ($this->font, 'B', $this->font_size + 1);

        $date = date ($this->date_header_format_string);

		$inTitleGraphic = dirname(__FILE__).'/'.$this->image_path_string;
		$titleGraphicSize = min(($right - $left) / 2, ($bottom - $top) / 2);
		
		$y = $top + ($this->page_margins / 2);

		$fontFamily = $this->napdf_instance->getFontFamily();
		$fontSize = $this->font_size - 1.5;
		
		$this->napdf_instance->SetFont ($this->font, 'B', $fontSize - 1);
		
		$stringWidth = $this->napdf_instance->GetStringWidth ($date);
		
		$cellleft = (($right + $left) / 2) - ($stringWidth / 2);
		
		$this->napdf_instance->SetXY ($cellleft, $y);

		$this->napdf_instance->Cell (0, 0, $date);
		$y += 0.1;
		
		$this->napdf_instance->SetFont ($this->font, 'B', $fontSize - 0.5);
		
		$stringWidth = $this->napdf_instance->GetStringWidth ($this->credits_string);
		
		$cellleft = (($right + $left) / 2) - ($stringWidth / 2);
		
		$this->napdf_instance->SetXY ($cellleft, $y);

		$this->napdf_instance->Cell (0, 0, $this->credits_string);
		$y += 0.2;

		$this->napdf_instance->SetFont ($this->font, 'B', ($fontSize + 7));
		$stringWidth = $this->napdf_instance->GetStringWidth ($this->banner_1_string);
		$cellleft = (($right + $left) / 2) - ($stringWidth / 2);
		
		$this->napdf_instance->SetXY ($cellleft, $y);

		$this->napdf_instance->Cell (0, 0, $this->banner_1_string);
		$y += 0.2;
		
		$this->napdf_instance->SetFont ($this->font, 'B', $fontSize + 1);
		$stringWidth = $this->napdf_instance->GetStringWidth ($this->banner_2_string.' '.$this->banner_3_string);
		$cellleft = (($right + $left) / 2) - ($stringWidth / 2);
		
		$this->napdf_instance->SetXY ($cellleft, $y);
		$this->napdf_instance->Cell (0, 0, $this->banner_2_string.' '.$this->banner_3_string);
		
		$title_left = (($right - $left) / 2) - ($titleGraphicSize / 2);
		$y += 0.125;
		$this->napdf_instance->Image ($inTitleGraphic, $left + $title_left, $y, $titleGraphicSize, $titleGraphicSize, 'PNG');

		$y += $titleGraphicSize + 0.125;

		$this->napdf_instance->SetFont ($this->font, 'B', $fontSize + 2);
		$url_string = $this->web_uri_string;
		$stringWidth = $this->napdf_instance->GetStringWidth ($url_string);
		$cellleft = (($right + $left) / 2) - ($stringWidth / 2);
		$this->napdf_instance->SetXY ($cellleft, $y);
		$this->napdf_instance->Cell (0, 0, $url_string);
		$y += 0.2;
		
		$this->napdf_instance->SetFont ($fontFamily, '', ($fontSize + 2));
		
		$stringWidth = $this->napdf_instance->GetStringWidth ($this->helpline_string);
		$cellleft = (($right + $left) / 2) - ($stringWidth / 2);
		$this->napdf_instance->SetXY ($cellleft, $y);
		$this->napdf_instance->Cell (0, 0, $this->helpline_string);

		$this->napdf_instance->SetFont ($this->font, 'B', $this->font_size + 1);
		
        $this->DrawFormats ($left, $this->napdf_instance->GetY() + 0.25, $right, $bottom, $this->napdf_instance->format_data, false, true);
	}
	
	/********************************************************************
	*/
	function DrawRearPanel (    $fixed_font_size,
	                            $left,
	                            $top,
	                            $right,
	                            $bottom
	                        ) {
        $this->font_size = $fixed_font_size;
        $this->napdf_instance->SetFont ($this->font, 'B', $this->font_size + 1);
		$this->DrawPhoneList($left, $top - ($this->page_margins / 2), $right - ($this->page_margins / 2), $bottom + ($this->page_margins / 2));
	}
};
?>