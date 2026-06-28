<?php
$orders   = db_load('orders');
$payments = db_load('payments');
$appts    = db_load('appointments');
$customers= db_load('customers');

$sumRange = function($from) use ($payments){
    $t=0; foreach($payments as $p){ if(($p['date']??'')>=$from) $t+=(float)($p['amount']??0); } return $t;
};
$today=today();
$revToday = $sumRange($today.' 00:00:00');
$revMonth = $sumRange(date('Y-m-01').' 00:00:00');
$revYear  = $sumRange(date('Y-01-01').' 00:00:00');
$revWeek  = $sumRange(date('Y-m-d',strtotime('-6 days')).' 00:00:00');

$completed = array_filter($orders, fn($o)=>($o['status']??'')==='completed');
$pendingPay= array_filter($orders, fn($o)=>in_array($o['status']??'',['pending','confirmed']));
$avgOrder  = count($completed) ? array_sum(array_map(fn($o)=>(float)$o['total'],$completed))/count($completed) : 0;

// مواعيد اليوم
$todayList = array_values(array_filter($appts, fn($a)=>($a['date']??'')===$today && ($a['status']??'')!=='cancelled'));
usort($todayList, fn($a,$b)=>strcmp($a['time']??'',$b['time']??''));

// إيرادات آخر 7 أيام للرسم
$week=[]; $maxw=1;
for($i=6;$i>=0;$i--){
    $d=date('Y-m-d',strtotime("-$i days")); $s=0;
    foreach($payments as $p){ if(substr($p['date']??'',0,10)===$d) $s+=(float)$p['amount']; }
    $dows=['Sun'=>'أحد','Mon'=>'إثن','Tue'=>'ثلا','Wed'=>'أرب','Thu'=>'خمي','Fri'=>'جمع','Sat'=>'سبت'];
    $week[]=['lbl'=>$dows[date('D',strtotime($d))],'v'=>$s]; $maxw=max($maxw,$s);
}
?>
<div class="grid g4" style="margin-bottom:1.4rem">
  <div class="stat rose"><div class="si">💰</div><div class="sv"><?= money($revToday) ?></div><div class="sl">إيرادات اليوم (ريال)</div></div>
  <div class="stat green"><div class="si">📅</div><div class="sv"><?= money($revMonth) ?></div><div class="sl">إيرادات الشهر (ريال)</div></div>
  <div class="stat amber"><div class="si">✅</div><div class="sv"><?= count($completed) ?></div><div class="sl">جلسات مكتملة</div></div>
  <div class="stat blue"><div class="si">⏳</div><div class="sv"><?= count($pendingPay) ?></div><div class="sl">طلبات معلّقة</div></div>
</div>

<div class="grid g2" style="grid-template-columns:1.4fr 1fr;align-items:start">
  <div class="card">
    <div class="card-h"><h3>إيرادات آخر 7 أيام</h3><span class="muted"><?= money($revWeek) ?> ريال</span></div>
    <div class="bars">
      <?php foreach($week as $w): $hp=$maxw>0?max(4,round($w['v']/$maxw*150)):4; ?>
        <div class="bar-col">
          <div class="bar-val"><?= $w['v']?money($w['v']):'' ?></div>
          <div class="bar" style="height:<?= $hp ?>px"></div>
          <div class="bar-lbl"><?= $w['lbl'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="divider"></div>
    <div class="grid g3" style="gap:.7rem">
      <div><div class="muted">متوسط قيمة الطلب</div><div class="serif" style="font-size:1.3rem;color:var(--rose-deep)"><?= money($avgOrder) ?> ﷼</div></div>
      <div><div class="muted">إيرادات السنة</div><div class="serif" style="font-size:1.3rem;color:var(--rose-deep)"><?= money($revYear) ?> ﷼</div></div>
      <div><div class="muted">عملاء مسجّلون</div><div class="serif" style="font-size:1.3rem;color:var(--rose-deep)"><?= count($customers) ?></div></div>
    </div>
  </div>

  <div class="card">
    <div class="card-h"><h3>مواعيد اليوم</h3><a class="btn btn-ghost btn-sm" href="index.php?page=appointments">عرض الكل</a></div>
    <?php if(!$todayList): ?>
      <div class="empty"><div class="ei">🗓️</div>لا توجد مواعيد اليوم</div>
    <?php else: foreach($todayList as $a): ?>
      <div style="display:flex;align-items:center;gap:.7rem;padding:10px 0;border-bottom:1px solid var(--line)">
        <div class="serif" style="font-size:1rem;color:var(--rose);min-width:54px"><?= h($a['time']?:'—') ?></div>
        <div style="flex:1"><b><?= h($a['customer_name']?:'بدون اسم') ?></b><div class="muted"><?= h($a['service_name']) ?></div></div>
        <span class="tag t-<?= h($a['status']) ?>"><?= ['pending'=>'معلق','confirmed'=>'مؤكد','completed'=>'مكتمل'][$a['status']]??$a['status'] ?></span>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<div class="card" style="margin-top:1.4rem">
  <div class="card-h"><h3>أحدث الطلبات</h3><a class="btn btn-ghost btn-sm" href="index.php?page=orders">كل الطلبات</a></div>
  <?php $recent=array_slice(array_reverse($orders),0,5); if(!$recent): ?>
    <div class="empty"><div class="ei">💳</div>لا توجد طلبات بعد</div>
  <?php else: ?>
    <div class="tbl-wrap"><table><thead><tr><th>العميلة</th><th>الخدمة</th><th>المبلغ</th><th>المدفوع</th><th>الحالة</th></tr></thead><tbody>
    <?php foreach($recent as $o): $st=$o['status']; $stm=['pending'=>'بانتظار الدفع','confirmed'=>'عربون مدفوع','completed'=>'مكتمل','cancelled'=>'ملغي']; ?>
      <tr><td><b><?= h($o['customer_name']) ?></b></td><td><?= h($o['service_name']) ?></td>
      <td><?= money($o['total']) ?> ﷼</td><td><?= money($o['paid']??0) ?> ﷼</td>
      <td><span class="tag t-<?= $st ?>"><?= $stm[$st]??$st ?></span></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
