<?php
/*
 * Mikhmon V3 - Security Module
 * AES-256-GCM Encryption, CSRF Protection, Rate Limiting
 * 
 * Requires: PHP 7.2+, OpenSSL extension
 */

if (substr($_SERVER["REQUEST_URI"], -12) == "security.php") {
    header("Location:./");
    exit;
}

// ─────────────────────────────────────────────
// 1. LOAD SECRET KEY FROM ENVIRONMENT
// ─────────────────────────────────────────────

function getMikhmonSecretKey(): string {
    // Prioritas: ENV variable (Docker) → .env file → ERROR
    $key = getenv('MIKHMON_SECRET');

    if (!$key) {
        // Fallback: baca .env file manual
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    $name  = trim($name);
                    $value = trim($value);
                    // Hapus quote jika ada
                    $value = trim($value, '"\'');
                    if ($name === 'MIKHMON_SECRET') {
                        $key = $value;
                        break;
                    }
                }
            }
        }
    }

    if (!$key || strlen($key) < 16) {
        // Jangan expose detail error ke user
        error_log('[MIKHMON] MIKHMON_SECRET tidak ditemukan atau terlalu pendek di .env / environment variable.');
        die('<div style="padding:20px;font-family:monospace;background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;border-radius:5px;">
            <strong>Configuration Error</strong><br>
            MIKHMON_SECRET belum dikonfigurasi.<br>
            Silakan cek file .env Anda.
        </div>');
    }

    // Derive 32-byte key pakai SHA-256
    return hash('sha256', $key, true); // return raw binary 32 bytes
}

// ─────────────────────────────────────────────
// 2. ENKRIPSI AES-256-GCM
// ─────────────────────────────────────────────

/**
 * Enkripsi string menggunakan AES-256-GCM
 * Output: base64(iv + tag + ciphertext)
 */
function mikhmonEncrypt(string $plaintext): string {
    if ($plaintext === '') return '';

    $key    = getMikhmonSecretKey();
    $iv     = random_bytes(12); // 96-bit IV untuk GCM
    $tag    = '';

    $cipher = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        '',
        16 // tag length 128-bit
    );

    if ($cipher === false) {
        error_log('[MIKHMON] Enkripsi gagal: ' . openssl_error_string());
        return '';
    }

    // Gabung: IV (12) + TAG (16) + CIPHERTEXT, lalu base64
    return base64_encode($iv . $tag . $cipher);
}

/**
 * Dekripsi string AES-256-GCM
 * Input: base64(iv + tag + ciphertext)
 */
function mikhmonDecrypt(string $encoded): string {
    if ($encoded === '') return '';

    $key  = getMikhmonSecretKey();
    $data = base64_decode($encoded, true);

    if ($data === false || strlen($data) < 29) {
        // Data korup atau format lama
        return '';
    }

    $iv         = substr($data, 0, 12);
    $tag        = substr($data, 12, 16);
    $ciphertext = substr($data, 28);

    $plaintext = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($plaintext === false) {
        error_log('[MIKHMON] Dekripsi gagal — data mungkin korup atau key salah.');
        return '';
    }

    return $plaintext;
}

// ─────────────────────────────────────────────
// 3. PASSWORD ADMIN — BCRYPT HASH
// ─────────────────────────────────────────────

function mikhmonHashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function mikhmonVerifyPassword(string $password, string $hash): bool {
    // Support hash lama (base64 XOR) selama masa transisi
    if (strpos($hash, '$2y$') === 0) {
        return password_verify($password, $hash);
    }
    // Legacy fallback — XOR lama
    return ($password === legacyDecrypt($hash));
}

// Legacy decrypt hanya untuk migrasi (akan dihapus setelah re-save semua password)
function legacyDecrypt(string $string, int $key = 128): string {
    $result = '';
    $string = base64_decode($string);
    for ($i = 0, $k = strlen($string); $i < $k; $i++) {
        $char    = substr($string, $i, 1);
        $keychar = substr((string)$key, ($i % strlen((string)$key)) - 1, 1);
        $char    = chr(ord($char) - ord($keychar));
        $result .= $char;
    }
    return $result;
}

