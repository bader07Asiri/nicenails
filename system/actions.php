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
    if ($st==='cancelled') {
        $o = rec_find(db_load('orders'), $id);
        // إلغاء طلب فيه مبلغ مدفوع = إرجاع المبلغ تلقائياً (يخرج من الإيرادات)
        if ($o && (float)($o['paid']??0) > 0 && empty($o['refunded'])) {
            record_refund($o, (float)$o['paid']);
            rec_update('orders', $id, ['refunded'=>true,'refund_amount'=>(float)$o['paid'],'refunded_at'=>now_str()]);
        }
        cancel_linked_appt($id);
    }
    rec_update('orders', $id, ['status'=>$st]);
    flash('ok', $st==='cancelled' ? 'تم إلغاء الطلب' : 'تم تحديث حالة الطلب');
    back('orders');
}

case 'order_delete': {
    $id = $_GET['id'];
    $o  = rec_find(db_load('orders'), $id);
    // حارس المبلغ: لا يُحذف طلب فيه فلوس مدفوعة حتى يتضح وضع المبلغ (مرتجع)
    if ($o && (float)($o['paid']??0) > 0 && empty($o['refunded'])) {
        flash('err', 'لا يمكن حذف الطلب — يوجد مبلغ مدفوع ('.money($o['paid']).' ﷼). نفّذي «مرتجع وإلغاء» أولاً لإرجاع المبلغ، ثم احذفي.');
        back('orders');
    }
    soft_delete('orders', $id);
    flash('ok', 'تم نقل الطلب إلى سلة المهملات');
    back('orders');
}

// مرتجع: إرجاع المبلغ المدفوع وإلغاء الطلب (يخرج من الإيرادات، يصير ضمن الملغاة)
case 'order_refund': {
    $id = $_GET['id'] ?? p('id');
    $o  = rec_find(db_load('orders'), $id);
    if ($o) {
        $paid = (float)($o['paid'] ?? 0);
        if ($paid > 0 && empty($o['refunded'])) record_refund($o, $paid);
        rec_update('orders', $id, ['status'=>'cancelled','refunded'=>true,'refund_amount'=>$paid,'refunded_at'=>now_str()]);
        cancel_linked_appt($id);
        flash('ok', 'تم إرجاع المبلغ ('.money($paid).' ﷼) وإلغاء الطلب');
    }
    back('orders');
}

// ─────────────────────────────────────────────────────────────
//  سلة المهملات
// ─────────────────────────────────────────────────────────────
case 'trash_restore': {
    trash_restore($_GET['id'] ?? p('id'));
    flash('ok', 'تمت استعادة العنصر إلى مكانه');
    back('trash');
}
case 'trash_purge': {
    trash_purge($_GET['id'] ?? p('id'));
    flash('ok', 'تم حذف العنصر نهائياً');
    back('trash');
}
case 'trash_empty': {
    trash_empty();
    flash('ok', 'تم تفريغ سلة المهملات نهائياً');
    back('trash');
}

