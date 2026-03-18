<?php
/**
 * MikroTik RouterOS v7 API Client
 * Query system scripts with owner filter (server-side filtering)
 *
 * Usage: php test_mikrotik_api.php
 */

class MikroTikAPI {
    private $socket;
    private $connected = false;

    public function connect($host, $port, $user, $pass) {
        $this->socket = @fsockopen($host, $port, $errno, $errstr, 10);

        if (!$this->socket) {
            throw new Exception("Connection failed: $errno - $erstr");
        }

        stream_set_blocking($this->socket, true);
        stream_set_timeout($this->socket, 10);

        $loginSentence = $this->encodeCommand("/login") .
                         $this->encodeCommand("=name=" . $user) .
                         $this->encodeCommand("=password=" . $pass).
                         "\x00";

        fwrite($this->socket, $loginSentence);
        usleep(300000);

        $response = fread($this->socket, 8192);

        if (strpos($response, "!trap") !== false || strpos($response, "!fatal") !== false) {
            throw new Exception("Login failed");
        }

        if (strpos($response, "!done") === false) {
            throw new Exception("No response from server");
        }

        $this->connected = true;
        return true;
    }

    public function getScriptsByOwnerPattern($patterns) {
        if (!$this->connected) {
            throw new Exception("Not connected");
        }

        $baseQuery = $this->encodeCommand("/system/script/print") .
                           $this->encodeCommand("=.proplist=.id,name,owner,policy,last-started,run-count,source,dont-require-permissions");

        $allScripts = [];

        foreach ($patterns as $pattern) {
            $filterQuery = $baseQuery .
                             $this->encodeCommand("?~owner=" . $pattern) .
                             "\x00";

            fwrite($this->socket, $filterQuery);

            $patternData = "";
            stream_set_timeout($this->socket, 10);
            while (!feof($this->socket)) {
                $chunk = fread($this->socket, 4096);
                if ($chunk === false || strlen($chunk) === 0) break;
                $patternData .= $chunk;
            }

            $patternScripts = $this->parseResponse($patternData);

            foreach ($patternScripts as $script) {
                $scriptId = $script[".id"] ?? "";
                $exists = false;
                foreach ($allScripts as $existing) {
                    if (($existing[".id"] ?? "") === $scriptId) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $allScripts[] = $script;
                }
            }
        }

        return $allScripts;
    }

    public function disconnect() {
        if ($this->socket) {
            fclose($this->socket);
            $this->connected = false;
        }
    }

    private function encodeCommand($cmd) {
        $len = strlen($cmd);
        $encoded = "";
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
            $encoded .= pack("N", $len);
        }

        $encoded .= $cmd;
        return $encoded;
    }

    private function parseResponse($data) {
        $result = [];
        $currentEntry = [];
        $pos = 0;
        $len = strlen($data);

        while ($pos < $len) {
            $byte = ord($data[$pos]);
            $pos++;

            if ($byte === 0) {
                if (!empty($currentEntry)) {
                    $result[] = $currentEntry;
                    $currentEntry = [];
                }
                continue;
            }

            $wordLen = $byte;
            if ($byte >= 0x80) {
                $wordLen = 0;
            }

            if ($pos + $wordLen > $len) break;

            $word = substr($data, $pos, $wordLen);
            $pos += $wordLen;

            if ($word === "!re") {
                if (!empty($currentEntry)) {
                    $result[] = $currentEntry;
                }
                $currentEntry = [];
            } elseif ($word === "!done") {
                break;
            } elseif (strlen($word) > 1 && $word[0] === "=") {
                $parts = explode("=", substr($word, 1), 2);
                if (count($parts) === 2) {
                    $currentEntry[trim($parts[0])] = trim($parts[1]);
                }
            }
        }

        return $result;
    }
}

$config = [
    "host" => "192.168.100.1",
    "port" => 999,
    "user" => "telebot",
    "pass" => "telebot",
    "patterns" => ["2026-03", "jun2025"],
];

try {
    echo "=== MikroTik RouterOS API - System Script Query ===\n";
    echo "Host: {$config["host"]}:{$config["port"]}\n";
    echo "User: {$config["user"]}\n";
    echo "Filter: owner contains \"" . implode("\" OR \"", $config["patterns"]) . "\" [SERVER-SIDE]\n\n";

    $api = new MikroTikAPI();

    echo "Connecting...\n";
    $api->connect($config["host"], $config["port"], $config["user"], $config["pass"]);
    echo "Connected.\n\n";

    echo "Querying scripts (with server-side filter)...\n";
    $scripts = $api->getScriptsByOwnerPattern($config["patterns"]);

    echo "\n";
    echo str_repeat("=", 90) . "\n";
    echo "RESULTS: Found " . count($scripts) . " scripts\n";
    echo str_repeat("=", 90) . "\n";

    $grouped = [];
    foreach ($scripts as $script) {
        $owner = $script["owner"] ?? "unknown";
        if (!isset($grouped[$owner])) {
            $grouped[$owner] = [];
        }
        $grouped[$owner][] = $script;
    }

    foreach ($grouped as $owner => $ownerScripts) {
        echo "Owner: $owner (" . count($ownerScripts) . " scripts)\n";
        echo str_repeat("-", 90) . "\n";
        printf("  %-4s | %-6s | %-20s | %-12s | %s\n", "No.", "ID", "Date/Time", "IP Address", "MAC Address");
        echo str_repeat("-", 90) . "\n";

        foreach ($ownerScripts as $i => $script) {
            $fullName = $script["name"] ?? "N/A";
            $date = substr($fullName, 0, 12);

            preg_match('/\|-(\d+\.\d+\.\d+\.\d+)-\|/', $fullName, $ipMatch);
            preg_match('/\-(0-9A-F:]{17})-\|/', $fullName, $macMatch);

            $ip = $ipMatch[1] ?? "N/A";
            $mac = $macMatch[1] ?? "N/A";

            printf("  %-4d | %-6s | %-20s | %-15s | %s\n",
                $i + 1,
                $script[".id"] ?? "N/A",
                $date,
                $ip,
                $mac
            );
        }
        echo "\n";
    }

    echo str_repeat("=", 90) . "\n";
    echo "SUMMARY\n";
    echo str_repeat("=", 90) . "\n";
    foreach ($grouped as $owner => $ownerScripts) {
        printf("  - %s: %d scripts\n", $owner, count($ownerScripts));
    }
    echo str_repeat("=", 90) . "\n";
    echo "TOTAL: " . count($scripts) . " scripts\n";
    echo str_repeat("=", 90) . "\n\n";

    echo "JSON Output saved to: scripts_export.json\n";
    file_put_contents("scripts_export.json", json_encode($scripts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $api->disconnect();
    echo "\nDisconnected.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
