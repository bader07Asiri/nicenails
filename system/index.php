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
    'import'       => ['ic'=>'📥','t'=>'استيراد',          's'=>'استيراد محادثات واتساب'],
    'trash'        => ['ic'=>'🗑️','t'=>'سلة المهملات','s'=>'العناصر المحذوفة — استرجاع أو حذف نهائي'],
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
        $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
        if ($flash): ?><div class="flash <?= h($flash['type']) ?>"><?= $flash['type']==='ok'?'✅':'⚠️' ?> <?= h($flash['msg']) ?></div><?php endif;
        $pageFile = __DIR__ . "/pages/$page.php";
        if (file_exists($pageFile)) include $pageFile; else echo '<div class="empty">الصفحة غير موجودة</div>';
      ?>
    </div>
  </div>
</div>
<script src="assets/app.js"></script>
<script>
if ('serviceWorker' in navigator) { navigator.serviceWorker.register('sw.js').catch(function(e){console.log('SW',e);}); }
function urlB64ToUint8(b64){var p='='.repeat((4-b64.length%4)%4);var s=(b64+p).replace(/-/g,'+').replace(/_/g,'/');var raw=atob(s);var arr=new Uint8Array(raw.length);for(var i=0;i<raw.length;i++)arr[i]=raw.charCodeAt(i);return arr;}
async function enablePush(){
  try{
    if(!('serviceWorker' in navigator)||!('PushManager' in window)){alert('جهازك لا يدعم الإشعارات. على آيفون: ثبّتي التطبيق على الشاشة الرئيسية أولاً (iOS 16.4+).');return;}
    var perm=await Notification.requestPermission();
    if(perm!=='granted'){alert('لم يتم السماح بالإشعارات.');return;}
    var reg=await navigator.serviceWorker.ready;
    var kr=await fetch('api.php?do=vapid_key').then(r=>r.json());
    if(!kr.key){alert('مفتاح الإشعارات غير مُعد.');return;}
    var sub=await reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:urlB64ToUint8(kr.key)});
    var res=await fetch('api.php?do=push_subscribe',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({subscription:sub})}).then(r=>r.json());
    alert(res.ok?'تم تفعيل الإشعارات على هذا الجهاز ✅':'تعذّر التفعيل.');
  }catch(e){console.log(e);alert('خطأ في تفعيل الإشعارات: '+e.message);}
}
setInterval(function(){
  fetch('api.php?do=unread').then(r=>r.json()).then(function(d){
    if(d&&typeof d.messages!=='undefined'){
      var link=document.querySelector('.side-link[href="index.php?page=inbox"]');
      if(!link)return;
      var b=link.querySelector('.badge');
      if(d.messages>0){ if(!b){b=document.createElement('span');b.className='badge';link.appendChild(b);} b.textContent=d.messages; }
      else if(b){ b.remove(); }
    }
  }).catch(function(){});
},25000);
</script>
</body></html>
