<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *  Modified: Security hardening — AES-256-GCM, input sanitization
 */
session_start();
error_reporting(0);

if (substr($_SERVER["REQUEST_URI"], -11) == "readcfg.php") {
    header("Location:./");
    exit;
}

// Load security module (wajib sebelum apapun)
if (!function_exists('mikhmonDecrypt')) {
    require_once dirname(__DIR__) . '/include/security.php';
}

// Kirim security headers
sendSecurityHeaders();

// Baca config dengan sanitasi input
// ?? '' untuk handle null saat session belum dipilih
$iphost      = sanitizeInput(explode('!',   $data[$session][1]  ?? '')[1]  ?? '', 'ip');
$userhost    = sanitizeInput(explode('@|@', $data[$session][2]  ?? '')[1]  ?? '', 'username');
$passwdhost  = decrypt(     (explode('#|#', $data[$session][3]  ?? '')[1]  ?? ''));
$hotspotname = sanitizeInput(explode('%',   $data[$session][4]  ?? '')[1]  ?? '', 'text');
$dnsname     = sanitizeInput(explode('^',   $data[$session][5]  ?? '')[1]  ?? '', 'hostname');
$currency    = sanitizeInput(explode('&',   $data[$session][6]  ?? '')[1]  ?? '', 'text');
$areload     = (int)        (explode('*',   $data[$session][7]  ?? '10')[1] ?? 10);
$areload     = ($areload < 10) ? 10 : $areload;
$iface       = sanitizeInput(explode('(',   $data[$session][8]  ?? '')[1]  ?? '', 'text');
$infolp      =               explode(')',   $data[$session][9]  ?? '')[1]  ?? '';
$idleto      = sanitizeInput(explode('=',   $data[$session][10] ?? '')[1]  ?? '', 'text');
$sesname     = sanitizeInput(explode('+',   $data[$session][10] ?? '')[1]  ?? '', 'text');
$useradm     = sanitizeInput(explode('<|<', $data['mikhmon'][1] ?? '')[1]  ?? '', 'username');
$passadm     =               explode('>|>', $data['mikhmon'][2] ?? '')[1]  ?? '';
$livereport  = sanitizeInput(explode('@!@', $data[$session][11] ?? '')[1]  ?? '', 'text');

$cekindo['indo'] = array(
    'RP', 'Rp', 'rp', 'IDR', 'idr', 'RP.', 'Rp.', 'rp.', 'IDR.', 'idr.',
);