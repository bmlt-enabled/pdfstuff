<?php
/**
	\file sas_napdf.class.php
	
	\brief This file creates and dumps a Rockland meeting list in PDF form.
*/
// Get the napdf class, which is used to fetch the data and construct the file.
require_once (dirname ( __FILE__ ).'/printableList.class.php');

define ( "_PDF_NSH_LIST_HELPLINE_REGION", "Regional Helpline: (212) 929-NANA (6262)" );
define ( "_PDF_NSH_LIST_ROOT_URI", "https://bmlt.newyorkna.org/main_server/" );
define ( "_PDF_NSH_LIST_SUBCOMMITTEES", "<TBD> Area Service Committee Meetings" );
define ( "_PDF_NSH_LIST", "Meeting List Produced by the <TBD> Area Service Committee" );
define ( "_PDF_NSH_LIST_BANNER", "Narcotics Anonymous Meetings" );
define ( "_PDF_NSH_LIST_BANNER_2", "On" );
define ( "_PDF_NSH_LIST_BANNER_3", "Long Island, New York" );
define ( "_PDF_NSH_LIST_URL", "Web Site: http://<TBD>" );

/**
	\brief	This creates and manages an instance of the napdf class, and creates
	the PDF file.
*/
class flex_napdf extends printableList
{
	/********************************************************************
		\brief	The constructor for this class does a lot. It creates the instance of the napdf class, gets the data from the
		server, then sorts it. When the constructor is done, the data is ready to be assembled into a PDF.
		
		If the napdf object does not successfully get data from the server, then it is set to null.
	*/
	function __construct ( $in_http_vars	///< The HTTP parameters we'd like to send to the server.
							) {
		$this->units = 'in';		///< The measurement units (inches)
		$this->font = 'Helvetica';	///< The font we'll use

		$this->page_x = 8.5;	    ///< The width, in inches, of each page
        $this->page_y = 11;          ///< The height, in inches, of each page.
        $this->page_margins = 0.25; ///< The page margins, in inches, of each page.

		$this->font_size = 10;		///< The font size we'll use
        $this->list_page_sections = 4;     ///< The number of sections for the page.
        $this->page_max = 4;               ///< The number of pages.

        $this->twofold = FALSE;
        
        if ( isset($_GET['layout']) && $_GET['layout'] ) {
            if ('two-fold-tabloid' == strtolower($_GET['layout'])) {
                $this->twofold = TRUE;
                unset($_GET['orientation']);
                unset($_GET['pages']);
                $this->page_max = 5;
            } elseif ('booklet' == strtolower($_GET['layout'])) {
                $this->page_x = 4.5;	    ///< The width, in inches, of each page
                $this->page_y = 8;          ///< The height, in inches, of each page.
                unset($_GET['orientation']);
            } elseif ('uslegal' == strtolower($_GET['layout'])) {
                $this->page_x = 8.5;	    ///< The width, in inches, of each page
                $this->page_y = 14;          ///< The height, in inches, of each page.
            } elseif ('tabloid' == strtolower($_GET['layout'])) {
                $this->page_x = 11;	    ///< The width, in inches, of each page
                $this->page_y = 17;          ///< The height, in inches, of each page.
            } elseif ('chapbook' == strtolower($_GET['layout'])) {
                $this->page_x = 5.5;	    ///< The width, in inches, of each page
                $this->page_y = 8.5;        ///< The height, in inches, of each page.
                unset($_GET['orientation']);
            }
        }

        if ( isset($_GET['orientation']) && $_GET['orientation'] ) {
            if ('l' == strtolower($_GET['orientation'])) {
                $temp = $this->page_x;
                $this->page_x = $this->page_y;
                $this->page_y = $temp;
            }
        }
        
        if ( isset($_GET['pages']) && (intval($_GET['pages']) > 2) ) {
            $page_count = intval($_GET['pages']);
            $over = ($page_count > 4) ? (0 != intval($page_count % 4) ? (4 - intval($page_count % 4)) : 0) : (4 - $page_count);
            $this->page_max = $page_count + $over;
        }
        
        if ( isset($_GET['columns']) && (intval($_GET['columns']) > 0) && (intval($_GET['columns']) < 9) ) {
            $this->list_page_sections = intval($_GET['columns']);
        }
        
		$this->sort_keys = array (	'weekday_tinyint' => true,			///< First, sort by weekday
		                            'start_time' => true,               ///< Next, the meeting start time
									'location_municipality' => true,	///< Next, the town.
									'week_starts' => 1					///< Our week starts on Sunday (1)
									);
		
		/// These are the parameters that we send over to the root server, in order to get our meetings.
		$this->out_http_vars = array (  'services' => array (   ///< We will be asking for meetings in specific Service Bodies.
                                                            1001,	///< SSAASC
                                                            1002,	///< NASC
                                                            1003,	///< ELIASC
                                                            1004,	///< SSSAC
														    1067    ///< NSLI
											                ),
										'sort_key' => 'time'        
									);

		parent::__construct ( _PDF_NSH_LIST_ROOT_URI, $in_http_vars );
	}
	
