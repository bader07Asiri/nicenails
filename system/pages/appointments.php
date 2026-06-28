<?php
$appts = db_load('appointments');
$services = db_load('services');

// شهر معروض
$ym = $_GET['m'] ?? date('Y-m');
[$Y,$M] = array_map('intval', explode('-',$ym));
$first = mktime(0,0,0,$M,1,$Y);
$daysIn = (int)date('t',$first);
$startDow = (int)date('w',$first); // 0=Sun
$prevM = date('Y-m', strtotime("$ym-01 -1 month"));
$nextM = date('Y-m', strtotime("$ym-01 +1 month"));
$arMonths=['','يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];

// مواعيد حسب اليوم
$byDay=[];
foreach($appts as $a){ $byDay[$a['date']??''][]=$a; }

$dows=['الأحد','الإثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'];
$stm=['pending'=>'معلق','confirmed'=>'مؤكد','completed'=>'مكتمل','cancelled'=>'ملغي'];
?>
<div class="toolbar">
  <button class="btn btn-primary" onclick="openModal('mAppt')">+ موعد جديد</button>
  <div style="flex:1"></div>
  <div class="cal-head" style="margin:0;gap:.6rem">
    <a class="btn btn-ghost btn-sm" href="?page=appointments&m=<?= $prevM ?>">‹ السابق</a>
    <b class="serif" style="font-size:1.1rem;min-width:130px;text-align:center"><?= $arMonths[$M].' '.$Y ?></b>
    <a class="btn btn-ghost btn-sm" href="?page=appointments&m=<?= $nextM ?>">التالي ›</a>
    <a class="btn btn-ghost btn-sm" href="?page=appointments">اليوم</a>
  </div>
</div>

<div class="card">
  <div class="cal-grid" style="margin-bottom:7px">
    <?php foreach($dows as $d): ?><div class="cal-dow"><?= $d ?></div><?php endforeach; ?>
  </div>
  <div class="cal-grid">
    <?php
    // خلايا فارغة قبل بداية الشهر
    for($i=0;$i<$startDow;$i++) echo '<div class="cal-cell other"></div>';
    for($d=1;$d<=$daysIn;$d++):
      $dateStr=sprintf('%04d-%02d-%02d',$Y,$M,$d);
      $isToday=($dateStr===today());
      $evs=$byDay[$dateStr]??[];
      usort($evs, fn($a,$b)=>strcmp($a['time']??'',$b['time']??''));
    ?>
      <div class="cal-cell <?= $isToday?'today':'' ?>" onclick="newApptOn('<?= $dateStr ?>')">
        <div class="dnum"><?= $d ?></div>
        <?php foreach(array_slice($evs,0,3) as $e): if(($e['status']??'')==='cancelled') continue; ?>
          <div class="cal-ev <?= h($e['status']) ?>" title="<?= h($e['time'].' '.$e['customer_name'].' — '.$e['service_name']) ?>"><?= h(($e['time']?$e['time'].' ':'').$e['customer_name']) ?></div>
        <?php endforeach; ?>
        <?php $vis=count(array_filter($evs,fn($e)=>($e['status']??'')!=='cancelled')); if($vis>3): ?><div class="muted" style="font-size:.62rem">+<?= $vis-3 ?> المزيد</div><?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>
</div>

<!-- قائمة مواعيد الشهر -->
<div class="card" style="margin-top:1.4rem">
  <div class="card-h"><h3>مواعيد <?= $arMonths[$M].' '.$Y ?></h3></div>
  <?php
  $monthAppts=array_values(array_filter($appts, fn($a)=>strpos($a['date']??'',$ym)===0));
  usort($monthAppts, fn($a,$b)=>strcmp($a['date'].$a['time'],$b['date'].$b['time']));
  if(!$monthAppts): ?>
    <div class="empty"><div class="ei">🗓️</div>لا توجد مواعيد هذا الشهر</div>
  <?php else: ?>
    <div class="tbl-wrap"><table><thead><tr><th>التاريخ</th><th>الوقت</th><th>العميلة</th><th>الخدمة</th><th>الحالة</th><th></th></tr></thead><tbody>
    <?php foreach($monthAppts as $a): ?>
      <tr>
        <td><?= ar_date($a['date']) ?></td><td><?= h($a['time']?:'—') ?></td>
        <td><b><?= h($a['customer_name']?:'—') ?></b><?php if($a['phone']): ?><div class="muted"><?= h($a['phone']) ?></div><?php endif; ?></td>
        <td><?= h($a['service_name']) ?></td>
        <td><span class="tag t-<?= h($a['status']) ?>"><?= $stm[$a['status']]??$a['status'] ?></span></td>
        <td><div class="t-actions">
          <?php if(($a['status']??'')!=='confirmed'): ?><a class="btn btn-sm btn-success" href="actions.php?do=appt_status&id=<?= $a['id'] ?>&status=confirmed&return=<?= urlencode("index.php?page=appointments&m=$ym") ?>">تأكيد</a><?php endif; ?>
          <?php if(($a['status']??'')!=='completed'): ?><a class="btn btn-sm btn-ghost" href="actions.php?do=appt_status&id=<?= $a['id'] ?>&status=completed&return=<?= urlencode("index.php?page=appointments&m=$ym") ?>">إتمام</a><?php endif; ?>
          <?php if($a['phone']): ?><a class="btn btn-sm btn-wa" target="_blank" href="<?= h(wa_link($a['phone'])) ?>">واتساب</a><?php endif; ?>
          <a class="btn btn-sm btn-danger" href="actions.php?do=appt_delete&id=<?= $a['id'] ?>&return=<?= urlencode("index.php?page=appointments&m=$ym") ?>" onclick="return confirmDel()">حذف</a>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>

<!-- مودال موعد جديد -->
<div class="modal-bg" id="mAppt"><div class="modal"><form method="post" action="actions.php">
  <input type="hidden" name="do" value="appt_create">
  <input type="hidden" name="return" value="index.php?page=appointments&m=<?= h($ym) ?>">
  <div class="modal-h"><h3>موعد جديد</h3><button type="button" class="modal-x" onclick="closeModal('mAppt')">✕</button></div>
  <div class="modal-b">
    <div class="frow">
      <div class="field"><label>التاريخ <span>*</span></label><input type="date" name="date" id="appt_date" value="<?= today() ?>" required></div>
      <div class="field"><label>الوقت</label><input type="time" name="time"></div>
    </div>
    <div class="frow">
      <div class="field"><label>اسم العميلة</label><input type="text" name="customer_name" placeholder="الاسم"></div>
      <div class="field"><label>رقم الجوال</label><input type="text" name="phone" placeholder="05xxxxxxxx" dir="ltr"></div>
    </div>
    <div class="field"><label>الخدمة</label>
      <select name="service_name"><option value="">— اختياري —</option>
        <?php foreach($services as $s): ?><option value="<?= h($s['name']) ?>"><?= h($s['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>الحالة</label>
      <select name="status"><option value="pending">معلق</option><option value="confirmed">مؤكد</option></select>
    </div>
    <div class="field"><label>ملاحظات</label><textarea name="notes" placeholder="تفاصيل إضافية"></textarea></div>
  </div>
  <div class="modal-f"><button class="btn btn-primary" type="submit">حفظ الموعد</button><button class="btn btn-ghost" type="button" onclick="closeModal('mAppt')">إلغاء</button></div>
</form></div></div>

<script>
function newApptOn(d){document.getElementById('appt_date').value=d;openModal('mAppt');}
</script>
