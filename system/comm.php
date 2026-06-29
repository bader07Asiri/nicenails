<?php
// ═══════════════════════════════════════════════════════════
//  Nice Nail — comm.php
//  إرسال واتساب + تخزين الرسائل + وضع البوت/اليدوي + الإشعارات
//  (Telegram + Web Push)  — مُضمَّن من lib.php
// ═══════════════════════════════════════════════════════════

// مفاتيح VAPID احتياطية (تُستخدم إن لم تكن في settings.json — مهم بسبب الـ Persistent Volume)
if (!defined('VAPID_PUBLIC'))  define('VAPID_PUBLIC',  'BKMnDG0d8isGefAHm7p3omdqmNBaZECv_QnQl2s_rOjlEg-DPiDLrEZOTSgPKws9kStghS2u368TFEyRQgqJHLs');
if (!defined('VAPID_PRIV_B64')) define('VAPID_PRIV_B64','LS0tLS1CRUdJTiBFQyBQUklWQVRFIEtFWS0tLS0tCk1IY0NBUUVFSU9jZkJRMXdHSVdlVmQ3SXBOYnpEU3JSMUtVd2Nuc213TXVndzZRMG9yYWdvQW9HQ0NxR1NNNDkKQXdFSG9VUURRZ0FFb3ljTWJSM3lLd1o1OEFlYnVuZWlaMnFZMEZwa1FLLzlDZENYYXorczZPVVNENE0rSU11cwpSazVOS0E4ckN6MlJLMkNGTGE3ZnJ4TVVUSkZDQ29rY3V3PT0KLS0tLS1FTkQgRUMgUFJJVkFURSBLRVktLS0tLQo=');
function vapid_public(){ $s=settings_get(); return $s['webpush']['public_key'] ?? VAPID_PUBLIC; }

// ── تخزين الرسائل (data/messages.json) ─────────────────────
function msg_add($phone, $name, $dir, $text, $via='', $media=null) {
    $phone = normalize_phone($phone);
    $all = db_load('messages');
    $rec = [
        'id'    => gen_id('msg'),
        'phone' => $phone,
        'name'  => clean($name),
        'dir'   => $dir,                 // in | out
        'text'  => (string)$text,
        'via'   => $via,                 // bot | human | system
        'ts'    => now_str(),
        'read'  => ($dir === 'out'),     // الصادر يُعتبر مقروء
    ];
    if ($media && !empty($media['file'])) {
        $rec['media_type'] = $media['type'] ?? 'image';   // image | audio | video | document | sticker
        $rec['media_file'] = $media['file'];              // اسم الملف داخل data/media
        $rec['media_mime'] = $media['mime'] ?? '';
    }
    $all[] = $rec;
    db_save('messages', $all);
    return end($all);
}
function msg_thread($phone) {
    $phone = normalize_phone($phone);
    $all = db_load('messages');
    $t = array_values(array_filter($all, fn($m)=>normalize_phone($m['phone']??'')===$phone));
    usort($t, fn($a,$b)=>strcmp($a['ts']??'',$b['ts']??''));
    return $t;
}
// قائمة المحادثات: آخر رسالة + غير المقروء + الوضع + الاسم
function conv_list() {
    $all = db_load('messages');
    $conv = [];
    foreach ($all as $m) {
        $p = normalize_phone($m['phone']??'');
        if (!$p) continue;
        if (!isset($conv[$p])) $conv[$p] = ['phone'=>$p,'name'=>'','last'=>'','last_ts'=>'','unread'=>0];
        if (!empty($m['name'])) $conv[$p]['name'] = $m['name'];
        if (($m['ts']??'') >= $conv[$p]['last_ts']) {
            $conv[$p]['last'] = $m['text']; $conv[$p]['last_ts'] = $m['ts'];
        }
        if (($m['dir']??'')==='in' && empty($m['read'])) $conv[$p]['unread']++;
    }
    // أكمل الاسم من قاعدة العملاء + الوضع
    $customers = db_load('customers');
    foreach ($conv as $p=>&$c) {
        if (empty($c['name'])) {
            foreach ($customers as $cu) if (normalize_phone($cu['phone']??'')===$p && !empty($cu['name'])) { $c['name']=$cu['name']; break; }
        }
        $c['mode'] = conv_get_mode($p);
    }
    unset($c);
    usort($conv, fn($a,$b)=>strcmp($b['last_ts'],$a['last_ts']));
    return $conv;
}
function conv_mark_read($phone) {
    $phone = normalize_phone($phone);
    $all = db_load('messages'); $ch=false;
    foreach ($all as &$m) {
        if (normalize_phone($m['phone']??'')===$phone && ($m['dir']??'')==='in' && empty($m['read'])) { $m['read']=true; $ch=true; }
    }
    unset($m); if ($ch) db_save('messages', $all);
}
function unread_total() {
    $all = db_load('messages'); $n=0;
    foreach ($all as $m) if (($m['dir']??'')==='in' && empty($m['read'])) $n++;
    return $n;
}