// تنظيف لمرة واحدة: مدفوعات قديمة لطلبات/دورات محذوفة سابقاً (تبقى عالقة في القوائم المالية)
case 'payments_cleanup': {
    $orderIds = array_column(db_load('orders'), 'id');
    $courseIds = array_column(db_load('courses'), 'id');
    $valid = array_merge($orderIds, $courseIds);
    $n = 0;
    foreach (db_load('payments') as $pp) {
        $oid = $pp['order_id'] ?? '';
        if ($oid !== '' && !in_array($oid, $valid, true)) { soft_delete('payments', $pp['id']); $n++; }
    }
    flash($n ? 'ok' : 'err', $n ? "تم نقل $n عملية دفع يتيمة (بلا طلب) إلى سلة المهملات — القوائم المالية صارت سليمة." : 'لا توجد مدفوعات يتيمة — القوائم سليمة.');
    back('finance');
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
    soft_delete('appointments', $_GET['id']);
    flash('ok', 'تم نقل الموعد إلى سلة المهملات');
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
    soft_delete('customers', $_GET['id']);
    flash('ok', 'تم نقل العميلة إلى سلة المهملات');
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
    soft_delete('services', $_GET['id']);
    flash('ok', 'تم نقل الخدمة إلى سلة المهملات');
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
    soft_delete('courses', $_GET['id']);
    flash('ok','تم نقل المتدربة إلى سلة المهملات');
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
    if ($phone) {
        conv_set_mode($phone, 'human');   // الرد اليدوي يوقف البوت لهالمحادثة
        if (!empty($_FILES['image']) && ($_FILES['image']['error'] ?? 1) === UPLOAD_ERR_OK) {
            $tmp = $_FILES['image']['tmp_name'];
            $mime = mime_content_type($tmp) ?: 'image/jpeg';
            if (strpos($mime, 'image/') === 0) {
                $ext = mime_ext($mime);
                $fname = gen_id('out') . '.' . $ext;
                @move_uploaded_file($tmp, media_dir() . '/' . $fname);
                $mid = wa_upload_media(media_dir() . '/' . $fname, $mime);
                if ($mid) wa_send_image($phone, $mid, $text);
                msg_add($phone, '', 'out', $text, 'human', ['type'=>'image','file'=>$fname,'mime'=>$mime]);
            }
        } elseif ($text !== '') {
            wa_send_message($phone, $text, true, 'human');
        }
        conv_mark_read($phone);
    }
    back('inbox');
}
case 'import_whatsapp': {
    if (empty($_FILES['chatfile']) || ($_FILES['chatfile']['error'] ?? 1) !== UPLOAD_ERR_OK) {
        flash('err', 'لم يتم رفع ملف'); back('import');
    }
    $raw = file_get_contents($_FILES['chatfile']['tmp_name']);
    $added = whatsapp_import($raw);
    flash('ok', 'تم استيراد '.$added.' رسالة بنجاح');
    back('import');
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

// ── استيراد محادثات واتساب (JSON من WhatsApp-Chat-Exporter أو txt) ──
function whatsapp_import($raw) {
    $raw = trim($raw);
    $msgs = db_load('messages');
    $before = count($msgs);
    $j = json_decode($raw, true);
    if (is_array($j)) {
        // صيغة JSON: مفاتيح = jid، كل واحد فيه name + messages
        foreach ($j as $jid => $chat) {
            if (!is_array($chat)) continue;
            $phone = normalize_phone(preg_replace('/[^0-9].*$/', '', (string)$jid));
            if (!$phone) continue;
            $name = $chat['name'] ?? '';
            customer_upsert($phone, $name, 'client');
            $list = $chat['messages'] ?? $chat;
            if (!is_array($list)) continue;
            foreach ($list as $m) {
                if (!is_array($m)) continue;
                $text = $m['data'] ?? ($m['text'] ?? ($m['caption'] ?? ''));
                $hasMedia = !empty($m['media']);
                if ($text === '' && !$hasMedia) continue;
                $dir = !empty($m['from_me']) ? 'out' : 'in';
                $ts = isset($m['timestamp']) && $m['timestamp']
                      ? date('Y-m-d H:i:s', (int)$m['timestamp'])
                      : (($m['date'] ?? date('Y-m-d')).' '.($m['time'] ?? '00:00').':00');
                $msgs[] = [
                    'id'=>gen_id('imp'), 'phone'=>$phone, 'name'=>clean($name),
                    'dir'=>$dir, 'text'=>($hasMedia && $text==='' ? '[وسائط]' : (string)$text),
                    'via'=>'import', 'ts'=>$ts, 'read'=>true,
                ];
            }
        }
    } else {
        // صيغة txt: لا نعرف الرقم — نطلب من المستخدم رفع JSON بدلاً
        return 0;
    }
    // رتّب زمنياً
    usort($msgs, fn($a,$b)=>strcmp($a['ts']??'', $b['ts']??''));
    db_save('messages', $msgs);
    return count($msgs) - $before;
}

function record_refund($o, $amount){
    if ($amount <= 0) return;
    $p = db_load('payments');
    $p[] = [
        'id'=>gen_id('pay'), 'date'=>now_str(), 'order_id'=>$o['id'] ?? '',
        'customer_name'=>$o['customer_name'] ?? '', 'phone'=>$o['phone'] ?? '',
        'service_name'=>$o['service_name'] ?? '', 'type'=>'refund', 'amount'=> -1 * (float)$amount,
    ];
    db_save('payments', $p);
}
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
