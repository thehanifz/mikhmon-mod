<?php
/*
 * RouterOS v7 Compatibility Layer for Mikhmon V3
 * Supports RouterOS v7.20.8+
 * 
 * This file provides compatibility functions for RouterOS v7
 */

// RouterOS version detection
function detectRouterOSVersion($API) {
    static $ros_version = null;
    
    if ($ros_version !== null) {
        return $ros_version;
    }
    
    try {
        // Try to get system identity and version
        $version_info = $API->comm("/system/resource/print");
        if (isset($version_info[0]['version'])) {
            $version = $version_info[0]['version'];
            $major_version = (int)explode('.', $version)[0];
            $ros_version = $major_version;
            return $ros_version;
        }
    } catch (Exception $e) {
        // Fallback to v6 if detection fails
        $ros_version = 6;
    }
    
    $ros_version = 6;
    return $ros_version;
}

// Get correct path based on RouterOS version
function getSystemScriptPath($API) {
    $version = detectRouterOSVersion($API);
    
    // RouterOS v7.20.8 still uses /system/script
    // Path changed to /system/scripting only in v7.18+ beta
    if ($version >= 7) {
        return "/system/script";
    }
    return "/system/script";
}

function getSystemSchedulerPath($API) {
    $version = detectRouterOSVersion($API);
    
    // RouterOS v7.20.8 still uses /system/scheduler
    if ($version >= 7) {
        return "/system/scheduler";
    }
    return "/system/scheduler";
}

// RouterOS v7 compatible script add function
function addSystemScript($API, $params) {
    $version = detectRouterOSVersion($API);
    $path = getSystemScriptPath($API);
    
    // In RouterOS v7, 'source' parameter is still supported
    // But we need to handle permissions differently
    if ($version >= 7) {
        // RouterOS v7 requires policy to be set explicitly
        $default_params = array(
            "owner" => "mikhmon",
            "policy" => array("read", "write", "test", "reboot")
        );
        $params = array_merge($default_params, $params);
    }
    
    return $API->comm($path . "/add", $params);
}

// RouterOS v7 compatible scheduler add function
function addSystemScheduler($API, $params) {
    $version = detectRouterOSVersion($API);
    $path = getSystemSchedulerPath($API);
    
    if ($version >= 7) {
        // RouterOS v7 scheduler changes:
        // - 'on-event' is still supported
        // - 'start-date' format might need adjustment
        // Add policy if needed
        $default_params = array(
            "policy" => array("read", "write", "test")
        );
        $params = array_merge($default_params, $params);
    }
    
    return $API->comm($path . "/add", $params);
}

// Format date for RouterOS v7
function formatDateForROSv7($date_string) {
    // RouterOS v7 expects date in format: yyyy-mm-dd or mon/dd/yyyy
    // v6 format: mon/dd/yyyy
    // v7 accepts both, but we'll use v6 format for compatibility
    return $date_string;
}

// Check if running on RouterOS v7
function isRouterOSv7($API) {
    return detectRouterOSVersion($API) >= 7;
}

?>
