<?php
// ═══════════════════════════════════════════════════════════
//  Nice Nail — whatsapp.php  |  Webhook لـ WhatsApp Cloud API
//  رابط الـ Webhook في Meta:  https://نطاقك/system/whatsapp.php
// ═══════════════════════════════════════════════════════════
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/bot.php';

$settings = settings_get();
$wa = $settings['whatsapp'] ?? [];

// ── 1) التحقق من الـ Webhook (GET) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode      = $_GET['hub_mode']         ?? '';
    $token     = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge']    ?? '';
    if ($mode === 'subscribe' && hash_equals((string)($wa['verify_token'] ?? ''), (string)$token)) {
        echo $challenge; exit;
    }
    http_response_code(403); echo 'Verification failed'; exit;
}

// ── 2) استقبال الرسائل (POST) ───────────────────────────────
$raw = file_get_contents('php://input');
@file_put_contents(__DIR__.'/data/wa_log.txt', date('Y-m-d H:i:s')." IN: ".$raw."\n", FILE_APPEND);
$update = json_decode($raw, true);

http_response_code(200); // ردّ سريع لـ Meta
if (!$update) exit;

try {
    foreach (($update['entry'] ?? []) as $entry) {
        foreach (($entry['changes'] ?? []) as $change) {
            $value = $change['value'] ?? [];
            $contacts = $value['contacts'] ?? [];
            $profileName = $contacts[0]['profile']['name'] ?? '';
            foreach (($value['messages'] ?? []) as $msg) {
                $type = $msg['type'] ?? '';
                $from = normalize_phone($msg['from'] ?? '');
                if (!$from) continue;
                $cust = customer_upsert($from, $profileName, '');
                $name = $profileName ?: ($cust['name'] ?? '');
                $link = rtrim($settings['site_url']??'','/').'/system/index.php?page=inbox&phone='.$from;

                // تخزين الرسالة الواردة حسب نوعها
                if ($type === 'text') {
                    $body = $msg['text']['body'] ?? '';
                    msg_add($from, $name, 'in', $body, 'customer');
                } elseif (in_array($type, ['image','audio','video','document','sticker'])) {
                    $mObj = $msg[$type] ?? [];
                    $caption = $mObj['caption'] ?? '';
                    $mid = $mObj['id'] ?? '';
                    $dl = $mid ? wa_download_media($mid, $type) : null;
                    msg_add($from, $name, 'in', $caption, 'customer', $dl);
                    $body = $caption ?: ('['.$type.']');
                } else {
                    $body = '['.$type.']';
                    msg_add($from, $name, 'in', $body, 'customer');
                }

                // إشعار للأخصائية بكل رسالة واردة
                notify_specialist('رسالة واتساب جديدة', ($name?:$from).': '.mb_substr($body,0,80), $link, 'message');

                // الوضع اليدوي → لا يرد البوت
                if (conv_get_mode($from) === 'human') continue;
                // البوت يرد على النص فقط
                if ($type !== 'text') continue;

                $reply = bot_reply($from, $body, $settings);
                if (!empty($GLOBALS['bot_handoff'])) {
                    conv_set_mode($from, 'human');
                    notify_specialist('عميلة تطلب التواصل', ($name?:$from).' تنتظر ردّك', $link, 'message');
                }
                if ($reply !== null && $reply !== '') {
                    wa_send_message($from, $reply, true, 'bot');
                }
            }
        }
    }
} catch (Throwable $e) {
    @file_put_contents(__DIR__.'/data/wa_log.txt', date('Y-m-d H:i:s')." ERR: ".$e->getMessage()."\n", FILE_APPEND);
}
exit;
