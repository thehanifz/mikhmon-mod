<?php
/**
 * Dummy API Test File for MikroTik RouterOS v7
 * Simulates /system script print with owner filter
 * 
 * Usage: php test_api_dummy.php
 */

// Simulated MikroTik RouterOS v7.20 API Response
// Command: /system script print where owner~"(2026-03|jun2025)"



// Output format options
echo "=== MikroTik RouterOS v7.20 API Dummy Test ===\n\n";

echo "Command: /system script print where owner~\"(2026-03|jun2025)\"\n\n";

echo "Filtered Results (" . count($filteredData) . " scripts found):\n";
echo str_repeat("-", 80) . "\n";

foreach ($filteredData as $script) {
    echo sprintf("ID: %-5s Name: %-25s Owner: %-15s\n", 
        $script['.id'], 
        $script['name'], 
        $script['owner']
    );
    echo "  Policy: " . implode(', ', $script['policy']) . "\n";
    echo "  Last Started: " . $script['last-started'] . " | Run Count: " . $script['run-count'] . "\n";
    echo "  Source: " . substr($script['source'], 0, 50) . "...\n";
    echo str_repeat("-", 80) . "\n";
}

// JSON output for API testing
echo "\n\n=== JSON Output (for API integration test) ===\n";
echo json_encode(array_values($filteredData), JSON_PRETTY_PRINT) . "\n";

// Test connection function (for real API testing)
function testMikroTikConnection($host, $user, $pass, $port = 8728) {
    echo "\n=== Testing Real Connection ===\n";
    echo "Host: $host:$port\n";
    echo "User: $user\n";
    
    $socket = @fsockopen($host, $port, $errno, $errstr, 5);
    
    if ($socket) {
        echo "Status: ✓ Connection successful\n";
        fclose($socket);
        return true;
    } else {
        echo "Status: ✗ Connection failed ($errno: $errstr)\n";
        return false;
    }
}

// Encode word for MikroTik API protocol (proper variable-length encoding)
function encodeWord($word) {
    $len = strlen($word);
    $encoded = '';
    
    // Variable-length encoding per MikroTik spec
    if ($len <= 0x7F) {
        $encoded .= chr($len);
    } elseif ($len <= 0x3FFF) {
        $encoded .= chr(0x80 | ($len >> 8));
        $encoded .= chr($len & 0xFF);
    } elseif ($len <= 0x1FFFFF) {
        $encoded .= chr(0xC0 | ($len >> 16));
        $encoded .= chr(($len >> 8) & 0xFF);
        $encoded .= chr($len & 0xFF);
    } else {
        $encoded .= chr(0xF0);
        $encoded .= pack('N', $len);
    }
    
    $encoded .= $word;
    return $encoded;
}

// Encode API command with proper length prefixes
function encodeCommand($cmd) {
    return encodeWord($cmd);
}

// Function to query MikroTik API and get script data (RouterOS v7 compatible)
function getMikroTikScripts($host, $port, $user, $pass) {
    echo "\n=== Connecting to MikroTik API ===\n";
    echo "Host: $host:$port | User: $user\n";
    
    $socket = @fsockopen($host, $port, $errno, $errstr, 10);
    
    if (!$socket) {
        echo "Error: Cannot connect ($errno: $errstr)\n";
        return false;
    }
    
    stream_set_blocking($socket, true);
    stream_set_timeout($socket, 10);
    
    // Build login sentence (simple login for v6.43+)
    $loginSentence = encodeCommand("/login") .
                     encodeCommand("=name=" . $user) .
                     encodeCommand("=password=" . $pass) .
                     "\x00";  // zero-length terminator
    
    echo "Sending login (hex): " . bin2hex($loginSentence) . "\n";
    
    fwrite($socket, $loginSentence);
    
    usleep(300000);
    $loginResult = fread($socket, 8192);
    
    echo "Login response (hex): " . bin2hex($loginResult) . "\n";
    
    if (strpos($loginResult, '!trap') !== false || strpos($loginResult, '!fatal') !== false) {
        echo "Error: Login failed\n";
        fclose($socket);
        return false;
    }
    
    echo "Login: Success\n";
    
    // Build query sentence with OR stack operation
    // Filter: owner=2026-03 OR owner=jun2025
    $querySentence = encodeCommand("/system/script/print") .
                     encodeCommand("=.proplist=.id,name,owner,policy,last-started,run-count,source") .
                     encodeCommand("?owner=2026-03") .
                     encodeCommand("?owner=jun2025") .
                     encodeCommand("?#|") .  // OR operation on stack
                     "\x00";  // zero-length terminator
    
    echo "Sending query (hex): " . bin2hex($querySentence) . "\n";
    
    fwrite($socket, $querySentence);
    
    // Read all response data
    $allData = '';
    stream_set_timeout($socket, 5);
    while (!feof($socket)) {
        $chunk = fread($socket, 4096);
        if ($chunk === false || strlen($chunk) === 0) break;
        $allData .= $chunk;
    }
    
    fclose($socket);
    
    echo "Raw response length: " . strlen($allData) . " bytes\n";
    
    // Parse the response
    $data = [];
    $currentEntry = [];
    $pos = 0;
    $len = strlen($allData);
    
    while ($pos < $len) {
        // Read word length
        $byte = ord($allData[$pos]);
        $pos++;
        
        if ($byte === 0) {
            // Zero-length word (sentence terminator)
            if (!empty($currentEntry)) {
                $data[] = $currentEntry;
                $currentEntry = [];
            }
            continue;
        }
        
        // Read word content
        $wordLen = $byte;
        if ($byte >= 0x80) {
            // Multi-byte length encoding (not needed for small words)
            $wordLen = 0;
        }
        
        if ($pos + $wordLen > $len) break;
        
        $word = substr($allData, $pos, $wordLen);
        $pos += $wordLen;
        
        if ($word === '!re') {
            // New reply entry
            if (!empty($currentEntry)) {
                $data[] = $currentEntry;
            }
            $currentEntry = [];
        } elseif ($word === '!done') {
            // End of response
            break;
        } elseif (strlen($word) > 1 && $word[0] === '=') {
            // Property line
            $parts = explode('=', substr($word, 1), 2);
            if (count($parts) === 2) {
                $currentEntry[trim($parts[0])] = trim($parts[1]);
            }
        }
    }
    
    echo "Query: Success - " . count($data) . " records retrieved\n";
    
    return $data;
}

// Test real connection - ambil data dari MikroTik
$mt_host = '192.168.100.1';
$mt_port = 999;
$mt_user = 'telebot';
$mt_pass = 'telebot';  // password telebot

// Query real data from MikroTik
$realData = getMikroTikScripts($mt_host, $mt_port, $mt_user, $mt_pass);

if ($realData) {
    echo "\n\n=== REAL DATA FROM MIKROTIK ===\n";
    echo "Command: /system script print where owner~\"(2026-03|jun2025)\"\n\n";
    echo "Found " . count($realData) . " scripts:\n\n";
    
    foreach ($realData as $script) {
        echo sprintf("ID: %-5s Name: %-30s Owner: %-20s\n", 
            $script['.id'] ?? 'N/A',
            $script['name'] ?? 'N/A',
            $script['owner'] ?? 'N/A'
        );
        if (isset($script['source'])) {
            echo "  Source: " . substr($script['source'], 0, 60) . "...\n";
        }
        echo str_repeat("-", 80) . "\n";
    }
}

testMikroTikConnection($mt_host, $mt_user, $mt_pass, $mt_port);

echo "\n=== Test Complete ===\n";
