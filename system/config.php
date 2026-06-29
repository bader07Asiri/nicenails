<?php
// ═══════════════════════════════════════════════════════════
//  Nice Nail — نظام الإدارة الداخلي  |  config.php
//  الإعدادات العامة ومسارات الملفات
// ═══════════════════════════════════════════════════════════

// كلمة مرور الدخول — غيّريها قبل الرفع!
if (!defined('ADMIN_PASSWORD')) define('ADMIN_PASSWORD', 'NiceNail2026');

// اسم النشاط ورقم الواتساب الرسمي (يظهر في الواجهة والروابط)
if (!defined('BIZ_NAME'))   define('BIZ_NAME', 'Nice Nail');
if (!defined('BIZ_PHONE'))  define('BIZ_PHONE', '966500000000'); // بصيغة دولية بدون +

// المنطقة الزمنية
date_default_timezone_set('Asia/Riyadh');

// مسارات البيانات
define('BASE_DIR', __DIR__);
define('DATA_DIR', __DIR__ . '/data');

// خريطة ملفات البيانات
$GLOBALS['DATA_FILES'] = [
    'services'     => DATA_DIR . '/services.json',
    'customers'    => DATA_DIR . '/customers.json',
    'orders'       => DATA_DIR . '/orders.json',
    'appointments' => DATA_DIR . '/appointments.json',
    'courses'      => DATA_DIR . '/courses.json',
    'payments'     => DATA_DIR . '/payments.json',
    'broadcasts'   => DATA_DIR . '/broadcasts.json',
    'settings'     => DATA_DIR . '/settings.json',
    'trash'        => DATA_DIR . '/trash.json',
];
