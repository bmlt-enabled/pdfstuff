<?php
/**
 * Generic NAPDF Class
 * 
 * This class extends flex_napdf and accepts configuration parameters dynamically
 * instead of using hardcoded constants. This allows users to generate PDFs
 * for any BMLT server and service body without modifying code.
 */

require_once(dirname(__FILE__).'/flex_napdf.class.php');

class generic_napdf extends flex_napdf
{
    // grouping mode: 'weekday' (default) or 'city'
    public $group_by = 'weekday';
    /**
     * Constructor accepts configuration from HTTP parameters
     * 
     * @param array $in_http_vars HTTP parameters including configuration
     */
    function __construct($in_http_vars)
    {
        // Extract configuration from parameters with defaults
        $serverUrl = $in_http_vars['server_url'] ?? '';
        $serviceBodies = $in_http_vars['service_bodies'] ?? [];
        $recursive = isset($in_http_vars['recursive']) && $in_http_vars['recursive'] == '1';
        
        // Settings with sensible defaults
        $this->helpline_string = $in_http_vars['helpline'] ?? 'Helpline: Contact your local area';
        $this->credits_string = $in_http_vars['credits'] ?? 'Meeting List Generated from BMLT';
        $this->web_uri_string = $in_http_vars['web_url'] ?? '';
        $this->banner_1_string = $in_http_vars['banner_1'] ?? 'NA Meetings';
        $this->banner_2_string = $in_http_vars['banner_2'] ?? '';
        $this->banner_3_string = $in_http_vars['banner_3'] ?? '';
        
        // Week starts: 1=Sunday, 2=Monday, etc.
        $this->week_starts_1_based_int = isset($in_http_vars['week_starts']) 
            ? (int)$in_http_vars['week_starts'] 
            : 1;
        
        // Optional image path
        $this->image_path_string = $in_http_vars['logo_path'] ?? '';
        
        // Filename with timestamp
        $timestamp = date('Y_m_d');
        $this->filename = $in_http_vars['filename'] ?? "meeting_list_$timestamp.pdf";
        
        // Server URL is required
        if (empty($serverUrl)) {
            throw new Exception('Server URL is required');
        }
        $this->root_uri = rtrim($serverUrl, '/') . '/';
        
        // Date format
        $this->date_header_format_string = $in_http_vars['date_format'] ?? '\\R\\e\\v\\i\\s\\e\\d F, Y';
        
        // Font
        $this->font = 'Helvetica';
        
        // Sorting configuration based on group_by parameter
        $groupBy = $in_http_vars['group_by'] ?? 'weekday';
        
        // Store grouping preference for use in rendering
        $this->group_by = $groupBy;
        
        // Set sort order based on grouping
        if ($groupBy === 'city') {
            // Group by city: sort by city first, then weekday, then time
            $this->sort_keys = array(
                'location_municipality' => true,
                'weekday_tinyint' => true,
                'start_time' => true,
                'week_starts' => $this->week_starts_1_based_int
            );
        } else {
            // Group by weekday (default): sort by weekday first, then time, then city
            $this->sort_keys = array(
                'weekday_tinyint' => true,
                'start_time' => true,
                'location_municipality' => true,
                'week_starts' => $this->week_starts_1_based_int
            );
        }
        
        // Build service body parameters
        if (empty($serviceBodies)) {
            throw new Exception('At least one service body must be selected');
        }
        
        // Prepare service body IDs
        $serviceBodyIds = [];
        if (is_array($serviceBodies)) {
            $serviceBodyIds = $serviceBodies;
        } elseif (is_string($serviceBodies)) {
            // Handle comma-separated string
            $serviceBodyIds = array_map('trim', explode(',', $serviceBodies));
        }
        
        // Convert to integers
        $serviceBodyIds = array_map('intval', array_filter($serviceBodyIds));
        
        if (empty($serviceBodyIds)) {
            throw new Exception('Invalid service body IDs');
        }
        
        // Build parameters for BMLT query
        $this->out_http_vars = array(
            'services' => $serviceBodyIds,
            'recursive' => $recursive ? '1' : '0',
            'sort_key' => 'time'
        );
        
        // Call parent constructor
        parent::__construct($in_http_vars);
    }
    
    /**
     * Override parent method to add custom validation
     */
    protected function validateConfiguration()
    {
        if (empty($this->root_uri)) {
            return false;
        }
        
        if (empty($this->out_http_vars['services'])) {
            return false;
        }
        
        return true;
    }

    // Override to support grouping by city
    public function DrawListPage($left, $top, $right, $bottom, $margin, $columns)
    {
        if ($this->group_by !== 'city') {
            return parent::DrawListPage($left, $top, $right, $bottom, $margin, $columns);
        }

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

        $y_offset = $bottom - $fSize;

        $current_city = '';
        $this->pos['start'] = $this->pos['start'] ?? true;
        if ($this->pos['start']) {
            $current_city = '';
            $this->pos['count'] = 0;
            $this->pos['start'] = false;
        }

        for ($column = 0; ($column < $columns) && !$this->pos['end']; $column++) {
            $y = $top;
            $column_left = $left + (($margin + $column_width) * $column);

            while (!$this->pos['end'] && ($y + 0.2) < $y_offset) {
                $meeting = $meetings[intval($this->pos['count'])];
                $meeting_city = isset($meeting['location_municipality']) ? trim($meeting['location_municipality']) : '';
                $is_new_group = strcasecmp($meeting_city, $current_city) !== 0;

                if ($is_new_group || ($y == $top)) {
                    $current_city = $meeting_city ?: 'Unknown City';
                    $y += ($y == $top) ? 0 : 0.075;
                    $y = $this->DrawCityHeader($column_left, $y, $column_width, $current_city);
                } else {
                    $this->napdf_instance->SetDrawColor(0);
                    $y += 0.05;
                    $this->napdf_instance->Line($column_left, $y, $column_left + $column_width, $y);
                }

                $y += 0.05;
                $y = parent::DrawOneMeeting($column_left, $y, $column_width, $meeting);

                if (++$this->pos['count'] == $count_max) {
                    $this->pos['end'] = 1;
                }
            }
        }
    }

    private function DrawCityHeader($left, $top, $column_width, $city)
    {
        $heading_height = 8;
        $height = 0.15;
        $fontFamily = $this->napdf_instance->getFontFamily();

        $this->napdf_instance->SetFillColor(0);
        $this->napdf_instance->SetTextColor(255);
        $this->napdf_instance->Rect($left, $top, $column_width, $height, "F");

        $header = $city;
        $this->napdf_instance->SetFont($fontFamily, 'B', $heading_height);
        $stringWidth = $this->napdf_instance->GetStringWidth($header);

        if ($stringWidth >= ($column_width - 0.125)) {
            $this->napdf_instance->SetFont($fontFamily, 'B', $heading_height - 1);
            $stringWidth = $this->napdf_instance->GetStringWidth($header);
        }

        $cellleft = (($column_width - $stringWidth) / 2) + $left;
        $this->napdf_instance->SetXY($cellleft, $top);
        $this->napdf_instance->Cell(0, $height, $header);

        return $top + $height;
    }
}