// ── وضع المحادثة: bot | human (في data/wa_sessions.json) ───
function conv_get_mode($phone) {
    $phone = normalize_phone($phone);
    foreach (db_load('wa_sessions') as $s) if (($s['phone']??'')===$phone) return $s['mode'] ?? 'bot';
    return 'bot';
}
function conv_set_mode($phone, $mode) {
    $phone = normalize_phone($phone);
    $all = db_load('wa_sessions'); $found=false;
    foreach ($all as &$s) if (($s['phone']??'')===$phone) { $s['mode']=$mode; $found=true; }
    unset($s);
    if (!$found) $all[] = ['phone'=>$phone,'step'=>'menu','data'=>[],'mode'=>$mode,'updated'=>now_str()];
    db_save('wa_sessions', $all);
}

// ── إرسال رسالة واتساب عبر Cloud API ───────────────────────
function wa_send_message($to, $text, $store=true, $via='human') {
    $s  = settings_get();
    $wa = $s['whatsapp'] ?? [];
    if ($store) msg_add($to, '', 'out', $text, $via);
    if (empty($wa['enabled']) || empty($wa['access_token']) || empty($wa['phone_number_id'])) {
        @file_put_contents(DATA_DIR.'/wa_log.txt', now_str()." OUT(disabled) to $to: $text\n", FILE_APPEND);
        return ['disabled'=>true];
    }
    $url = "https://graph.facebook.com/v20.0/{$wa['phone_number_id']}/messages";
    $payload = ['messaging_product'=>'whatsapp','to'=>normalize_phone($to),'type'=>'text','text'=>['body'=>$text]];
    $res = http_post_json($url, $payload, ['Authorization: Bearer '.$wa['access_token']]);
    @file_put_contents(DATA_DIR.'/wa_log.txt', now_str()." OUT to $to: $text | $res\n", FILE_APPEND);
    return ['sent'=>true,'resp'=>$res];
}

// أداة POST JSON عامة
function http_post_json($url, $data, $headers=[], $timeout=15) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS=>json_encode($data, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT=>$timeout,
    ]);
    $r = curl_exec($ch); $e = curl_error($ch); curl_close($ch);
    return $e ? ('ERR: '.$e) : $r;
}

// ═══════════════════════════════════════════════════════════
//  الإشعارات
// ═══════════════════════════════════════════════════════════
function notify_specialist($title, $body, $link='', $event='message') {
    $s = settings_get();
    $nf = $s['notify'] ?? [];
    // احترام مفاتيح التشغيل لكل نوع حدث
    $eventMap = ['message'=>'on_message','order'=>'on_order','booking'=>'on_booking','course'=>'on_course'];
    $key = $eventMap[$event] ?? '';
    if ($key && isset($nf[$key]) && !$nf[$key]) return;

    // سجّل الإشعار (للعرض داخل النظام + يلتقطه الـ Service Worker)
    $list = db_load('notifications');
    $list[] = ['id'=>gen_id('ntf'),'ts'=>now_str(),'title'=>clean($title),'body'=>clean($body),'link'=>$link,'read'=>false];
    if (count($list) > 200) $list = array_slice($list, -200);
    db_save('notifications', $list);

    // Telegram
    $tg = $s['telegram'] ?? [];
    if (!empty($tg['enabled']) && !empty($tg['bot_token']) && !empty($tg['chat_id'])) {
        telegram_send($tg, "🔔 ".$title."\n".$body.($link?"\n".$link:''));
    }
    // Web Push
    $wp = $s['webpush'] ?? [];
    if (!empty($wp['enabled'])) webpush_send_all($wp);
}

