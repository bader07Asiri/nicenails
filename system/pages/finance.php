<?php
$payments = db_load('payments');
$orders   = db_load('orders');
$today=today();

$sumFrom=fn($from)=>array_sum(array_map(fn($p)=>($p['date']>=$from)?(float)$p['amount']:0,$payments));
$revToday=$sumFrom($today.' 00:00:00');
$revWeek =$sumFrom(date('Y-m-d',strtotime('-6 days')).' 00:00:00');
$revMonth=$sumFrom(date('Y-m-01').' 00:00:00');
$revYear =$sumFrom(date('Y-01-01').' 00:00:00');
$revLastMonth=array_sum(array_map(function($p){
  $d=substr($p['date'],0,7); return $d===date('Y-m',strtotime('first day of last month'))?(float)$p['amount']:0;
},$payments));

$completed=array_filter($orders,fn($o)=>($o['status']??'')==='completed');
$avg=count($completed)?array_sum(array_map(fn($o)=>(float)$o['total'],$completed))/count($completed):0;
$pendingPay=array_filter($orders,fn($o)=>in_array($o['status']??'',['pending','confirmed']));
$pendingAmt=array_sum(array_map(fn($o)=>max(0,(float)$o['total']-(float)($o['paid']??0)),$pendingPay));

// أكثر الخدمات طلباً
$svcCount=[]; $svcRev=[];
foreach($payments as $p){ $n=$p['service_name']?:'غير محدد'; $svcCount[$n]=($svcCount[$n]??0)+1; $svcRev[$n]=($svcRev[$n]??0)+(float)$p['amount']; }
arsort($svcRev);

// أكثر العملاء
$custRev=[];
foreach($payments as $p){ $n=$p['customer_name']?:'—'; $custRev[$n]=($custRev[$n]??0)+(float)$p['amount']; }
arsort($custRev);

// أيام الذروة (عدد الحجوزات حسب اليوم من الأسبوع)
$peak=[0,0,0,0,0,0,0]; $dn=['الأحد','الإثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'];
foreach($orders as $o){ if(!empty($o['appt_date'])) $peak[(int)date('w',strtotime($o['appt_date']))]++; }
$peakMax=max(1,max($peak));

// رسم أسبوعي (آخر 8 أسابيع)
$weeks=[]; $wmax=1;
for($i=7;$i>=0;$i--){
  $start=date('Y-m-d',strtotime("monday this week -$i week"));
  $end=date('Y-m-d',strtotime("$start +6 days"));
  $sum=array_sum(array_map(fn($p)=>(substr($p['date'],0,10)>=$start&&substr($p['date'],0,10)<=$end)?(float)$p['amount']:0,$payments));
  $weeks[]=['lbl'=>date('m/d',strtotime($start)),'v'=>$sum]; $wmax=max($wmax,$sum);
}
$diff=$revMonth-$revLastMonth; $diffPct=$revLastMonth>0?round($diff/$revLastMonth*100):($revMonth>0?100:0);
?>
<div class="toolbar">
  <a class="btn btn-primary" href="export.php?type=payments">⬇️ تصدير كشف مالي (Excel)</a>
  <a class="btn btn-ghost" href="export.php?type=orders">⬇️ تصدير الطلبات</a>
</div>

<div class="grid g4" style="margin-bottom:1.4rem">
  <div class="stat rose"><div class="si">📅</div><div class="sv"><?= money($revToday) ?></div><div class="sl">اليوم (ريال)</div></div>
  <div class="stat green"><div class="si">📆</div><div class="sv"><?= money($revWeek) ?></div><div class="sl">آخر 7 أيام</div></div>
  <div class="stat amber"><div class="si">🗓️</div><div class="sv"><?= money($revMonth) ?></div><div class="sl">هذا الشهر</div></div>
  <div class="stat blue"><div class="si">📊</div><div class="sv"><?= money($revYear) ?></div><div class="sl">هذه السنة</div></div>
</div>

<div class="grid g4" style="margin-bottom:1.4rem">
  <div class="card" style="padding:1.1rem 1.3rem"><div class="muted">متوسط قيمة الطلب</div><div class="serif" style="font-size:1.5rem;color:var(--rose-deep)"><?= money($avg) ?> ﷼</div></div>
  <div class="card" style="padding:1.1rem 1.3rem"><div class="muted">جلسات مكتملة</div><div class="serif" style="font-size:1.5rem;color:var(--rose-deep)"><?= count($completed) ?></div></div>
  <div class="card" style="padding:1.1rem 1.3rem"><div class="muted">مبالغ معلّقة (لم تُدفع)</div><div class="serif" style="font-size:1.5rem;color:var(--amber)"><?= money($pendingAmt) ?> ﷼</div></div>
  <div class="card" style="padding:1.1rem 1.3rem"><div class="muted">مقارنة بالشهر الماضي</div><div class="serif" style="font-size:1.5rem;color:<?= $diff>=0?'var(--green)':'var(--red)' ?>"><?= ($diff>=0?'▲ +':'▼ ').$diffPct ?>%</div></div>