// ─────────────────────────────────────────────
// 4. CSRF PROTECTION
// ─────────────────────────────────────────────

function csrfGenerateToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token']      = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function csrfTokenField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfGenerateToken(), ENT_QUOTES) . '">';
}

function csrfValidate(): bool {
    $token     = $_POST['csrf_token'] ?? '';
    $sessToken = $_SESSION['csrf_token'] ?? '';
    $sessTime  = $_SESSION['csrf_token_time'] ?? 0;

    // Token expired setelah 2 jam
    if ((time() - $sessTime) > 7200) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
        return false;
    }

    if (!hash_equals($sessToken, $token)) {
        return false;
    }

    // Rotate token setelah validasi berhasil
    unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
    return true;
}

// ─────────────────────────────────────────────
// 5. RATE LIMITING LOGIN
// ─────────────────────────────────────────────

define('RATE_LIMIT_MAX',    5);   // Maksimal percobaan gagal
define('RATE_LIMIT_WINDOW', 900); // 15 menit (detik)
define('RATE_LIMIT_FILE',   __DIR__ . '/../.ratelimit'); // simpan di luar webroot ideal, tapi ini cukup

function rateLimitCheck(string $ip): bool {
    $data = rateLimitLoad();
    $now  = time();
    $key  = md5($ip);

    if (!isset($data[$key])) return true; // Belum ada record → allow

    $record = $data[$key];

    // Reset jika sudah lewat window
    if (($now - $record['first']) > RATE_LIMIT_WINDOW) {
        unset($data[$key]);
        rateLimitSave($data);
        return true;
    }

    return $record['count'] < RATE_LIMIT_MAX;
}

function rateLimitRecord(string $ip): void {
    $data = rateLimitLoad();
    $now  = time();
    $key  = md5($ip);

    if (!isset($data[$key]) || ($now - $data[$key]['first']) > RATE_LIMIT_WINDOW) {
        $data[$key] = ['count' => 1, 'first' => $now];
    } else {
        $data[$key]['count']++;
    }

    rateLimitSave($data);
}

function rateLimitReset(string $ip): void {
    $data = rateLimitLoad();
    $key  = md5($ip);
    unset($data[$key]);
    rateLimitSave($data);
}

function rateLimitRemaining(string $ip): int {
    $data = rateLimitLoad();
    $key  = md5($ip);
    if (!isset($data[$key])) return RATE_LIMIT_MAX;
    return max(0, RATE_LIMIT_MAX - $data[$key]['count']);
}

function rateLimitLoad(): array {
    if (!file_exists(RATE_LIMIT_FILE)) return [];
    $content = file_get_contents(RATE_LIMIT_FILE);
    $data    = json_decode($content, true);
    return is_array($data) ? $data : [];
}

function rateLimitSave(array $data): void {
    // Bersihkan record lama sebelum simpan
    $now = time();
    foreach ($data as $key => $record) {
        if (($now - $record['first']) > RATE_LIMIT_WINDOW) {
            unset($data[$key]);
        }
    }
    file_put_contents(RATE_LIMIT_FILE, json_encode($data), LOCK_EX);
}

// ─────────────────────────────────────────────
// 6. INPUT SANITIZATION
// ─────────────────────────────────────────────

function sanitizeInput(string $input, string $type = 'text'): string {
    $input = trim($input);

    switch ($type) {
        case 'ip':
            // Validasi IP:Port format (misal: 192.168.1.1:8728)
            $input = preg_replace('/[^0-9a-fA-F:.\[\]]/', '', $input);
            break;
        case 'username':
            $input = preg_replace('/[^a-zA-Z0-9_\-]/', '', $input);
            break;
        case 'hostname':
            $input = preg_replace('/[^a-zA-Z0-9._\-]/', '', $input);
            break;
        case 'alphanumeric':
            $input = preg_replace('/[^a-zA-Z0-9]/', '', $input);
            break;
        case 'text':
        default:
            $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            break;
    }

    return $input;
}

function getClientIP(): string {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

// ─────────────────────────────────────────────
// 7. SECURITY HEADERS (panggil sebelum output HTML)
// ─────────────────────────────────────────────

function sendSecurityHeaders(): void {
    if (headers_sent()) return;
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}