function telegram_send($tg, $text) {
    $url = "https://api.telegram.org/bot{$tg['bot_token']}/sendMessage";
    return http_post_json($url, ['chat_id'=>$tg['chat_id'],'text'=>$text,'disable_web_page_preview'=>true]);
}

// ── Web Push (بدون حمولة — wake؛ الـ SW يجلب آخر إشعار) ─────
function webpush_send_all($wp) {
    $subs = db_load('push_subs');
    foreach ($subs as $sub) {
        $endpoint = $sub['endpoint'] ?? '';
        if (!$endpoint) continue;
        $aud = parse_url($endpoint, PHP_URL_SCHEME).'://'.parse_url($endpoint, PHP_URL_HOST);
        $jwt = vapid_jwt($aud, $wp);
        if (!$jwt) continue;
        $headers = [
            'Authorization: vapid t='.$jwt.', k='.($wp['public_key'] ?? VAPID_PUBLIC),
            'TTL: 86400',
            'Content-Length: 0',
            'Urgency: high',
        ];
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_HTTPHEADER=>$headers, CURLOPT_POSTFIELDS=>'', CURLOPT_TIMEOUT=>10,
        ]);
        $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        // 404/410 = اشتراك منتهٍ → احذفه
        if (in_array($code, [404,410])) push_sub_remove($endpoint);
        @file_put_contents(DATA_DIR.'/wa_log.txt', now_str()." PUSH $code -> $endpoint\n", FILE_APPEND);
    }
}
function push_sub_remove($endpoint) {
    $subs = db_load('push_subs');
    $subs = array_values(array_filter($subs, fn($x)=>($x['endpoint']??'')!==$endpoint));
    db_save('push_subs', $subs);
}

// ── توليد VAPID JWT (ES256) ────────────────────────────────
function b64url_encode($d){ return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
function vapid_jwt($aud, $wp) {
    $pem = $wp['private_pem'] ?? '';
    if (!$pem && !empty($wp['private_pem_b64'])) $pem = base64_decode($wp['private_pem_b64']);
    if (!$pem) $pem = base64_decode(VAPID_PRIV_B64);   // احتياطي
    if (!$pem) return null;
    $header  = b64url_encode(json_encode(['typ'=>'JWT','alg'=>'ES256']));
    $payload = b64url_encode(json_encode([
        'aud'=>$aud, 'exp'=>time()+12*3600, 'sub'=>$wp['subject'] ?? 'mailto:info@nailart.sa'
    ]));
    $signingInput = $header.'.'.$payload;
    $key = openssl_pkey_get_private($pem);
    if (!$key) return null;
    $der = '';
    if (!openssl_sign($signingInput, $der, $key, OPENSSL_ALGO_SHA256)) return null;
    // تحويل توقيع DER إلى R||S خام (64 بايت)
    $raw = der_to_raw_sig($der);
    if ($raw === null) return null;
    return $signingInput.'.'.b64url_encode($raw);
}
// DER ECDSA (SEQUENCE{INTEGER r, INTEGER s}) → 64-byte raw
function der_to_raw_sig($der) {
    $o = 0;
    if (ord($der[$o++]) !== 0x30) return null;          // SEQUENCE
    $len = ord($der[$o++]);
    if ($len & 0x80) { $n=$len & 0x7f; $o += $n; }      // long form length (نتجاوزه)
    if (ord($der[$o++]) !== 0x02) return null;          // INTEGER r
    $rlen = ord($der[$o++]); $r = substr($der, $o, $rlen); $o += $rlen;
    if (ord($der[$o++]) !== 0x02) return null;          // INTEGER s
    $slen = ord($der[$o++]); $s = substr($der, $o, $slen);
    $r = ltrim($r, "\x00"); $s = ltrim($s, "\x00");
    $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
    $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);
    return $r.$s;
}