</div>

<div class="card" style="margin-bottom:1.4rem">
  <div class="card-h"><h3>الإيرادات الأسبوعية (آخر 8 أسابيع)</h3></div>
  <div class="bars">
    <?php foreach($weeks as $w): $hp=max(4,round($w['v']/$wmax*150)); ?>
      <div class="bar-col"><div class="bar-val"><?= $w['v']?money($w['v']):'' ?></div><div class="bar" style="height:<?= $hp ?>px"></div><div class="bar-lbl"><?= $w['lbl'] ?></div></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="grid g2" style="align-items:start;margin-bottom:1.4rem">
  <div class="card">
    <div class="card-h"><h3>أكثر الخدمات طلباً</h3></div>
    <?php if(!$svcRev): ?><div class="empty">لا توجد بيانات</div><?php else:
      $smax=max($svcRev); foreach(array_slice($svcRev,0,6,true) as $name=>$rev): ?>
      <div style="margin-bottom:.8rem">
        <div style="display:flex;justify-content:space-between;font-size:.85rem;margin-bottom:4px"><b><?= h($name) ?></b><span class="muted"><?= $svcCount[$name] ?> مرة · <?= money($rev) ?> ﷼</span></div>
        <div style="background:var(--rose-pale);border-radius:30px;height:9px;overflow:hidden"><div style="background:var(--rose);height:100%;width:<?= round($rev/$smax*100) ?>%;border-radius:30px"></div></div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="card">
    <div class="card-h"><h3>أكثر العملاء إنفاقاً</h3></div>
    <?php if(!$custRev): ?><div class="empty">لا توجد بيانات</div><?php else: ?>
      <div class="tbl-wrap" style="border:none;box-shadow:none"><table><tbody>
      <?php $rank=1; foreach(array_slice($custRev,0,6,true) as $name=>$rev): ?>
        <tr><td style="width:30px"><b style="color:var(--rose)"><?= $rank++ ?></b></td><td><b><?= h($name) ?></b></td><td style="text-align:left"><?= money($rev) ?> ﷼</td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-bottom:1.4rem">
  <div class="card-h"><h3>أيام الذروة (حسب الحجوزات)</h3></div>
  <div class="grid" style="grid-template-columns:repeat(7,1fr);gap:.6rem">
    <?php foreach($dn as $i=>$d): ?>
      <div style="text-align:center">
        <div style="background:var(--cream);border-radius:10px;height:90px;display:flex;align-items:flex-end;padding:5px;border:1px solid var(--line)">
          <div style="background:linear-gradient(180deg,var(--slate-light),var(--slate));width:100%;border-radius:7px;height:<?= max(5,round($peak[$i]/$peakMax*78)) ?>px"></div>
        </div>
        <div class="muted" style="font-size:.72rem;margin-top:4px"><?= $d ?></div>
        <b style="font-size:.85rem;color:var(--rose-deep)"><?= $peak[$i] ?></b>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <div class="card-h"><h3>سجل المدفوعات</h3><span class="muted"><?= count($payments) ?> عملية</span></div>
  <?php if(!$payments): ?><div class="empty"><div class="ei">📈</div>لا توجد مدفوعات مسجّلة بعد</div>
  <?php else:
    $tmap=['deposit'=>'عربون','remaining'=>'باقي','full'=>'كامل','course'=>'دورة'];
  ?>
    <div class="tbl-wrap"><table><thead><tr><th>التاريخ</th><th>العميلة</th><th>الخدمة</th><th>النوع</th><th>المبلغ</th></tr></thead><tbody>
    <?php foreach(array_slice(array_reverse($payments),0,30) as $p): ?>
      <tr><td><?= h(substr($p['date'],0,16)) ?></td><td><b><?= h($p['customer_name']) ?></b></td><td><?= h($p['service_name']) ?></td>
      <td><span class="tag t-confirmed"><?= $tmap[$p['type']]??$p['type'] ?></span></td><td><b><?= money($p['amount']) ?> ﷼</b></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
