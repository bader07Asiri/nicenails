<?php
$customers = array_reverse(db_load('customers'));
$orders = db_load('orders');
$broadcasts = array_reverse(db_load('broadcasts'));
$filter = $_GET['filter'] ?? 'all';
$stm=['visitor'=>'زائر','client'=>'عميلة','trainee'=>'متدربة'];

$counts=['all'=>count($customers),'visitor'=>0,'client'=>0,'trainee'=>0];
foreach($customers as $c){ $counts[$c['status']??'visitor']=($counts[$c['status']??'visitor']??0)+1; }
$list=$filter==='all'?$customers:array_values(array_filter($customers, fn($c)=>($c['status']??'')===$filter));

// عدد طلبات كل عميلة
$spend=[];
foreach($orders as $o){ $cid=$o['customer_id']??''; if($cid){ $spend[$cid]=($spend[$cid]??0)+(float)($o['paid']??0);} }
?>
<div class="grid g4" style="margin-bottom:1.3rem">
  <div class="stat rose"><div class="si">👩</div><div class="sv"><?= $counts['all'] ?></div><div class="sl">إجمالي العملاء</div></div>
  <div class="stat blue"><div class="si">👀</div><div class="sv"><?= $counts['visitor'] ?></div><div class="sl">زوار (لم يحجزوا)</div></div>
  <div class="stat green"><div class="si">💖</div><div class="sv"><?= $counts['client'] ?></div><div class="sl">عميلات فعليات</div></div>
  <div class="stat amber"><div class="si">🎓</div><div class="sv"><?= $counts['trainee'] ?></div><div class="sl">متدربات</div></div>
</div>

<div class="toolbar">
  <button class="btn btn-primary" onclick="openModal('mCust')">+ عميلة جديدة</button>
  <button class="btn btn-ghost" onclick="openModal('mBroadcast')">📣 رسالة جماعية</button>
  <div class="search"><input type="text" placeholder="🔍 بحث بالاسم أو الجوال..." oninput="liveSearch(this,'custTable')"></div>
  <div class="chips">
    <?php foreach(['all'=>'الكل','client'=>'عميلات','visitor'=>'زوار','trainee'=>'متدربات'] as $k=>$lbl): ?>
      <a class="chip <?= $filter===$k?'active':'' ?>" href="?page=customers&filter=<?= $k ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if(!$list): ?>
  <div class="card"><div class="empty"><div class="ei">👩</div>لا يوجد عملاء بعد — تُضاف تلقائياً عند أول تواصل واتساب</div></div>
<?php else: ?>
<div class="tbl-wrap"><table id="custTable"><thead><tr>
  <th>الاسم</th><th>الجوال</th><th>الحالة</th><th>الزيارات</th><th>النقاط</th><th>إجمالي الإنفاق</th><th>ملاحظات</th><th></th>
</tr></thead><tbody>
<?php foreach($list as $c): $cid=$c['id']; ?>
  <tr>
    <td><b><?= h($c['name']?:'بدون اسم') ?></b><div class="muted" style="font-size:.72rem">منذ <?= h(substr($c['first_contact']??'',0,10)) ?></div></td>
    <td dir="ltr" style="text-align:right"><?= h($c['phone']) ?></td>
    <td><span class="tag t-<?= h($c['status']??'visitor') ?>"><?= $stm[$c['status']??'visitor']??$c['status'] ?></span></td>
    <td><?= (int)($c['visits']??0) ?></td>
    <td><?php $pts=(int)($c['points']??0); ?><span title="كل 5 جلسات = مكافأة"><?= $pts ?> <?= $pts>=5?'🎁':'' ?></span></td>
    <td><?= money($spend[$cid]??0) ?> ﷼</td>
    <td class="muted" style="max-width:160px"><?= h($c['notes']?:'—') ?></td>
    <td><div class="t-actions">
      <a class="btn btn-sm btn-wa" target="_blank" href="<?= h(wa_link($c['phone'])) ?>">واتساب</a>
      <button class="btn btn-sm btn-ghost" onclick='editCust(<?= json_encode($c,JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>تعديل</button>
      <a class="btn btn-sm btn-danger" href="actions.php?do=customer_delete&id=<?= $cid ?>&return=<?= urlencode("index.php?page=customers&filter=$filter") ?>" onclick="return confirmDel()">حذف</a>
    </div></td>
  </tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>

<?php if($broadcasts): ?>
<div class="card" style="margin-top:1.4rem">
  <div class="card-h"><h3>سجل الرسائل الجماعية</h3></div>
  <div class="tbl-wrap"><table><thead><tr><th>التاريخ</th><th>الشريحة</th><th>العدد</th><th>الرسالة</th></tr></thead><tbody>
  <?php $segm=['all'=>'الكل','visitors'=>'الزوار','clients'=>'العميلات','trainees'=>'المتدربات']; foreach(array_slice($broadcasts,0,8) as $b): ?>
    <tr><td><?= h(substr($b['date'],0,16)) ?></td><td><?= $segm[$b['segment']]??$b['segment'] ?></td><td><?= (int)$b['count'] ?></td><td class="muted"><?= h(mb_substr($b['message'],0,70)) ?></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>