	/********************************************************************
	*/
	function OutputPDF() {
		$d = date ( "Y_m_d" );
		$this->napdf_instance->Output( "TESTLIST_$d.pdf", "I" );
	}

	/********************************************************************
		\brief This function actually assembles the PDF. It does not output it.
		
		\returns a boolean. true if successful.
	*/
	function AssemblePDF() {
        $meeting_data = $this->napdf_instance->meeting_data;
    
        if ( $meeting_data ) {
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
            $frontpanel_y_offset = $panelpage['margin'];
            $frontpanel_max_y_offset = $frontpanel_y_offset + $panelpage['height'];
        
            // The front page has half dedicated to a single list panel.
            $frontlist_x_offset = $frontpanel_max_x_offset + $panelpage['margin'] + $listpage['margin'];
            $frontlist_max_x_offset = $frontlist_x_offset + $listpage['width'];
            $frontlist_y_offset = $listpage['margin'];
            $frontlist_max_y_offset = $frontlist_y_offset + $listpage['height'];
        
            // The front page has half dedicated to a single list panel.
            $frontlist_x_offset = $frontpanel_max_x_offset + $panelpage['margin'] + $listpage['margin'];
            $frontlist_max_x_offset = $frontlist_x_offset + $listpage['width'];
            $frontlist_y_offset = $listpage['margin'];
            $frontlist_max_y_offset = $frontlist_y_offset + $listpage['height'];
        
            // The back page has two list panels.
            $backlist_page_1_x_offset = $listpage['margin'];
            $backlist_page_1_max_x_offset = $backlist_page_1_x_offset + $listpage['width'];
            $backlist_page_1_y_offset = $listpage['margin'];
            $backlist_page_1_max_y_offset = $backlist_page_1_y_offset + $listpage['height'];
        
            $backlist_page_2_x_offset = $backlist_page_1_max_x_offset + ($listpage['margin'] * 2);
            $backlist_page_2_max_x_offset = $backlist_page_2_x_offset + $listpage['width'];
            $backlist_page_2_y_offset = $listpage['margin'];
            $backlist_page_2_max_y_offset = $backlist_page_2_y_offset + $listpage['height'];
        
            global $columns, $maxwidth, $fSize, $y;
            $maxwidth = $listpage['width'] + 1;
            $columns = "";
            $fSize = $this->font_size;
                        
            foreach ($meeting_data as &$meeting) {
                if (isset($meeting['location_text']) && isset($meeting['location_street'])) {
                    $meeting['location'] = $meeting['location_text'].', '.$meeting['location_street'];
                }
            }
        
            $fixed_font_size = $this->font_size;
            $variable_font_size = $this->font_size + 2;
            
            $frontleft = $frontpanel_x_offset;
            $frontright = $frontpanel_max_x_offset;
            $fright = $frontpanel_max_x_offset / 2;
            
            do {
                $pages = 0;

                $this->napdf_instance = napdf::MakeNAPDF ( _PDF_NSH_LIST_ROOT_URI, $this->page_x, $this->page_y, $this->out_http_vars, $this->units, $this->orientation, $this->sort_keys );
                if ( !($this->napdf_instance instanceof napdf) )
                    {
                    $this->napdf_instance = null;
                    break;
                    }
                
                $this->napdf_instance->AddPage ( );
                $pages++;
                $frontpanel_x_offset = $frontleft;
                $frontpanel_max_x_offset = $frontright;
        
                if ($this->twofold) {
                    $this->DrawRearPanel ( $fixed_font_size, $frontpanel_x_offset, $frontpanel_y_offset, $fright, $frontpanel_max_y_offset );
                    $frontpanel_x_offset += $fright;
                    $frontpanel_max_x_offset = $frontpanel_x_offset + $fright - $this->page_margins;
                }
                
                $this->DrawFrontPanel ( $fixed_font_size, $frontpanel_x_offset, $frontpanel_y_offset, $frontpanel_max_x_offset, $frontpanel_max_y_offset );

                $this->pos['end'] = FALSE;
                $this->pos['start'] = TRUE;
                $this->pos['count'] = 0;
                
                while ( !$this->pos['end'] )
                    {
                    $this->napdf_instance->AddPage ( );
                    $pages++;
                    $this->font_size = $variable_font_size;
                    $this->DrawListPage ( $listpage['margin'], $listpage['margin'], $listpage['width'], $listpage['height'], $listpage['margin'], $this->list_page_sections );
                    }
                
                $variable_font_size -= 0.1;
            } while ($pages >= $this->page_max);
        }
        
        while ($pages < ($this->page_max - 1)) {
            $this->napdf_instance->AddPage ( );
            $pages++;
        }
        
        if (!$this->twofold) {
            $this->napdf_instance->AddPage ( );
            $pages++;

            $this->DrawRearPanel ( $fixed_font_size, $backpanel_x_offset, $backpanel_y_offset, $backpanel_max_x_offset, $backpanel_max_y_offset );
        }
        
		return TRUE;
	}
	
