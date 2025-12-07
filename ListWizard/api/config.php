<?php
/**
 * API endpoint for BMLT server configuration
 * Handles server list retrieval and service body fetching
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

// Error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'servers':
            echo json_encode(getServerList());
            break;
            
        case 'service_bodies':
            $serverUrl = $_POST['server_url'] ?? '';
            if (empty($serverUrl)) {
                throw new Exception('server_url is required');
            }
            echo json_encode(getServiceBodies($serverUrl));
            break;
            
        default:
            throw new Exception('Invalid action. Use action=servers or action=service_bodies');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Get server list from BMLT aggregator
 * @return array List of servers with name and URL
 */
function getServerList() {
    // Fetch from official BMLT aggregator
    $aggregatorUrl = 'https://raw.githubusercontent.com/bmlt-enabled/aggregator/refs/heads/main/serverList.json';
    
    $serversJson = callCurl($aggregatorUrl);
    
    if ($serversJson === false) {
        throw new Exception('Failed to fetch server list from aggregator');
    }
    
    $servers = json_decode($serversJson, true);
    
    if (!is_array($servers)) {
        throw new Exception('Invalid server list format');
    }
    
    // Transform to our format - use name field directly as provided
    $serverList = array_map(function($server) {
        return [
            'id' => $server['id'] ?? '',
            'name' => $server['name'] ?? 'Unknown Server',
            'url' => $server['url'] ?? ''
        ];
    }, $servers);
    
    // Sort alphabetically by name
    usort($serverList, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    
    return $serverList;
}

/**
 * Fetch service bodies from a BMLT server
 * @param string $serverUrl The BMLT server root URL
 * @return array List of service bodies
 */
function getServiceBodies($serverUrl) {
    // Ensure URL ends with /
    $serverUrl = rtrim($serverUrl, '/') . '/';
    
    // Construct the API endpoint
    $apiUrl = $serverUrl . 'client_interface/json/?switcher=GetServiceBodies';
    
    // Fetch data using cURL
    $result = callCurl($apiUrl);
    
    if ($result === false) {
        throw new Exception('Failed to fetch service bodies from server');
    }
    
    $serviceBodies = json_decode($result, true);
    
    if (!is_array($serviceBodies)) {
        throw new Exception('Invalid response from BMLT server');
    }
    
    // Sort by name for better UX
    usort($serviceBodies, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    
    // Return simplified structure
    return array_map(function($sb) {
        return [
            'id' => (int)$sb['id'],
            'name' => $sb['name'],
            'type' => $sb['type'] ?? 'AS',
            'parent_id' => isset($sb['parent_id']) ? (int)$sb['parent_id'] : null,
            'description' => $sb['description'] ?? ''
        ];
    }, $serviceBodies);
}

/**
 * Make HTTP request using cURL with fallback
 * @param string $url The URL to fetch
 * @param array $options Optional cURL options
 * @return string|false Response body or false on failure
 */
function callCurl($url, $options = []) {
    // Try cURL first
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'BMLT PDF Generator/1.0');
        
        // Apply custom options
        foreach ($options as $option => $value) {
            curl_setopt($ch, $option, $value);
        }
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return $result;
        }
        
        return false;
    }
    
    // Fallback to file_get_contents
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'BMLT PDF Generator/1.0'
        ]
    ]);
    
    return @file_get_contents($url, false, $context);
}