<!-- مودال: عميلة جديدة -->
<div class="modal-bg" id="mCust"><div class="modal" style="max-width:460px"><form method="post" action="actions.php">
  <input type="hidden" name="do" value="customer_create">
  <input type="hidden" name="return" value="index.php?page=customers&filter=<?= h($filter) ?>">
  <div class="modal-h"><h3>عميلة جديدة</h3><button type="button" class="modal-x" onclick="closeModal('mCust')">✕</button></div>
  <div class="modal-b">
    <div class="field"><label>الاسم</label><input type="text" name="name"></div>
    <div class="field"><label>رقم الجوال <span>*</span></label><input type="text" name="phone" dir="ltr" placeholder="05xxxxxxxx" required></div>
    <div class="field"><label>الحالة</label><select name="status"><option value="client">عميلة</option><option value="visitor">زائر</option><option value="trainee">متدربة</option></select></div>
    <div class="field"><label>ملاحظات (حساسية، تفضيلات...)</label><textarea name="notes"></textarea></div>
  </div>
  <div class="modal-f"><button class="btn btn-primary" type="submit">حفظ</button><button class="btn btn-ghost" type="button" onclick="closeModal('mCust')">إلغاء</button></div>
</form></div></div>

<!-- مودال: تعديل عميلة -->
<div class="modal-bg" id="mCustEdit"><div class="modal" style="max-width:460px"><form method="post" action="actions.php">
  <input type="hidden" name="do" value="customer_update"><input type="hidden" name="id" id="ce_id">
  <input type="hidden" name="return" value="index.php?page=customers&filter=<?= h($filter) ?>">
  <div class="modal-h"><h3>تعديل بيانات العميلة</h3><button type="button" class="modal-x" onclick="closeModal('mCustEdit')">✕</button></div>
  <div class="modal-b">
    <div class="field"><label>الاسم</label><input type="text" name="name" id="ce_name"></div>
    <div class="field"><label>رقم الجوال</label><input type="text" name="phone" id="ce_phone" dir="ltr"></div>
    <div class="field"><label>الحالة</label><select name="status" id="ce_status"><option value="client">عميلة</option><option value="visitor">زائر</option><option value="trainee">متدربة</option></select></div>
    <div class="field"><label>ملاحظات</label><textarea name="notes" id="ce_notes"></textarea></div>
  </div>
  <div class="modal-f"><button class="btn btn-primary" type="submit">حفظ التعديلات</button><button class="btn btn-ghost" type="button" onclick="closeModal('mCustEdit')">إلغاء</button></div>
</form></div></div>

<!-- مودال: رسالة جماعية -->
<div class="modal-bg" id="mBroadcast"><div class="modal" style="max-width:480px"><form method="post" action="actions.php">
  <input type="hidden" name="do" value="broadcast_send">
  <input type="hidden" name="return" value="index.php?page=customers&filter=<?= h($filter) ?>">
  <div class="modal-h"><h3>📣 رسالة جماعية</h3><button type="button" class="modal-x" onclick="closeModal('mBroadcast')">✕</button></div>
  <div class="modal-b">
    <div class="field"><label>الشريحة المستهدفة</label>
      <select name="segment">
        <option value="all">كل العملاء (<?= $counts['all'] ?>)</option>
        <option value="clients">العميلات الفعليات (<?= $counts['client'] ?>)</option>
        <option value="visitors">الزوار الذين لم يحجزوا (<?= $counts['visitor'] ?>)</option>
        <option value="trainees">المتدربات (<?= $counts['trainee'] ?>)</option>
      </select>
    </div>
    <div class="field"><label>نص الرسالة</label><textarea name="message" rows="5" placeholder="عرض خاص، إعلان دورة، مواعيد متاحة..." required></textarea></div>
    <div class="flash ok" style="margin:0">💡 تُرسل عبر WhatsApp Cloud API بعد تفعيله من الإعدادات. الآن تُحفظ وتُجهّز قائمة الأرقام.</div>
  </div>
  <div class="modal-f"><button class="btn btn-primary" type="submit">تجهيز الإرسال</button><button class="btn btn-ghost" type="button" onclick="closeModal('mBroadcast')">إلغاء</button></div>
</form></div></div>

<script>
function editCust(c){
  document.getElementById('ce_id').value=c.id;
  document.getElementById('ce_name').value=c.name||'';
  document.getElementById('ce_phone').value=c.phone||'';
  document.getElementById('ce_status').value=c.status||'visitor';
  document.getElementById('ce_notes').value=c.notes||'';
  openModal('mCustEdit');
}
</script>