	/*************************** INTERFACE FUNCTIONS ***************************/
	
	/********************************************************************
	*/
	function DrawFrontPanel ( $fixed_font_size, $left, $top, $right, $bottom )
	{
        $this->font_size = $fixed_font_size;
        $this->napdf_instance->SetFont ( $this->font, 'B', $this->font_size + 1 );

        $date = date ( '\R\e\v\i\s\e\d F, Y' );

		$inTitleGraphic = dirname(__FILE__)."/images/Sunburst_Cover_Logo.png";
		$titleGraphicSize = min(($right - $left) / 2, ($bottom - $top) / 2);
		
		$y = $top + $this->page_margins;

		$fontFamily = $this->napdf_instance->getFontFamily();
		$fontSize = $this->font_size - 1.5;
		
		$this->napdf_instance->SetFont ( $this->font, 'B', $fontSize - 1 );
		
		$stringWidth = $this->napdf_instance->GetStringWidth ( $date );
		
		$cellleft = (($right + $left) / 2) - ($stringWidth / 2);
		
		$this->napdf_instance->SetXY ( $cellleft, $y );

		$this->napdf_instance->Cell ( 0, 0, $date );
		$y += 0.1;
		
		$this->napdf_instance->SetFont ( $this->font, 'B', $fontSize - 0.5 );
		
		$stringWidth = $this->napdf_instance->GetStringWidth ( _PDF_NSH_LIST );
		
		$cellleft = (($right + $left) / 2) - ($stringWidth / 2);
		
		$this->napdf_instance->SetXY ( $cellleft, $y );

		$this->napdf_instance->Cell ( 0, 0, _PDF_NSH_LIST );
		$y += 0.2;

		$this->napdf_instance->SetFont ( $this->font, 'B', ($fontSize + 7) );
		$stringWidth = $this->napdf_instance->GetStringWidth ( _PDF_NSH_LIST_BANNER );
		$cellleft = (($right + $left) / 2) - ($stringWidth / 2);
		
		$this->napdf_instance->SetXY ( $cellleft, $y );

		$this->napdf_instance->Cell ( 0, 0, _PDF_NSH_LIST_BANNER );
		$y += 0.2;
		
		$this->napdf_instance->SetFont ( $this->font, 'B', $fontSize + 1 );
		$stringWidth = $this->napdf_instance->GetStringWidth ( _PDF_NSH_LIST_BANNER_2.' '._PDF_NSH_LIST_BANNER_3 );
		$cellleft = (($right + $left) / 2) - ($stringWidth / 2);
		
		$this->napdf_instance->SetXY ( $cellleft, $y );
		$this->napdf_instance->Cell ( 0, 0, _PDF_NSH_LIST_BANNER_2.' '._PDF_NSH_LIST_BANNER_3 );
		
		$title_left = (($right - $left) / 2) - ($titleGraphicSize / 2);
		$y += 0.125;
		$this->napdf_instance->Image ( $inTitleGraphic, $left + $title_left, $y, $titleGraphicSize, $titleGraphicSize, 'PNG' );

		$this->napdf_instance->SetFont ( $fontFamily, 'B', ($fontSize + 4.75) );
		
		$y += $titleGraphicSize + 0.125;

		$url_string = _PDF_NSH_LIST_URL;
		$stringWidth = $this->napdf_instance->GetStringWidth ( $url_string );
		$cellleft = (($right + $left) / 2) - ($stringWidth / 2);
		$this->napdf_instance->SetXY ( $cellleft, $y );
		$this->napdf_instance->Cell ( 0, 0, $url_string );
		$y += 0.2;
		
		$this->napdf_instance->SetFont ( $fontFamily, '', ($fontSize + 3) );
		
		$stringWidth = $this->napdf_instance->GetStringWidth ( _PDF_NSH_LIST_HELPLINE_REGION );
		$cellleft = (($right + $left) / 2) - ($stringWidth / 2);
		$this->napdf_instance->SetXY ( $cellleft, $y );
		$this->napdf_instance->Cell ( 0, 0, _PDF_NSH_LIST_HELPLINE_REGION );

		$this->napdf_instance->SetFont ( $this->font, 'B', $this->font_size + 1 );
		
        $this->DrawFormats ( $left, $this->napdf_instance->GetY() + 0.25, $right, $bottom, $this->napdf_instance->format_data, false, true );
	}
	
	/********************************************************************
	*/
	function DrawRearPanel ( $fixed_font_size, $left, $top, $right, $bottom )
	{
        $this->font_size = $fixed_font_size;
        $this->napdf_instance->SetFont ( $this->font, 'B', $this->font_size + 1 );
		$this->DrawPhoneList( $left, $top, $right, $bottom );
	}
};
?>