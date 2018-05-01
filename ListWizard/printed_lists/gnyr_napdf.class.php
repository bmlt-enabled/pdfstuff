<?php
/**
	\file sas_napdf.class.php
	
	\brief This file creates and dumps a Rockland meeting list in PDF form.
*/
// Get the napdf class, which is used to fetch the data and construct the file.
require_once (dirname (__FILE__).'/flex_napdf.class.php');

define ("_GNYR_LIST_HELPLINE", "Regional Helpline: (212) 929-NANA (6262)");
define ("_GNYR_LIST_ROOT_URI", "https://bmlt.newyorkna.org/main_server/");
define ("_GNYR_LIST_CREDITS", "Meeting List Printed by the Greater New York Region");
define ("_GNYR_LIST_URL", "Web Site: https://newyorkna.org");
define ("_GNYR_LIST_BANNER_1", "NA Meetings");
define ("_GNYR_LIST_BANNER_2", "In");
define ("_GNYR_LIST_BANNER_3", "Downstate New York");
define ("_GNYR_DATE_FORMAT", '\R\e\v\i\s\e\d F, Y');
define ("_GNYR_FILENAME_FORMAT", 'Printable_PDF_NA_Meeting_List_%s.pdf');
define ("_GNYR_IMAGE_POSIX_PATH", 'images/NYNALogo.png');
define ("_GNYR_WEEK_STARTS", 2);

/**
	\brief	This creates and manages an instance of the napdf class, and creates
	the PDF file.
*/
class gnyr_napdf extends flex_napdf {
	/********************************************************************
		\brief	The constructor for this class does a lot. It creates the instance of the napdf class, gets the data from the
		server, then sorts it. When the constructor is done, the data is ready to be assembled into a PDF.
		
		If the napdf object does not successfully get data from the server, then it is set to null.
	*/
	function __construct (  $in_http_vars	///< The HTTP parameters we'd like to send to the server.
					        ) {
		$this->helpline_string = _GNYR_LIST_HELPLINE;       ///< This is the default string we use for the Helpline.
		$this->credits_string = _GNYR_LIST_CREDITS;         ///< The credits for creation of the list.
		$this->web_uri_string = _GNYR_LIST_URL;             ///< The Web site URI.
		$this->banner_1_string = _GNYR_LIST_BANNER_1;       ///< The First Banner String.
		$this->banner_2_string = _GNYR_LIST_BANNER_2;       ///< The Second Banner String.
		$this->banner_3_string = _GNYR_LIST_BANNER_3;       ///< The Third Banner String.
		$this->week_starts_1_based_int = _GNYR_WEEK_STARTS; ///< The Day of the week (1-based integer, with 1 as Sunday) that our week starts.
		
		$this->image_path_string = _GNYR_IMAGE_POSIX_PATH;  ///< The POSIX path to the image, relative to this file.
		$this->filename = sprintf(_GNYR_FILENAME_FORMAT, date ("Y_m_d"));  ///< The output name for the file.
		$this->root_uri = _GNYR_LIST_ROOT_URI;                  ///< This is the default Root Server URL.
		$this->date_header_format_string = _GNYR_DATE_FORMAT;   ///< This is the default string we use for the date attribution line at the top.

		$this->font = 'Helvetica';	    ///< The font we'll use.
        
		$this->sort_keys = array (	'weekday_tinyint' => true,			///< First, sort by weekday
		                            'start_time' => true,               ///< Next, the meeting start time
									'location_municipality' => true,	///< Next, the town.
									'week_starts' => $this->week_starts_1_based_int ///< Our week starts on this day
									);
		
		/// These are the parameters that we send over to the root server, in order to get our meetings.
		$this->out_http_vars = array ( 'services' => array (  ///< We will be asking for meetings in specific Service Bodies.
                                                            1,		///< GNYR
                                                            2,		///< ENYR
                                                            1001,	///< SASASC
                                                            1002,	///< NASC
                                                            1003,	///< ELIASC
                                                            1004,	///< SSASC
                                                            1005,	///< BxASC
                                                            1006,	///< BkASC
                                                            1007,	///< KBASC
                                                            1008,	///< MahASC
                                                            1010,	///< NYCASC
                                                            1011,	///< OAASC
                                                            1012,	///< RASC
                                                            1013,	///< SIASC
                                                            1014,	///< WASC
                                                            1015,	///< Metro Area ASC
                                                            1016,	///< QASC
                                                            1017,	///< WQASC
                                                            1045,	///< LHVASC
                                                            1064,   ///< South Jamaica ASC
														    1067    ///< NSLI
											               ),
										'sort_key' => 'time'        
									);

		parent::__construct ($in_http_vars);
	}
	