// ═══════════════════════════════════════════════════════════
//  وسائط الواتساب (صور/ملفات) عبر Cloud API
// ═══════════════════════════════════════════════════════════
function media_dir() {
    $d = DATA_DIR . '/media';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    return $d;
}

// رفع ملف وسائط إلى واتساب → يرجّع media id (للإرسال بدون رابط عام)
function wa_upload_media($filepath, $mime) {
    $s = settings_get(); $wa = $s['whatsapp'] ?? [];
    if (empty($wa['enabled']) || empty($wa['access_token']) || empty($wa['phone_number_id'])) return null;
    $url = "https://graph.facebook.com/v20.0/{$wa['phone_number_id']}/media";
    $post = [
        'messaging_product' => 'whatsapp',
        'type'              => $mime,
        'file'             => new CURLFile($filepath, $mime, basename($filepath)),
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$wa['access_token']],
        CURLOPT_POSTFIELDS=>$post, CURLOPT_TIMEOUT=>30,
    ]);
    $res = curl_exec($ch); curl_close($ch);
    $j = json_decode($res, true);
    return $j['id'] ?? null;
}

// إرسال صورة (بالـ media id) مع تعليق اختياري
function wa_send_image($to, $mediaId, $caption='') {
    $s = settings_get(); $wa = $s['whatsapp'] ?? [];
    if (empty($wa['enabled'])) return ['disabled'=>true];
    $url = "https://graph.facebook.com/v20.0/{$wa['phone_number_id']}/messages";
    $img = ['id'=>$mediaId];
    if ($caption !== '') $img['caption'] = $caption;
    $payload = ['messaging_product'=>'whatsapp','to'=>normalize_phone($to),'type'=>'image','image'=>$img];
    $res = http_post_json($url, $payload, ['Authorization: Bearer '.$wa['access_token']]);
    @file_put_contents(DATA_DIR.'/wa_log.txt', now_str()." OUT-IMG to $to | $res\n", FILE_APPEND);
    return ['sent'=>true,'resp'=>$res];
}

// تنزيل وسائط واردة → يحفظها في data/media ويرجّع [file, mime, type]
function wa_download_media($mediaId, $typeHint='image') {
    $s = settings_get(); $wa = $s['whatsapp'] ?? [];
    if (empty($wa['access_token'])) return null;
    // 1) جلب رابط الوسائط
    $info = curl_get("https://graph.facebook.com/v20.0/{$mediaId}", ['Authorization: Bearer '.$wa['access_token']]);
    $j = json_decode($info, true);
    $murl = $j['url'] ?? ''; $mime = $j['mime_type'] ?? '';
    if (!$murl) return null;
    // 2) تنزيل البايتات (يتطلب التوكن)
    $bytes = curl_get($murl, ['Authorization: Bearer '.$wa['access_token']]);
    if ($bytes === false || $bytes === '') return null;
    $ext = mime_ext($mime);
    $fname = gen_id('wam') . '.' . $ext;
    file_put_contents(media_dir().'/'.$fname, $bytes);
    return ['file'=>$fname, 'mime'=>$mime, 'type'=>$typeHint];
}

function curl_get($url, $headers=[], $timeout=30) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>$headers, CURLOPT_TIMEOUT=>$timeout, CURLOPT_FOLLOWLOCATION=>true]);
    $r = curl_exec($ch); curl_close($ch); return $r;
}
function mime_ext($mime) {
    $map = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif',
            'audio/ogg'=>'ogg','audio/mpeg'=>'mp3','audio/mp4'=>'m4a','audio/amr'=>'amr',
            'video/mp4'=>'mp4','application/pdf'=>'pdf'];
    return $map[$mime] ?? 'bin';
}
