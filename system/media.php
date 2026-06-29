<?php
// ═══════════════════════════════════════════════════════════
//  Nice Nail — media.php  |  عرض وسائط المحادثات (خلف تسجيل الدخول)
//  الاستخدام: media.php?f=اسم_الملف
// ═══════════════════════════════════════════════════════════
session_start();
require_once __DIR__ . '/lib.php';
if (empty($_SESSION['admin_logged_in'])) { http_response_code(403); exit('forbidden'); }

$f = basename($_GET['f'] ?? '');               // منع الخروج من المجلد
if ($f === '' || !preg_match('/^[A-Za-z0-9_.\-]+$/', $f)) { http_response_code(400); exit('bad'); }
$path = DATA_DIR . '/media/' . $f;
if (!is_file($path)) { http_response_code(404); exit('not found'); }

$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=86400');
readfile($path);
