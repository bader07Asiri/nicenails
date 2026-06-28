<?php
$services = db_load('services');
usort($services, fn($a,$b)=>($a['order']??99)<=>($b['order']??99));
$ptypes=['deposit'=>'عربون + الباقي','full'=>'دفعة واحدة','free'=>'مجاني'];
?>
<div class="toolbar">
  <button class="btn btn-primary" onclick="newService()">+ خدمة جديدة</button>
  <span class="muted">الأسعار ونظام الدفع تُعدَّل من هنا وتنعكس مباشرة في الطلبات وبوت الواتساب.</span>
</div>

<?php if(!$services): ?>
  <div class="card"><div class="empty"><div class="ei">💅</div>لا توجد خدمات — أضيفي أول خدمة</div></div>
<?php else: ?>
<div class="grid g3">
<?php foreach($services as $s): ?>
  <div class="card" style="<?= ($s['active']??true)?'':'opacity:.55' ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem">
      <div><h3 class="serif" style="font-size:1.15rem;color:var(--dark)"><?= h($s['name']) ?></h3>
      <div class="muted" style="margin-top:3px">⏱️ <?= h($s['duration']) ?></div></div>
      <?php if(!($s['active']??true)): ?><span class="tag t-cancelled">موقوفة</span><?php endif; ?>
    </div>
    <div class="divider" style="margin:.9rem 0"></div>
    <div style="display:flex;align-items:baseline;gap:5px;margin-bottom:.7rem">
      <span class="serif" style="font-size:2rem;color:var(--rose-deep)"><?= money($s['price']) ?></span><span class="muted">ريال</span>
    </div>
    <div class="pill-row" style="margin-bottom:1rem">
      <span class="tag t-client"><?= $ptypes[$s['payment_type']]??$s['payment_type'] ?></span>
      <?php if(($s['payment_type']??'')==='deposit'): ?><span class="tag t-confirmed">عربون <?= money($s['deposit']??200) ?> ﷼</span><?php endif; ?>
    </div>
    <div class="t-actions">
      <button class="btn btn-sm btn-ghost btn-block" onclick='editService(<?= json_encode($s,JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>تعديل</button>
      <a class="btn btn-sm btn-danger" href="actions.php?do=service_delete&id=<?= $s['id'] ?>&return=<?= urlencode('index.php?page=services') ?>" onclick="return confirmDel('حذف هذه الخدمة؟')">حذف</a>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="modal-bg" id="mSvc"><div class="modal" style="max-width:480px"><form method="post" action="actions.php">
  <input type="hidden" name="do" value="service_save"><input type="hidden" name="id" id="s_id">
  <input type="hidden" name="return" value="index.php?page=services">
  <div class="modal-h"><h3 id="s_title">خدمة جديدة</h3><button type="button" class="modal-x" onclick="closeModal('mSvc')">✕</button></div>
  <div class="modal-b">
    <div class="field"><label>اسم الخدمة <span>*</span></label><input type="text" name="name" id="s_name" required></div>
    <div class="frow">
      <div class="field"><label>المدة التقريبية</label><input type="text" name="duration" id="s_duration" placeholder="مثال: 2-3 ساعات"></div>
      <div class="field"><label>السعر (ريال)</label><input type="number" name="price" id="s_price" min="0" step="1"></div>
    </div>
    <div class="frow">
      <div class="field"><label>نظام الدفع</label>
        <select name="payment_type" id="s_ptype" onchange="toggleDep()">
          <option value="deposit">عربون + الباقي</option>
          <option value="full">دفعة واحدة كاملة</option>
          <option value="free">مجاني</option>
        </select>
      </div>
      <div class="field" id="s_depwrap"><label>قيمة العربون</label><input type="number" name="deposit" id="s_deposit" min="0" step="1" value="200"></div>
    </div>
    <div class="field"><label>الحالة</label><select name="active" id="s_active"><option value="1">مفعّلة</option><option value="0">موقوفة</option></select></div>
  </div>
  <div class="modal-f"><button class="btn btn-primary" type="submit">حفظ</button><button class="btn btn-ghost" type="button" onclick="closeModal('mSvc')">إلغاء</button></div>
</form></div></div>

<script>
function toggleDep(){document.getElementById('s_depwrap').style.display=document.getElementById('s_ptype').value==='deposit'?'':'none';}
function newService(){
  document.getElementById('s_title').innerText='خدمة جديدة';
  document.getElementById('s_id').value='';
  ['s_name','s_duration','s_price'].forEach(i=>document.getElementById(i).value='');
  document.getElementById('s_ptype').value='deposit';
  document.getElementById('s_deposit').value=200;
  document.getElementById('s_active').value='1';
  toggleDep();openModal('mSvc');
}
function editService(s){
  document.getElementById('s_title').innerText='تعديل الخدمة';
  document.getElementById('s_id').value=s.id;
  document.getElementById('s_name').value=s.name||'';
  document.getElementById('s_duration').value=s.duration||'';
  document.getElementById('s_price').value=s.price||0;
  document.getElementById('s_ptype').value=s.payment_type||'full';
  document.getElementById('s_deposit').value=s.deposit||0;
  document.getElementById('s_active').value=(s.active===false)?'0':'1';
  toggleDep();openModal('mSvc');
}
</script>
