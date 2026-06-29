<?php
// ═══════════════════════════════════════════════════════════
//  Nice Nail — lib.php  |  طبقة البيانات والدوال المساعدة
// ═══════════════════════════════════════════════════════════
require_once __DIR__ . '/config.php';

// ── قراءة/كتابة JSON بأمان مع قفل ───────────────────────────
function db_path($name) {
    return $GLOBALS['DATA_FILES'][$name] ?? (DATA_DIR . "/$name.json");
}

function db_load($name) {
    $file = db_path($name);
    if (!file_exists($file) || filesize($file) === 0) return [];
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function db_save($name, $data) {
    $file = db_path($name);
    if (!is_dir(dirname($file))) mkdir(dirname($file), 0755, true);
    $lock = fopen($file . '.lock', 'c');
    flock($lock, LOCK_EX);
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    flock($lock, LOCK_UN);
    fclose($lock);
}

// قراءة الإعدادات ككائن واحد (مع دمج الافتراضي)
function settings_get() {
    $s = db_load('settings');
    if (!$s || !is_array($s) || array_keys($s) === range(0, count($s) - 1)) {
        $s = [];
    }
    return $s;
}
function settings_save($s) { db_save('settings', $s); }

// ── معرّفات ووقت ────────────────────────────────────────────
function gen_id($prefix = 'id') {
    return $prefix . '_' . substr(bin2hex(random_bytes(6)), 0, 10);
}
function now_str() { return date('Y-m-d H:i:s'); }
function today() { return date('Y-m-d'); }

// ── تنظيف وحماية ────────────────────────────────────────────
function clean($val) {
    return htmlspecialchars(strip_tags(trim((string)$val)), ENT_QUOTES, 'UTF-8');
}
function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

// توحيد رقم الجوال (يحوّل لصيغة دولية 9665XXXXXXXX قدر الإمكان)
function normalize_phone($p) {
    $p = preg_replace('/[^0-9]/', '', (string)$p);
    if ($p === '') return '';
    if (strpos($p, '966') === 0) return $p;
    if (strpos($p, '05') === 0)  return '966' . substr($p, 1);
    if (strpos($p, '5') === 0 && strlen($p) === 9) return '966' . $p;
    return $p;
}
function wa_link($phone, $text = '') {
    $p = normalize_phone($phone);
    $u = "https://wa.me/$p";
    if ($text !== '') $u .= '?text=' . rawurlencode($text);
    return $u;
}

// ── المصادقة ────────────────────────────────────────────────
function require_login() {
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: index.php?page=login');
        exit;
    }
}

// ── العملات والأرقام ────────────────────────────────────────
function money($n) {
    return number_format((float)$n, ($n == intval($n) ? 0 : 2), '.', ',');
}
function ar_date($d) {
    if (!$d) return '';
    $ts = strtotime($d);
    $days = ['Sunday'=>'الأحد','Monday'=>'الإثنين','Tuesday'=>'الثلاثاء','Wednesday'=>'الأربعاء','Thursday'=>'الخميس','Friday'=>'الجمعة','Saturday'=>'السبت'];
    $dayName = $days[date('l', $ts)] ?? '';
    return $dayName . ' ' . date('Y-m-d', $ts);
}

// ── أدوات السجلات (إيجاد/تحديث/حذف عنصر بـ id) ──────────────
function rec_find($list, $id) {
    foreach ($list as $r) if (($r['id'] ?? null) === $id) return $r;
    return null;
}
function rec_update($name, $id, $changes) {
    $list = db_load($name);
    foreach ($list as &$r) {
        if (($r['id'] ?? null) === $id) { $r = array_merge($r, $changes); break; }
    }
    unset($r);
    db_save($name, $list);
}
function rec_delete($name, $id) {
    $list = db_load($name);
    $list = array_values(array_filter($list, fn($r) => ($r['id'] ?? null) !== $id));
    db_save($name, $list);
}