	/**
		\brief	This is a static callback function to be used for sorting the multi-dimensional meeting_data
				array. It uses the sort_order_keys array to determine the sort.
				
		\returns an integer. -1 if a < b, 0 if a == b, or 1 if a > b.
	*/
	static function sort_meeting_data_callback_ny (	&$in_a,		///< The first meeting array to compare
													&$in_b		///< The second meeting array to compare
													)
	{
		$ret = 0;
		
		if ( is_array ( $in_a ) && is_array ( $in_b ) && is_array ( napdf::$sort_order_keys ) )
			{
			// We reverse the array, in order to sort from least important to most important.
			$sort_keys = array_reverse ( napdf::$sort_order_keys, true );

			foreach ( $sort_keys as $key => $value )
				{
				if ( isset ( $in_a[$key] ) && isset ( $in_b[$key] ) )
					{
					$val_a = trim ( $in_a[$key] );
					$val_b = trim ( $in_b[$key] );

					if ( ('weekday_tinyint' == $key) && (napdf::$week_starts > 1) && (napdf::$week_starts < 8) )
						{
						$val_a -= napdf::$week_starts;

						if ( $val_a < 0 )
							{
							$val_a += 8;
							}
						else
							{
							$val_a += 1;
							}
						
						$val_b -= napdf::$week_starts;
						
						if ( $val_b < 0 )
							{
							$val_b += 8;
							}
						else
							{
							$val_b += 1;
							}
						}

					// We know a few keys already, and we can determine how the sorting goes from there.
					switch ( $key )
						{
						case 'start_time':
						case 'duration_time':
							$val_a = strtotime ( $val_a );
							$val_b = strtotime ( $val_b );
						case 'weekday_tinyint':
						case 'id_bigint':
						case 'shared_group_id_bigint':
						case 'service_body_bigint':
							$val_a = intval ( $val_a );
							$val_b = intval ( $val_b );
						case 'longitude':
						case 'latitude':
							if ( $val_a > $val_b )
								{
								$ret = 1;
								}
							elseif ( $val_b > $val_a )
								{
								$ret = -1;
								}
						break;
						
						default:
							// We ignore blank values
							if ( strlen ( $val_a ) && strlen ( $val_b ) )
								{
								$tmp = strcmp ( strtolower ( $val_a ), strtolower ( $val_b ) );
								
								if ( $tmp != 0 )
									{
									$ret = $tmp;
									}
								}
						break;
						}
					}
				
				if ( !$value )
					{
					$ret = -$ret;
					}
				}
			
			$county_a =ucwords ( strtolower ( trim ( $in_a['location_city_subsection'] ) ) );
			if ( !$county_a )
				{
				$county_a = ucwords ( strtolower ( trim ( $in_a['location_sub_province'] ) ) );
				}
			
			$county_b = ucwords ( strtolower ( trim ( $in_b['location_city_subsection'] ) ) );
			if ( !$county_b )
				{
				$county_b = ucwords ( strtolower ( trim ( $in_b['location_sub_province'] ) ) );
				}
			
			if ( isset ( $county_a ) && isset ( $county_b ) )
				{
				$tmp = strcmp ( strtolower ( $county_a ), strtolower ( $county_b ) );
				
				if ( $tmp != 0 )
					{
					$ret = $tmp;
					}
				}
			}
		
		return $ret;
	}
};
?>