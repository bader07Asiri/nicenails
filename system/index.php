<?php
// ═══════════════════════════════════════════════════════════
//  Nice Nail — index.php  |  الراوتر + المصادقة + التخطيط
// ═══════════════════════════════════════════════════════════
session_start();
require_once __DIR__ . '/lib.php';

// ── تسجيل الخروج
if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }

// ── تسجيل الدخول
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (hash_equals(ADMIN_PASSWORD, $_POST['password'])) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php'); exit;
    }
    $loginError = 'كلمة المرور غير صحيحة';
}

// ── شاشة الدخول
if (empty($_SESSION['admin_logged_in'])) {
    ?><!DOCTYPE html><html lang="ar" dir="rtl"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>دخول — Nice Nail</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    </head><body><div class="login-wrap"><form class="login-card" method="post">
      <img src="assets/logo.png" alt="Nice Nail">
      <h1>Nice Nail</h1><p>نظام الإدارة الداخلي</p>
      <?php if ($loginError): ?><div class="login-err"><?= h($loginError) ?></div><?php endif; ?>
      <input type="password" name="password" placeholder="كلمة المرور" autofocus required>
      <button class="btn btn-primary btn-block" type="submit">دخول</button>
    </form></div></body></html><?php
    exit;
}

// ── الصفحات المتاحة
$pages = [
    'dashboard'    => ['ic'=>'📊','t'=>'الرئيسية',       's'=>'نظرة عامة على الأداء'],
    'inbox'        => ['ic'=>'💬','t'=>'المحادثات',       's'=>'واتساب — الرد على العميلات'],
    'appointments' => ['ic'=>'🗓️','t'=>'المواعيد',        's'=>'التقويم وإدارة الحجوزات'],
    'orders'       => ['ic'=>'💳','t'=>'الطلبات',         's'=>'الطلبات والمدفوعات'],
    'customers'    => ['ic'=>'👩','t'=>'العملاء',         's'=>'قاعدة العملاء والرسائل'],
    'services'     => ['ic'=>'💅','t'=>'الخدمات والأسعار', 's'=>'إدارة الخدمات'],
    'courses'      => ['ic'=>'🎓','t'=>'الدورات',         's'=>'دورة جل اكستنشن'],
    'finance'      => ['ic'=>'📈','t'=>'القوائم المالية',  's'=>'التقارير والإيرادات'],
    'settings'     => ['ic'=>'⚙️','t'=>'الإعدادات',        's'=>'الواتساب والشروط والبوت'],
];
$page = $_GET['page'] ?? 'dashboard';
if (!isset($pages[$page])) $page = 'dashboard';

// عدادات الشارات
$ordersAll = db_load('orders');
$pendingOrders = count(array_filter($ordersAll, fn($o)=>in_array($o['status']??'', ['pending','confirmed'])));
$apptAll = db_load('appointments');
$todayAppts = count(array_filter($apptAll, fn($a)=>($a['date']??'')===today() && ($a['status']??'')!=='cancelled'));
$badges = ['orders'=>$pendingOrders ?: '', 'appointments'=>$todayAppts ?: '', 'inbox'=>unread_total() ?: ''];
?>
<!DOCTYPE html><html lang="ar" dir="rtl"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($pages[$page]['t']) ?> — Nice Nail</title>
<link rel="icon" href="assets/logo.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#C8686B">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Nice Nail">
<link rel="apple-touch-icon" href="assets/icon-192.png">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head><body>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="side-brand">
      <img src="assets/logo.png" alt="">
      <div><div class="bn">Nice Nail</div><div class="bs">نظام الإدارة</div></div>
    </div>
    <nav class="side-nav">
      <?php foreach ($pages as $key=>$p): ?>
        <a class="side-link <?= $key===$page?'active':'' ?>" href="index.php?page=<?= $key ?>">
          <span class="ic"><?= $p['ic'] ?></span><span><?= h($p['t']) ?></span>
          <?php if (!empty($badges[$key])): ?><span class="badge"><?= $badges[$key] ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="../index_landing.html" target="_blank">🌐 صفحة الهبوط</a>
      <a href="index.php?logout=1">🚪 تسجيل الخروج</a>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <div style="display:flex;align-items:center;gap:.8rem">
        <button class="menu-btn" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
        <div><h2><?= h($pages[$page]['t']) ?></h2><div class="sub"><?= h($pages[$page]['s']) ?></div></div>
      </div>
      <div class="topbar-right">
        <span class="muted" style="font-size:.78rem"><?= ar_date(today()) ?></span>
      </div>
    </div>
    <div class="content">
      <?php