// ── أرشفة العميلة تلقائياً من رقم الجوال ────────────────────
function customer_upsert($phone, $name = '', $status = '') {
    $phone = normalize_phone($phone);
    if (!$phone) return null;
    $list = db_load('customers');
    foreach ($list as &$c) {
        if (normalize_phone($c['phone'] ?? '') === $phone) {
            if ($name && empty($c['name'])) $c['name'] = clean($name);
            if ($status) $c['status'] = $status;
            $found = $c;
            db_save('customers', $list);
            return $found;
        }
    }
    unset($c);
    $new = [
        'id'            => gen_id('cust'),
        'name'          => clean($name),
        'phone'         => $phone,
        'status'        => $status ?: 'visitor', // visitor | client | trainee
        'notes'         => '',
        'first_contact' => now_str(),
        'visits'        => 0,
        'points'        => 0,
        'history'       => [],
    ];
    $list[] = $new;
    db_save('customers', $list);
    return $new;
}

require_once __DIR__ . "/comm.php";

// ═══════════════════════════════════════════════════════════
//  سلة المهملات (Soft Delete / Recycle Bin)
//  أي عنصر يُحذف يُنقل إلى trash.json ويختفي من كل الصفحات،
//  ويمكن استرجاعه. تفريغ السلة = حذف نهائي لا رجعة فيه.
// ═══════════════════════════════════════════════════════════
function trash_label($type, $r){
    switch($type){
        case 'orders':       return 'طلب — '.($r['customer_name']??'').' · '.($r['service_name']??'');
        case 'customers':    return 'عميلة — '.($r['name']??'').' · '.($r['phone']??'');
        case 'services':     return 'خدمة — '.($r['name']??'');
        case 'appointments': return 'موعد — '.($r['customer_name']??'').' · '.($r['date']??'').' '.($r['time']??'');
        case 'courses':      return 'متدربة — '.($r['name']??'');
        case 'payments':     return 'دفعة — '.($r['customer_name']??'').' · '.money($r['amount']??0).' ﷼';
    }
    return $type;
}

// نقل عنصر (وما يرتبط به) إلى سلة المهملات
function soft_delete($type, $id){
    $list = db_load($type);
    $rec = null;
    foreach($list as $r){ if(($r['id']??null)===$id){ $rec=$r; break; } }
    if(!$rec) return false;

    // إزالة العنصر من مخزنه الأصلي
    $list = array_values(array_filter($list, fn($r)=>($r['id']??null)!==$id));
    db_save($type, $list);

    $related = [];
    // تتالي: المدفوعات المرتبطة (للطلبات والدورات) — تُزال من القوائم المالية أيضاً
    if($type==='orders' || $type==='courses'){
        $pays = db_load('payments'); $keep=[];
        foreach($pays as $p){
            if(($p['order_id']??'')===$id) $related[] = ['type'=>'payments','data'=>$p];
            else $keep[]=$p;
        }
        if(count($keep)!==count($pays)) db_save('payments',$keep);
    }
    // تتالي: المواعيد المرتبطة بالطلب
    if($type==='orders'){
        $appts = db_load('appointments'); $keep=[];
        foreach($appts as $a){
            if(($a['order_id']??'')===$id) $related[] = ['type'=>'appointments','data'=>$a];
            else $keep[]=$a;
        }
        if(count($keep)!==count($appts)) db_save('appointments',$keep);
    }

    $trash = db_load('trash');
    $trash[] = [
        'id'         => gen_id('trash'),
        'type'       => $type,
        'label'      => trash_label($type,$rec),
        'deleted_at' => now_str(),
        'data'       => $rec,
        'related'    => $related,
    ];
    db_save('trash', $trash);
    return true;
}

// استرجاع عنصر من السلة إلى مكانه الأصلي (مع ما يرتبط به)
function trash_restore($trashId){
    $trash = db_load('trash');
    $entry = null;
    foreach($trash as $t){ if(($t['id']??null)===$trashId){ $entry=$t; break; } }
    if(!$entry) return false;

    $list = db_load($entry['type']); $list[] = $entry['data']; db_save($entry['type'], $list);
    foreach(($entry['related']??[]) as $rel){
        $rl = db_load($rel['type']); $rl[] = $rel['data']; db_save($rel['type'], $rl);
    }
    $trash = array_values(array_filter($trash, fn($t)=>($t['id']??null)!==$trashId));
    db_save('trash', $trash);
    return true;
}

// حذف نهائي لعنصر واحد من السلة
function trash_purge($trashId){
    $trash = db_load('trash');
    $trash = array_values(array_filter($trash, fn($t)=>($t['id']??null)!==$trashId));
    db_save('trash', $trash);
}

// تفريغ السلة بالكامل — حذف نهائي لا رجعة فيه
function trash_empty(){ db_save('trash', []); }
