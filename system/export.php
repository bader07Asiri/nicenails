<?php
// تصدير كشف مالي / طلبات بصيغة CSV (يفتح في Excel)
session_start();
require_once __DIR__ . '/lib.php';
if (empty($_SESSION['admin_logged_in'])) { header('Location: index.php'); exit; }

$type = $_GET['type'] ?? 'payments';
$tmap=['deposit'=>'عربون','remaining'=>'باقي','full'=>'دفعة كاملة','course'=>'دورة'];
$stm=['pending'=>'بانتظار الدفع','confirmed'=>'عربون مدفوع','completed'=>'مكتمل','cancelled'=>'ملغي'];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="nicenail_'.$type.'_'.date('Ymd_His').'.csv"');
echo "\xEF\xBB\xBF"; // BOM لدعم العربية في Excel
$out=fopen('php://output','w');

if ($type==='orders') {
    fputcsv($out, ['التاريخ','العميلة','الجوال','الخدمة','التصميم','الإجمالي','المدفوع','المتبقي','الموعد','الحالة']);
    foreach (array_reverse(db_load('orders')) as $o) {
        $rem=max(0,(float)$o['total']-(float)($o['paid']??0));
        fputcsv($out, [
            substr($o['date'],0,16), $o['customer_name'], "\t".$o['phone'], $o['service_name'],
            $o['design_details']??'', $o['total'], $o['paid']??0, $rem,
            ($o['appt_date']??'').' '.($o['appt_time']??''), $stm[$o['status']]??$o['status'],
        ]);
    }
} else {
    fputcsv($out, ['التاريخ','العميلة','الجوال','الخدمة','نوع الدفعة','المبلغ (ريال)']);
    $total=0;
    foreach (array_reverse(db_load('payments')) as $p) {
        $total+=(float)$p['amount'];
        fputcsv($out, [substr($p['date'],0,16), $p['customer_name'], "\t".$p['phone'], $p['service_name'], $tmap[$p['type']]??$p['type'], $p['amount']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['','','','','الإجمالي', $total]);
}
fclose($out);
