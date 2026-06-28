<?php
// ═══════════════════════════════════════════════════════════
//  Nice Nail — actions.php  |  معالجة كل العمليات (POST/GET)
// ═══════════════════════════════════════════════════════════
session_start();
require_once __DIR__ . '/lib.php';
if (empty($_SESSION['admin_logged_in'])) { header('Location: index.php'); exit; }

function flash($type, $msg){ $_SESSION['flash'] = ['type'=>$type,'msg'=>$msg]; }
function back($page='dashboard'){
    $ref = $_POST['return'] ?? $_GET['return'] ?? "index.php?page=$page";
    header('Location: ' . $ref); exit;
}
function p($k,$d=''){ return $_POST[$k] ?? $d; }

$do = $_REQUEST['do'] ?? '';

switch ($do) {

// ─────────────────────────────────────────────────────────────
//  الطلبات
// ─────────────────────────────────────────────────────────────
case 'order_create': {
    $services = db_load('services');
    $svc = rec_find($services, p('service_id'));
    $svcName = $svc['name'] ?? p('service_name');
    $ptype = $svc['payment_type'] ?? p('payment_type','full');
    $total = (float) p('total', 0);
    $deposit = ($ptype==='deposit') ? (float)($svc['deposit'] ?? p('deposit',200)) : 0;
    if ($ptype==='free' || $total==0) { $ptype='free'; $total=0; $deposit=0; }

    $phone = normalize_phone(p('phone'));
    $cust = customer_upsert($phone, p('customer_name'), 'client');

    $order = [
        'id'             => gen_id('ord'),
        'date'           => now_str(),
        'customer_id'    => $cust['id'] ?? '',
        'customer_name'  => clean(p('customer_name')),
        'phone'          => $phone,
        'service_id'     => p('service_id'),
        'service_name'   => clean($svcName),
        'design_details' => clean(p('design_details')),
        'payment_type'   => $ptype,                       // deposit | full | free
        'total'          => $total,
        'deposit'        => $deposit,
        'deposit_paid'   => false,
        'remaining_paid' => false,
        'paid'           => 0,
        'appt_date'      => p('appt_date'),
        'appt_time'      => p('appt_time'),
        'notes'          => clean(p('notes')),
        'status'         => 'pending',                    // pending | confirmed | completed | cancelled
    ];
    $orders = db_load('orders');
    $orders[] = $order;
    db_save('orders', $orders);

    // إنشاء موعد مرتبط إن وُجد تاريخ
    if ($order['appt_date']) {
        $appts = db_load('appointments');
        $appts[] = [
            'id'=>gen_id('apt'), 'order_id'=>$order['id'], 'date'=>$order['appt_date'],
            'time'=>$order['appt_time'], 'customer_name'=>$order['customer_name'],
            'phone'=>$order['phone'], 'service_name'=>$order['service_name'],
            'status'=>'pending', 'notes'=>$order['notes'],
        ];
        db_save('appointments', $appts);
    }
    notify_specialist('طلب جديد', $order['customer_name'].' — '.$order['service_name'].' ('.money($order['total']).' ﷼)',
        rtrim((settings_get()['site_url']??''),'/').'/system/index.php?page=orders', 'order');
    flash('ok', 'تم تسجيل الطلب' . ($ptype==='free' ? ' (مجاني)' : ' — جاهز لإرسال رابط الدفع'));
    back('orders');
}

case 'order_update': {
    $id = p('id');
    $total = (float) p('total', 0);
    rec_update('orders', $id, [
        'total'          => $total,
        'design_details' => clean(p('design_details')),
        'notes'          => clean(p('notes')),
    ]);
    flash('ok', 'تم تحديث الطلب');
    back('orders');
}

// تأكيد دفع العربون
case 'order_pay_deposit': {
    $id = $_GET['id'];
    $orders = db_load('orders');
    $o = rec_find($orders, $id);
    if ($o) {
        $amount = $o['deposit'] ?: $o['total'];
        rec_update('orders', $id, ['deposit_paid'=>true,'paid'=>$amount,'status'=>'confirmed']);
        record_payment($o, $o['payment_type']==='deposit' ? 'deposit' : 'full', $amount);
        if ($o['payment_type']!=='deposit') { rec_update('orders',$id,['remaining_paid'=>true,'status'=>'confirmed']); }
        // تأكيد الموعد المرتبط
        confirm_linked_appt($id);
        flash('ok', 'تم تسجيل الدفع وتأكيد الحجز');
    }
    back('orders');
}

// إرسال/تأكيد دفع الباقي (مع إمكانية تعديل المبلغ النهائي)
case 'order_pay_remaining': {
    $id = p('id');
    $orders = db_load('orders');
    $o = rec_find($orders, $id);
    if ($o) {
        $newTotal = (float) p('final_total', $o['total']);
        $remaining = max(0, $newTotal - ($o['deposit'] ?: 0));
        rec_update('orders', $id, [
            'total'=>$newTotal, 'remaining_paid'=>true,
            'paid'=>($o['deposit'] ?: 0) + $remaining, 'status'=>'completed',
        ]);
        record_payment($o, 'remaining', $remaining);
        // زيادة عدد زيارات العميلة + نقاط الولاء
        bump_customer_visit($o['customer_id']);
        complete_linked_appt($id);
        flash('ok', 'تم استلام باقي المبلغ — الطلب مكتمل ✅');
    }
    back('orders');
}

case 'order_status': {
    $id = $_GET['id']; $st = $_GET['status'] ?? 'pending';
    rec_update('orders', $id, ['status'=>$st]);
    if ($st==='cancelled') cancel_linked_appt($id);
    flash('ok', 'تم تحديث حالة الطلب');
    back('orders');
}

case 'order_delete': {
    rec_delete('orders', $_GET['id']);
    flash('ok', 'تم حذف الطلب');
    back('orders');
}

// ─────────────────────────────────────────────────────────────
//  المواعيد
// ─────────────────────────────────────────────────────────────
case 'appt_create': {
    $phone = normalize_phone(p('phone'));
    if ($phone) customer_upsert($phone, p('customer_name'), 'client');
    $appts = db_load('appointments');
    $appts[] = [
        'id'=>gen_id('apt'), 'order_id'=>'', 'date'=>p('date'), 'time'=>p('time'),
        'customer_name'=>clean(p('customer_name')), 'phone'=>$phone,
        'service_name'=>clean(p('service_name')), 'status'=>p('status','pending'),
        'notes'=>clean(p('notes')),
    ];
    db_save('appointments', $appts);
    notify_specialist('موعد جديد', clean(p('customer_name')).' — '.ar_date(p('date')).' '.p('time'),
        rtrim((settings_get()['site_url']??''),'/').'/system/index.php?page=appointments', 'booking');
    flash('ok', 'تمت إضافة الموعد');
    back('appointments');
}
case 'appt_status': {
    rec_update('appointments', $_GET['id'], ['status'=>$_GET['status']]);
    flash('ok', 'تم تحديث حالة الموعد');
    back('appointments');
}
case 'appt_delete': {
    rec_delete('appointments', $_GET['id']);
    flash('ok', 'تم حذف الموعد');
    back('appointments');
}

// ─────────────────────────────────────────────────────────────
//  العملاء
// ─────────────────────────────────────────────────────────────
case 'customer_create': {
    $c = customer_upsert(normalize_phone(p('phone')), p('name'), p('status','client'));
    if ($c) rec_update('customers', $c['id'], ['notes'=>clean(p('notes'))]);
    flash('ok', 'تم حفظ العميلة');
    back('customers');
}
case 'customer_update': {
    rec_update('customers', p('id'), [
        'name'=>clean(p('name')), 'phone'=>normalize_phone(p('phone')),
        'status'=>p('status'), 'notes'=>clean(p('notes')),
    ]);
    flash('ok', 'تم تحديث بيانات العميلة');
    back('customers');
}
case 'customer_delete': {
    rec_delete('customers', $_GET['id']);
    flash('ok', 'تم حذف العميلة');
    back('customers');
}
case 'broadcast_send': {
    $segment = p('segment','all');
    $msg = p('message');
    $customers = db_load('customers');
    $targets = array_filter($customers, function($c) use ($segment){
        if ($segment==='all') return true;
        if ($segment==='visitors') return ($c['status']??'')==='visitor';
        if ($segment==='clients') return ($c['status']??'')==='client';
        if ($segment==='trainees') return ($c['status']??'')==='trainee';
        return true;
    });
    $b = db_load('broadcasts');
    $b[] = [
        'id'=>gen_id('bc'), 'date'=>now_str(), 'segment'=>$segment,
        'message'=>clean($msg), 'count'=>count($targets),
        'recipients'=>array_values(array_map(fn($c)=>$c['phone'],$targets)),
    ];
    db_save('broadcasts', $b);
    flash('ok', 'تم تجهيز الرسالة الجماعية لـ '.count($targets).' عميلة (تُرسل عبر واتساب API عند التفعيل)');
    back('customers');
}

// ─────────────────────────────────────────────────────────────
//  الخدمات
// ─────────────────────────────────────────────────────────────
case 'service_save': {
    $id = p('id');
    $row = [
        'name'=>clean(p('name')), 'duration'=>clean(p('duration')),
        'price'=>(float)p('price',0), 'payment_type'=>p('payment_type','full'),
        'deposit'=>(float)p('deposit',0), 'active'=>p('active','1')==='1',
    ];
    $services = db_load('services');
    if ($id) { rec_update('services', $id, $row); }
    else {
        $row['id']=gen_id('svc'); $row['order']=count($services)+1;
        $services[]=$row; db_save('services',$services);
    }
    flash('ok', 'تم حفظ الخدمة');
    back('services');
}
case 'service_delete': {
    rec_delete('services', $_GET['id']);
    flash('ok', 'تم حذف الخدمة');
    back('services');
}

// ─────────────────────────────────────────────────────────────
//  الدورات
// ─────────────────────────────────────────────────────────────
case 'course_create': {
    $phone = normalize_phone(p('phone'));
    if ($phone) customer_upsert($phone, p('name'), 'trainee');
    $courses = db_load('courses');
    $courses[] = [
        'id'=>gen_id('crs'), 'date'=>now_str(), 'name'=>clean(p('name')),
        'phone'=>$phone, 'course_date'=>p('course_date'),
        'payment_status'=>p('payment_status','pending'),
        'price'=>(float)p('price', 4500), 'notes'=>clean(p('notes')),
    ];
    db_save('courses', $courses);
    flash('ok', 'تم تسجيل المتدربة');
    back('courses');
}
case 'course_pay': {
    $id=$_GET['id']; $courses=db_load('courses'); $c=rec_find($courses,$id);
    if ($c){
        rec_update('courses',$id,['payment_status'=>'paid']);
        record_payment([
            'id'=>$c['id'],'customer_name'=>$c['name'],'phone'=>$c['phone'],
            'service_name'=>'دورة جل اكستنشن'
        ],'course',$c['price'] ?? 4500);
        flash('ok','تم تأكيد دفع الدورة');
    }
    back('courses');
}
case 'course_update': {
    rec_update('courses', p('id'), [
        'name'=>clean(p('name')), 'phone'=>normalize_phone(p('phone')),
        'course_date'=>p('course_date'), 'payment_status'=>p('payment_status'),
        'notes'=>clean(p('notes')),
    ]);
    flash('ok','تم تحديث بيانات المتدربة');
    back('courses');
}
case 'course_delete': {
    rec_delete('courses', $_GET['id']);
    flash('ok','تم حذف المتدربة');
    back('courses');
}

// ─────────────────────────────────────────────────────────────
//  الإعدادات
// ─────────────────────────────────────────────────────────────
case 'settings_save': {
    $s = settings_get();
    $s['biz_name']=clean(p('biz_name',$s['biz_name']??'Nice Nail'));
    if(isset($_POST['site_url'])) $s['site_url']=rtrim(clean(p('site_url')),'/');
    $s['specialist_name']=clean(p('specialist_name',$s['specialist_name']??''));
    $s['phone']=normalize_phone(p('phone',$s['phone']??''));
    $s['instagram']=clean(p('instagram',$s['instagram']??''));
    $s['working_hours']=clean(p('working_hours',$s['working_hours']??''));
    $s['default_deposit']=(float)p('default_deposit',$s['default_deposit']??200);
    $s['cancel_policy_hours']=(int)p('cancel_policy_hours',$s['cancel_policy_hours']??24);
    $s['terms']=trim(p('terms',$s['terms']??''));
    $s['geidea']['enabled']=p('geidea_enabled','0')==='1';
    $s['geidea']['merchant_id']=clean(p('geidea_merchant',$s['geidea']['merchant_id']??''));
    $s['geidea']['api_key']=clean(p('geidea_key',$s['geidea']['api_key']??''));
    $s['geidea']['base_link']=clean(p('geidea_link',$s['geidea']['base_link']??''));
    $s['whatsapp']['enabled']=p('wa_enabled','0')==='1';
    $s['whatsapp']['verify_token']=clean(p('wa_verify',$s['whatsapp']['verify_token']??''));
    $s['whatsapp']['access_token']=trim(p('wa_token',$s['whatsapp']['access_token']??''));
    $s['whatsapp']['phone_number_id']=clean(p('wa_phoneid',$s['whatsapp']['phone_number_id']??''));
    settings_save($s);
    flash('ok','تم حفظ الإعدادات');
    back('settings');
}
case 'bot_save': {
    $s = settings_get();
    foreach (['greeting','booking_intro','booking_service_q','booking_link','to_human','course_info','samples'] as $k){
        if (isset($_POST['bot_'.$k])) $s['bot'][$k]=trim($_POST['bot_'.$k]);
    }
    settings_save($s);
    flash('ok','تم حفظ نصوص البوت');
    back('settings');
}

// ─────────────────────────────────────────────────────────────
//  الـ Inbox والإشعارات
// ─────────────────────────────────────────────────────────────
case 'wa_reply': {
    $phone = normalize_phone(p('phone'));
    $text  = trim((string)p('text'));
    if ($phone && $text !== '') {
        conv_set_mode($phone, 'human');   // الرد اليدوي يوقف البوت لهالمحادثة
        wa_send_message($phone, $text, true, 'human');
        conv_mark_read($phone);
    }
    back('inbox');
}
case 'wa_set_mode': {
    $phone = normalize_phone($_GET['phone'] ?? p('phone'));
    $mode  = ($_GET['mode'] ?? p('mode')) === 'human' ? 'human' : 'bot';
    if ($phone) conv_set_mode($phone, $mode);
    back('inbox');
}
case 'notify_save': {
    $s = settings_get();
    $s['telegram']['enabled']   = p('tg_enabled','0')==='1';
    $s['telegram']['bot_token'] = trim(p('tg_token', $s['telegram']['bot_token'] ?? ''));
    $s['telegram']['chat_id']   = trim(p('tg_chat',  $s['telegram']['chat_id'] ?? ''));
    $s['webpush']['enabled']    = p('wp_enabled','0')==='1';
    $s['notify']['on_message']  = p('n_message','0')==='1';
    $s['notify']['on_order']    = p('n_order','0')==='1';
    $s['notify']['on_booking']  = p('n_booking','0')==='1';
    $s['notify']['on_course']   = p('n_course','0')==='1';
    settings_save($s);
    // اختبار تيليجرام إن طُلب
    if (isset($_POST['tg_test']) && !empty($s['telegram']['enabled']) && !empty($s['telegram']['bot_token']) && !empty($s['telegram']['chat_id'])) {
        telegram_send($s['telegram'], "✅ تجربة إشعار من نظام Nice Nail — التيليجرام يعمل!");
        flash('ok','تم حفظ الإعدادات وإرسال رسالة تجربة على تيليجرام');
    } else {
        flash('ok','تم حفظ إعدادات الإشعارات');
    }
    back('settings');
}

default:
    flash('err', 'عملية غير معروفة');
    back('dashboard');
}

