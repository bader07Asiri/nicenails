<?php
// ═══════════════════════════════════════════════════════════
//  Nice Nail — api.php  |  نقاط JSON (إشعارات/اشتراك Push/عدّادات)
// ═══════════════════════════════════════════════════════════
session_start();
require_once __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');

$loggedIn = !empty($_SESSION['admin_logged_in']);
$do = $_REQUEST['do'] ?? '';
$body = json_decode(file_get_contents('php://input'), true) ?: [];

function out($d){ echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

switch ($do) {

case 'vapid_key':
    out(['key' => vapid_public()]);

case 'push_subscribe':
    if (!$loggedIn) out(['error'=>'auth']);
    $sub = $body['subscription'] ?? $body;
    $endpoint = $sub['endpoint'] ?? '';
    if (!$endpoint) out(['error'=>'no endpoint']);
    $subs = db_load('push_subs');
    foreach ($subs as $x) if (($x['endpoint']??'')===$endpoint) out(['ok'=>true,'dup'=>true]);
    $subs[] = [
        'endpoint' => $endpoint,
        'keys'     => $sub['keys'] ?? [],
        'added'    => now_str(),
    ];
    db_save('push_subs', $subs);
    out(['ok'=>true]);

case 'push_unsubscribe':
    if (!$loggedIn) out(['error'=>'auth']);
    push_sub_remove($body['endpoint'] ?? ($_POST['endpoint'] ?? ''));
    out(['ok'=>true]);

case 'last_notif':
    // آخر إشعار غير مقروء (يستدعيها الـ Service Worker لعرض الإشعار)
    $list = db_load('notifications');
    $last = null;
    for ($i=count($list)-1; $i>=0; $i--) { if (empty($list[$i]['read'])) { $last=$list[$i]; break; } }
    out(['notif'=>$last]);

case 'notif_read':
    if (!$loggedIn) out(['error'=>'auth']);
    $list = db_load('notifications');
    foreach ($list as &$n) $n['read']=true;
    unset($n); db_save('notifications',$list);
    out(['ok'=>true]);

case 'unread':
    if (!$loggedIn) out(['error'=>'auth']);
    out(['messages'=>unread_total(), 'notifs'=>count(array_filter(db_load('notifications'), fn($n)=>empty($n['read'])))]);

default:
    out(['error'=>'unknown']);
}
