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
    // whether to include QR codes for virtual/hybrid meetings
    public $include_qr = false;
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
        
        // Settings - use space character as marker for "intentionally empty" so parent constructor won't override
        // The DrawFrontPanel override will trim these before displaying
        $this->helpline_string = isset($in_http_vars['helpline']) && !empty(trim($in_http_vars['helpline'])) 
            ? trim($in_http_vars['helpline']) 
            : ' ';  // Space = intentionally empty
        $this->credits_string = isset($in_http_vars['credits']) && !empty(trim($in_http_vars['credits'])) 
            ? trim($in_http_vars['credits']) 
            : ' ';  // Space = intentionally empty
        $this->web_uri_string = isset($in_http_vars['web_url']) && !empty(trim($in_http_vars['web_url'])) 
            ? trim($in_http_vars['web_url']) 
            : ' ';  // Space = intentionally empty
        $this->banner_1_string = isset($in_http_vars['banner_1']) && !empty(trim($in_http_vars['banner_1'])) 
            ? trim($in_http_vars['banner_1']) 
            : 'NA Meetings';  // Keep default for banner 1
        $this->banner_2_string = isset($in_http_vars['banner_2']) && !empty(trim($in_http_vars['banner_2'])) 
            ? trim($in_http_vars['banner_2']) 
            : ' ';  // Space = intentionally empty
        $this->banner_3_string = isset($in_http_vars['banner_3']) && !empty(trim($in_http_vars['banner_3'])) 
            ? trim($in_http_vars['banner_3']) 
            : ' ';  // Space = intentionally empty
        
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
        
        // QR code setting
        $this->include_qr = isset($in_http_vars['include_qr']) && $in_http_vars['include_qr'] == '1';
        
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
    
    // Override DrawOneMeeting to add QR codes for virtual meetings
    public function DrawOneMeeting($left, $top, $column_width, $meeting)
    {
        // Check if we should show QR code (only if virtual_meeting_link is present)
        $hasVirtualLink = isset($meeting['virtual_meeting_link']) && !empty(trim($meeting['virtual_meeting_link']));
        $qrSize = 0.4; // QR code size in inches
        $qrMargin = 0.05;
        
        // Adjust column width if we need space for QR code
        if ($this->include_qr && $hasVirtualLink) {
            $availableWidth = $column_width - $qrSize - $qrMargin;
        } else {
            $availableWidth = $column_width;
        }
        
        $startY = $top;
        
        // Draw meeting info with adjusted width
        $fontFamily = $this->napdf_instance->getFontFamily();
        $fontSize = $this->font_size - 1.5;
        $fSize = $fontSize / 70;
        
        $this->napdf_instance->SetFillColor(255);
        $this->napdf_instance->SetTextColor(0);
        $this->napdf_instance->SetFont($fontFamily, 'B', $fontSize);
        
        // City
        $display_string = $meeting['location_municipality'];
        $this->napdf_instance->SetY($top);
        $this->napdf_instance->SetX($left);
        $this->napdf_instance->MultiCell($availableWidth, $fSize, mb_convert_encoding($display_string, 'ISO-8859-1', 'UTF-8'), 0, 'L');
        
        // Time
        $display_string = '';
        if (isset($meeting['start_time'])) {
            $display_string = printableList::translate_time($meeting['start_time']);
        }
        if (isset($meeting['duration_time']) && $meeting['duration_time'] && ('01:30:00' != $meeting['duration_time'])) {
            $display_string .= " (" . printableList::translate_duration($meeting['duration_time']) . ")";
        }
        $this->napdf_instance->SetX($left);
        $this->napdf_instance->MultiCell($availableWidth, $fSize, mb_convert_encoding($display_string, 'ISO-8859-1', 'UTF-8'));
        
        // Meeting name and formats
        $display_string = isset($meeting['meeting_name']) ? $meeting['meeting_name'] : '';
        if (isset($meeting['formats'])) {
            $display_string .= " (" . $this->RearrangeFormats($meeting['formats']) . ")";
        }
        $this->napdf_instance->SetX($left);
        $this->napdf_instance->MultiCell($availableWidth, $fSize, mb_convert_encoding($display_string, 'ISO-8859-1', 'UTF-8'), 0, 'L');
        
        $this->napdf_instance->SetFont($fontFamily, '', $fontSize);
        
        // Location details
        if (isset($meeting['location_neighborhood']) && $meeting['location_neighborhood']) {
            $display_string = $meeting['location_neighborhood'];
            $this->napdf_instance->SetX($left);
            $this->napdf_instance->MultiCell($availableWidth, $fSize, mb_convert_encoding($display_string, 'ISO-8859-1', 'UTF-8'), 0, 'L');
        }
        
        $display_string = '';
        if (isset($meeting['location_text']) && $meeting['location_text']) {
            $display_string .= $meeting['location_text'];
        }
        if (isset($meeting['location_info']) && $meeting['location_info']) {
            if ($display_string) $display_string .= ', ';
            $display_string .= " (" . $meeting['location_info'] . ")";
        }
        if ($display_string) $display_string .= ', ';
        $display_string .= isset($meeting['location_street']) ? $meeting['location_street'] : '';
        
        $this->napdf_instance->SetX($left);
        $this->napdf_instance->MultiCell($availableWidth, $fSize, mb_convert_encoding($display_string, 'ISO-8859-1', 'UTF-8'), 0, 'L');
        
        // Virtual meeting info (additional info and phone number, not the URL - that's what the QR code is for)
        $virtualParts = [];
        
        // Add additional info if present
        if (isset($meeting['virtual_meeting_additional_info']) && !empty(trim($meeting['virtual_meeting_additional_info']))) {
            $virtualParts[] = trim($meeting['virtual_meeting_additional_info']);
        }
        
        // Add phone number if present
        if (isset($meeting['phone_meeting_number']) && !empty(trim($meeting['phone_meeting_number']))) {
            $virtualParts[] = 'Phone: ' . trim($meeting['phone_meeting_number']);
        }
        
        // Display virtual info if we have any
        if (!empty($virtualParts)) {
            $virtualText = 'Virtual: ' . implode(' - ', $virtualParts);
            $this->napdf_instance->SetFont($fontFamily, 'I', $fontSize - 1);
            $this->napdf_instance->SetX($left);
            $this->napdf_instance->MultiCell($availableWidth, $fSize, mb_convert_encoding($virtualText, 'ISO-8859-1', 'UTF-8'), 0, 'L');
        } elseif ($hasVirtualLink) {
            // Fallback: if we have a link but no additional info, just show "Virtual" (QR code will have the URL)
            $virtualText = 'Virtual meeting (scan QR code)';
            $this->napdf_instance->SetFont($fontFamily, 'I', $fontSize - 1);
            $this->napdf_instance->SetX($left);
            $this->napdf_instance->MultiCell($availableWidth, $fSize, mb_convert_encoding($virtualText, 'ISO-8859-1', 'UTF-8'), 0, 'L');
        }
        
        // Comments
        $desc = '';
        if (isset($meeting['comments']) && $meeting['comments']) {
            $desc .= $meeting['comments'];
        }
        $desc = preg_replace("/[\n|\r]/", ", ", $desc);
        $desc = preg_replace("/,\s*,/", ",", $desc);
        $desc = stripslashes(stripslashes($desc));
        
        if ($desc) {
            $fSizeSmall = ($fontSize - 1) / 70;
            $this->napdf_instance->SetFont($fontFamily, 'I', $fontSize - 1);
            $this->napdf_instance->SetX($left);
            $this->napdf_instance->MultiCell($availableWidth, $fSizeSmall, mb_convert_encoding($desc, 'ISO-8859-1', 'UTF-8'));
        }
        
        // Draw QR code if enabled and virtual_meeting_link is present
        if ($this->include_qr && $hasVirtualLink) {
            $qrX = $left + $availableWidth + $qrMargin;
            $this->drawQRCode($meeting['virtual_meeting_link'], $qrX, $startY, $qrSize);
        }
        
        return $this->napdf_instance->GetY();
    }
    
    
    private function drawQRCode($url, $x, $y, $size)
    {
        try {
            // Use a QR code API service (api.qrserver.com is free and doesn't require authentication)
            $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($url);
            
            // Fetch QR code image
            $qrData = napdf::call_curl($qrApiUrl, false);
            
            if ($qrData) {
                // Save to temporary file
                $tempFile = sys_get_temp_dir() . '/qr_' . md5($url . time()) . '.png';
                file_put_contents($tempFile, $qrData);
                
                // Add image to PDF
                $this->napdf_instance->Image($tempFile, $x, $y, $size, $size, 'PNG');
                
                // Clean up
                @unlink($tempFile);
            }
        } catch (Exception $e) {
            // Silently fail - QR codes are optional
            error_log('QR Code generation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Override AssemblePDF to handle tri-fold layout specially
     * For tri-fold, the cover and phone list should only use 1/3 of the page width
     */
    public function AssemblePDF()
    {
        // Check if this is a tri-fold layout (portrait or landscape)
        $isTrifoldPortrait = $this->list_page_sections == 3 && 
                             $this->orientation == 'P' && 
                             $this->page_x == 8.5 && 
                             $this->page_y == 11;
        
        $isTrifoldLandscape = $this->list_page_sections == 3 && 
                              $this->orientation == 'L' && 
                              $this->page_x == 11 && 
                              $this->page_y == 8.5;
        
        $isTrifold = $isTrifoldPortrait || $isTrifoldLandscape;
        
        if (!$isTrifold) {
            // Use parent implementation for non-tri-fold layouts
            return parent::AssemblePDF();
        }
        
        // Tri-fold specific implementation
        $meeting_data = $this->napdf_instance->meeting_data;
        
        if (!$meeting_data) {
            return false;
        }
        
        // Calculate panel widths
        $panelpage['margin'] = $this->page_margins;
        $panelpage['height'] = $this->page_y - ($this->page_margins * 2);
        $panelpage['width'] = $this->page_x - ($this->page_margins * 2);
        
        // For tri-fold, each panel is 1/3 of the page width
        $panel_width = ($this->page_x - ($this->page_margins * 2)) / 3;
        
        foreach ($meeting_data as &$meeting) {
            if (isset($meeting['location_text']) && isset($meeting['location_street'])) {
                $meeting['location'] = $meeting['location_text'] . ', ' . $meeting['location_street'];
            }
        }
        
        $fixed_font_size = $this->font_size;
        $variable_font_size = $this->font_size + 2;
        
        // First page: Cover (1/3) + Meetings (2/3)
        $this->napdf_instance->AddPage();
        
        $this->pos['end'] = false;
        $this->pos['start'] = true;
        $this->pos['count'] = 0;
        
        // Panel 1 (left): Cover/Front Panel - 1/3 width
        $cover_left = $this->page_margins;
        $cover_right = $cover_left + $panel_width;
        $this->DrawFrontPanel(
            $fixed_font_size,
            $cover_left,
            $this->page_margins,
            $cover_right,
            $this->page_y - $this->page_margins
        );
        
        // Panels 2 & 3: Start meetings - 2/3 width (2 columns)
        $meetings_left = $cover_right + $this->page_margins;
        $meetings_right = $this->page_x - $this->page_margins;
        $this->font_size = $variable_font_size;
        $this->DrawListPage(
            $meetings_left,
            $this->page_margins,
            $meetings_right,
            $this->page_y - $this->page_margins,
            $this->page_margins, // margin between the 2 columns
            2  // 2 columns for these panels
        );
        
        // Middle pages: 3 columns of meetings, except on the last page do only 2 columns to leave room for phone list
        $lastPageNeedsPhoneList = true;
        while (!$this->pos['end']) {
            $this->napdf_instance->AddPage();
            $this->font_size = $variable_font_size;
            
            // Check if this might be the last page by looking ahead
            // We'll draw meetings on 2/3 of the page and reserve 1/3 for phone list
            $meetings_width = ($this->page_x - ($this->page_margins * 2)) * (2.0/3.0);
            
            $this->DrawListPage(
                $this->page_margins,
                $this->page_margins,
                $this->page_margins + $meetings_width,
                $this->page_y - $this->page_margins,
                $this->page_margins,
                2  // 2 columns to leave room for phone list
            );
            
            // If we just finished all meetings, add phone list on the right 1/3
            if ($this->pos['end'] && $lastPageNeedsPhoneList) {
                $phone_left = $this->page_margins + $meetings_width + $this->page_margins;
                $phone_right = $this->page_x - $this->page_margins;
                $this->DrawRearPanel(
                    $fixed_font_size,
                    $phone_left,
                    $this->page_margins,
                    $phone_right,
                    $this->page_y - $this->page_margins
                );
                $lastPageNeedsPhoneList = false;
            }
        }
        
        return true;
    }
    
    /**
     * Override DrawFrontPanel to skip empty fields
     */
    public function DrawFrontPanel($fixed_font_size, $left, $top, $right, $bottom)
    {
        $this->font_size = $fixed_font_size;
        $this->napdf_instance->SetFont($this->font, 'B', $this->font_size + 1);
        
        $date = date($this->date_header_format_string);
        
        $inTitleGraphic = dirname(__FILE__) . '/' . $this->image_path_string;
        $titleGraphicSize = min(($right - $left) / 2, ($bottom - $top) / 2);
        
        $y = $top + $this->page_margins;
        
        $fontFamily = $this->napdf_instance->getFontFamily();
        $fontSize = $this->font_size - 1.5;
        
        // Date
        $this->napdf_instance->SetFont($this->font, 'B', $fontSize - 1);
        $stringWidth = $this->napdf_instance->GetStringWidth($date);
        $cellleft = (($right + $left) / 2) - ($stringWidth / 2);
        $this->napdf_instance->SetXY($cellleft, $y);
        $this->napdf_instance->Cell(0, 0, $date);
        $y += 0.1;
        
        // Credits - only if not empty
        $credits = trim($this->credits_string);
        if (!empty($credits) && $credits !== ' ') {
            $this->napdf_instance->SetFont($this->font, 'B', $fontSize - 0.5);
            $stringWidth = $this->napdf_instance->GetStringWidth($credits);
            $cellleft = (($right + $left) / 2) - ($stringWidth / 2);
            $this->napdf_instance->SetXY($cellleft, $y);
            $this->napdf_instance->Cell(0, 0, $credits);
            $y += 0.2;
        } else {
            $y += 0.1;  // Small spacing even if empty
        }
        
        // Banner 1 - only if not empty
        $banner1 = trim($this->banner_1_string);
        if (!empty($banner1) && $banner1 !== ' ') {
            $this->napdf_instance->SetFont($this->font, 'B', ($fontSize + 7));
            $stringWidth = $this->napdf_instance->GetStringWidth($banner1);
            $cellleft = (($right + $left) / 2) - ($stringWidth / 2);
            $this->napdf_instance->SetXY($cellleft, $y);
            $this->napdf_instance->Cell(0, 0, $banner1);
            $y += 0.2;
        }
        
        // Banner 2 and 3 - only if at least one is not empty
        $banner_combined = trim($this->banner_2_string . ' ' . $this->banner_3_string);
        if (!empty($banner_combined) && $banner_combined !== ' ') {
            $this->napdf_instance->SetFont($this->font, 'B', $fontSize + 1);
            $stringWidth = $this->napdf_instance->GetStringWidth($banner_combined);
            $cellleft = (($right + $left) / 2) - ($stringWidth / 2);
            $this->napdf_instance->SetXY($cellleft, $y);
            $this->napdf_instance->Cell(0, 0, $banner_combined);
            $y += 0.2;
        }
        
        // Logo/Image - only if file exists
        $y += 0.125;
        if (!empty($this->image_path_string) && file_exists(dirname(__FILE__) . '/' . $this->image_path_string)) {
            $title_left = (($right - $left) / 2) - ($titleGraphicSize / 2);
            $this->napdf_instance->Image($inTitleGraphic, $left + $title_left, $y, $titleGraphicSize, $titleGraphicSize, 'PNG');
            $y += $titleGraphicSize;
        }
        $y += 0.125;
        
        // Website URL - only if not empty
        $webUrl = trim($this->web_uri_string);
        if (!empty($webUrl) && $webUrl !== ' ') {
            $this->napdf_instance->SetFont($this->font, 'B', $fontSize + 2);
            $stringWidth = $this->napdf_instance->GetStringWidth($webUrl);
            $cellleft = (($right + $left) / 2) - ($stringWidth / 2);
            $this->napdf_instance->SetXY($cellleft, $y);
            $this->napdf_instance->Cell(0, 0, $webUrl);
            $y += 0.2;
        }
        
        // Helpline - only if not empty
        $helpline = trim($this->helpline_string);
        if (!empty($helpline) && $helpline !== ' ') {
            $this->napdf_instance->SetFont($fontFamily, '', ($fontSize + 2));
            $stringWidth = $this->napdf_instance->GetStringWidth($helpline);
            $cellleft = (($right + $left) / 2) - ($stringWidth / 2);
            $this->napdf_instance->SetXY($cellleft, $y);
            $this->napdf_instance->Cell(0, 0, $helpline);
            $y += 0.2;
        }
        
        $this->napdf_instance->SetFont($this->font, 'B', $this->font_size + 1);
        
        // Format legend - use current Y position plus small margin
        $this->DrawFormats($left, $y + 0.15, $right, $bottom, $this->napdf_instance->format_data, false, true);
    }
}