// ── دوال مساعدة للعمليات ───────────────────────────────────
function record_payment($o, $type, $amount){
    if ($amount <= 0) return;
    $p = db_load('payments');
    $p[] = [
        'id'=>gen_id('pay'), 'date'=>now_str(), 'order_id'=>$o['id'] ?? '',
        'customer_name'=>$o['customer_name'] ?? '', 'phone'=>$o['phone'] ?? '',
        'service_name'=>$o['service_name'] ?? '', 'type'=>$type, 'amount'=>(float)$amount,
    ];
    db_save('payments', $p);
}
function bump_customer_visit($cid){
    if (!$cid) return;
    $list = db_load('customers');
    foreach ($list as &$c){
        if (($c['id']??'')===$cid){
            $c['visits']=($c['visits']??0)+1;
            $c['points']=($c['points']??0)+1;
        }
    }
    unset($c); db_save('customers',$list);
}
function confirm_linked_appt($orderId){ set_appt_status_by_order($orderId,'confirmed'); }
function complete_linked_appt($orderId){ set_appt_status_by_order($orderId,'completed'); }
function cancel_linked_appt($orderId){ set_appt_status_by_order($orderId,'cancelled'); }
function set_appt_status_by_order($orderId,$st){
    $appts=db_load('appointments'); $ch=false;
    foreach ($appts as &$a){ if(($a['order_id']??'')===$orderId){ $a['status']=$st; $ch=true; } }
    unset($a); if($ch) db_save('appointments',$appts);
}
