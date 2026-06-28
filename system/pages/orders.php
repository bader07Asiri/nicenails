<?php
$orders   = array_reverse(db_load('orders'));
$services = db_load('services');
$settings = settings_get();
$filter = $_GET['filter'] ?? 'all';
$stm=['pending'=>'بانتظار الدفع','confirmed'=>'عربون مدفوع','completed'=>'مكتمل','cancelled'=>'ملغي'];

$counts=['all'=>count($orders),'pending'=>0,'confirmed'=>0,'completed'=>0,'cancelled'=>0];
foreach($orders as $o){ $counts[$o['status']]=($counts[$o['status']]??0)+1; }
$list = $filter==='all' ? $orders : array_values(array_filter($orders, fn($o)=>($o['status']??'')===$filter));

// روابط الدفع: نص الرسالة
function pay_msg($settings,$name,$amount,$link){
    $t=$settings['auto_messages']['payment_link']??'مرحباً {name}، المبلغ: {amount} ريال — ادفعي هنا: {link}';
    return strtr($t,['{name}'=>$name,'{amount}'=>money($amount),'{link}'=>$link]);
}
function pay_link($settings,$order,$amount){
    $base=$settings['geidea']['base_link']??'';
    if($base) return rtrim($base,'/').'?amount='.$amount.'&ref='.urlencode($order['id']);
    return '[رابط الدفع — يُفعّل بعد ربط Geidea]';
}
?>
<div class="toolbar">
  <button class="btn btn-primary" onclick="openModal('mOrder')">+ طلب جديد</button>
  <div class="chips">
    <?php foreach(['all'=>'الكل','pending'=>'بانتظار الدفع','confirmed'=>'عربون مدفوع','completed'=>'مكتمل','cancelled'=>'ملغي'] as $k=>$lbl): ?>
      <a class="chip <?= $filter===$k?'active':'' ?>" href="?page=orders&filter=<?= $k ?>"><?= $lbl ?> <?= $counts[$k]?'('.$counts[$k].')':'' ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if(!$list): ?>
  <div class="card"><div class="empty"><div class="ei">💳</div>لا توجد طلبات في هذا التصنيف</div></div>
<?php else: ?>
<div class="grid" style="gap:1rem">
<?php foreach($list as $o):
  $remaining=max(0,(float)$o['total']-(float)($o['paid']??0));
  $isFree=($o['payment_type']??'')==='free';
?>
  <div class="card" style="padding:1.1rem 1.3rem">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
      <div style="flex:1;min-width:200px">
        <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
          <b style="font-size:1.05rem"><?= h($o['customer_name']) ?></b>
          <span class="tag t-<?= h($o['status']) ?>"><?= $stm[$o['status']]??$o['status'] ?></span>
          <?php if($isFree): ?><span class="tag t-visitor">مجاني</span><?php endif; ?>
        </div>
        <div class="muted" style="margin-top:3px"><?= h($o['service_name']) ?><?= $o['phone']?' • '.h($o['phone']):'' ?><?= $o['appt_date']?' • '.ar_date($o['appt_date']).' '.h($o['appt_time']):'' ?></div>
        <?php if($o['design_details']): ?><div class="muted" style="margin-top:3px">🎨 <?= h($o['design_details']) ?></div><?php endif; ?>
      </div>
      <?php if(!$isFree): ?>
      <div class="pay-box" style="min-width:200px;margin:0">
        <div class="pay-row"><span>الإجمالي</span><b><?= money($o['total']) ?> ﷼</b></div>
        <div class="pay-row"><span>المدفوع</span><span style="color:var(--green)"><?= money($o['paid']??0) ?> ﷼</span></div>
        <div class="pay-row total"><span>المتبقي</span><span style="color:var(--rose-deep)"><?= money($remaining) ?> ﷼</span></div>
      </div>
      <?php endif; ?>
    </div>

    <div class="divider" style="margin:.9rem 0"></div>
    <div class="t-actions">
      <?php if(($o['status']??'')==='cancelled'): ?>
        <span class="muted">طلب ملغي</span>
      <?php elseif($isFree): ?>
        <?php $msg=trim(($settings['bot']['booking_link']??'')."\n".($settings['terms']??'')); ?>
        <a class="btn btn-sm btn-wa" target="_blank" href="<?= h(wa_link($o['phone'], "مرحباً ".$o['customer_name']."، تفاصيل موعدكِ ".$o['service_name']." — ".ar_date($o['appt_date'])." ".$o['appt_time'].". الشروط: ".($settings['terms']??''))) ?>">إرسال التفاصيل + الشروط</a>
        <a class="btn btn-sm btn-success" href="actions.php?do=order_status&id=<?= $o['id'] ?>&status=completed&return=<?= urlencode("index.php?page=orders&filter=$filter") ?>">إتمام</a>
      <?php else:
        // مرحلة العربون / الدفعة الكاملة
        if(!($o['deposit_paid']??false)):
          $amt=($o['payment_type']==='deposit')?($o['deposit']?:200):$o['total'];
          $lnk=pay_link($settings,$o,$amt);
          $msg=pay_msg($settings,$o['customer_name'],$amt,$lnk);
      ?>
          <a class="btn btn-sm btn-wa" target="_blank" href="<?= h(wa_link($o['phone'],$msg)) ?>">📤 إرسال رابط <?= $o['payment_type']==='deposit'?'العربون ('.money($amt).')':'الدفع ('.money($amt).')' ?></a>
          <button class="btn btn-sm btn-ghost" onclick="copyText(<?= htmlspecialchars(json_encode($lnk),ENT_QUOTES) ?>,this)">نسخ الرابط</button>
          <a class="btn btn-sm btn-success" href="actions.php?do=order_pay_deposit&id=<?= $o['id'] ?>&return=<?= urlencode("index.php?page=orders&filter=$filter") ?>" onclick="return confirm('تأكيد استلام الدفعة؟')">✓ تأكيد الدفع</a>
      <?php
        elseif($o['payment_type']==='deposit' && !($o['remaining_paid']??false)):
          // إرسال/تأكيد الباقي مع تعديل المبلغ
      ?>
          <button class="btn btn-sm btn-primary" onclick="openRemaining('<?= $o['id'] ?>','<?= h(addslashes($o['customer_name'])) ?>',<?= (float)$o['total'] ?>,<?= (float)($o['deposit']?:0) ?>)">💳 إرسال رابط الباقي</button>
          <a class="btn btn-sm btn-wa" target="_blank" href="<?= h(wa_link($o['phone'])) ?>">واتساب</a>
      <?php else: ?>
          <span class="tag t-completed">✅ مدفوع بالكامل</span>
      <?php endif; endif; ?>

      <?php if(($o['status']??'')!=='cancelled' && ($o['status']??'')!=='completed'): ?>
        <button class="btn btn-sm btn-ghost" onclick="editOrder('<?= $o['id'] ?>',<?= (float)$o['total'] ?>,<?= htmlspecialchars(json_encode($o['design_details']),ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($o['notes']),ENT_QUOTES) ?>)">تعديل</button>
        <a class="btn btn-sm btn-danger" href="actions.php?do=order_status&id=<?= $o['id'] ?>&status=cancelled&return=<?= urlencode("index.php?page=orders&filter=$filter") ?>" onclick="return confirm('إلغاء الطلب؟')">إلغاء</a>
      <?php endif; ?>
      <a class="btn btn-sm btn-danger" href="actions.php?do=order_delete&id=<?= $o['id'] ?>&return=<?= urlencode("index.php?page=orders&filter=$filter") ?>" onclick="return confirmDel()">حذف</a>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- مودال: طلب جديد -->
<div class="modal-bg" id="mOrder"><div class="modal"><form method="post" action="actions.php">
  <input type="hidden" name="do" value="order_create">
  <input type="hidden" name="return" value="index.php?page=orders">
  <div class="modal-h"><h3>طلب جديد</h3><button type="button" class="modal-x" onclick="closeModal('mOrder')">✕</button></div>
  <div class="modal-b">
    <div class="frow">
      <div class="field"><label>اسم العميلة <span>*</span></label><input type="text" name="customer_name" required></div>
      <div class="field"><label>رقم الجوال <span>*</span></label><input type="text" name="phone" placeholder="05xxxxxxxx" dir="ltr" required></div>
    </div>
    <div class="field"><label>الخدمة <span>*</span></label>
      <select name="service_id" id="o_service" onchange="onServiceChange(this)" required>
        <option value="">— اختاري الخدمة —</option>
        <?php foreach($services as $s): if(!($s['active']??true))continue; ?>
          <option value="<?= h($s['id']) ?>" data-price="<?= (float)$s['price'] ?>" data-deposit="<?= (float)($s['deposit']??0) ?>" data-ptype="<?= h($s['payment_type']) ?>">
            <?= h($s['name']) ?> — <?= $s['payment_type']==='deposit'?'عربون '.money($s['deposit']??200):'دفعة واحدة' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>تفاصيل التصميم</label><input type="text" name="design_details" placeholder="لون، شكل، إضافات..."></div>
    <div class="frow">
      <div class="field"><label>المبلغ الإجمالي (ريال) <span>*</span></label><input type="number" name="total" id="o_total" min="0" step="1" oninput="calcRemaining()" required></div>
      <div class="field"><label>العربون</label><input type="number" name="deposit" id="o_deposit" min="0" step="1" readonly style="background:var(--cream)"></div>
    </div>
    <div class="pay-box"><div class="pay-row total"><span>المتبقي للجلسة</span><span><b id="o_remaining">0</b> ﷼</span></div>
      <div class="muted" style="margin-top:4px">للخدمة المجانية ضعي الإجمالي = 0</div></div>
    <div class="frow" style="margin-top:1rem">
      <div class="field"><label>تاريخ الموعد</label><input type="date" name="appt_date"></div>
      <div class="field"><label>الوقت</label><input type="time" name="appt_time"></div>
    </div>
    <div class="field"><label>ملاحظات</label><textarea name="notes"></textarea></div>
  </div>
  <div class="modal-f"><button class="btn btn-primary" type="submit">تسجيل الطلب</button><button class="btn btn-ghost" type="button" onclick="closeModal('mOrder')">إلغاء</button></div>
</form></div></div>

<!-- مودال: رابط الباقي مع تعديل المبلغ -->
<div class="modal-bg" id="mRemain"><div class="modal" style="max-width:460px"><form method="post" action="actions.php">
  <input type="hidden" name="do" value="order_pay_remaining">
  <input type="hidden" name="id" id="r_id">
  <input type="hidden" name="return" value="index.php?page=orders&filter=<?= h($filter) ?>">
  <div class="modal-h"><h3>إرسال رابط الباقي</h3><button type="button" class="modal-x" onclick="closeModal('mRemain')">✕</button></div>
  <div class="modal-b">
    <p class="muted" style="margin-bottom:1rem">عدّلي المبلغ النهائي إذا أُضيفت خدمة إضافية، ثم أرسلي الرابط للعميلة.</p>
    <div class="field"><label>العميلة</label><input type="text" id="r_name" readonly style="background:var(--cream)"></div>
    <div class="field"><label>المبلغ النهائي الإجمالي (ريال)</label><input type="number" name="final_total" id="r_total" min="0" step="1" oninput="calcRemain2()"></div>
    <div class="pay-box">
      <div class="pay-row"><span>العربون المدفوع</span><span id="r_dep">0</span></div>
      <div class="pay-row total"><span>المتبقي المطلوب</span><span><b id="r_remain">0</b> ﷼</span></div>
    </div>
  </div>
  <div class="modal-f">
    <a class="btn btn-wa" id="r_wa" target="_blank" href="#">📤 إرسال للعميلة عبر واتساب</a>
    <button class="btn btn-success" type="submit" onclick="return confirm('تأكيد استلام باقي المبلغ وإتمام الطلب؟')">✓ تأكيد الدفع وإتمام</button>
  </div>
</form></div></div>

<!-- مودال: تعديل الطلب -->
<div class="modal-bg" id="mEdit"><div class="modal" style="max-width:460px"><form method="post" action="actions.php">
  <input type="hidden" name="do" value="order_update"><input type="hidden" name="id" id="e_id">
  <input type="hidden" name="return" value="index.php?page=orders&filter=<?= h($filter) ?>">
  <div class="modal-h"><h3>تعديل الطلب</h3><button type="button" class="modal-x" onclick="closeModal('mEdit')">✕</button></div>
  <div class="modal-b">
    <div class="field"><label>المبلغ الإجمالي</label><input type="number" name="total" id="e_total" min="0" step="1"></div>
    <div class="field"><label>تفاصيل التصميم</label><input type="text" name="design_details" id="e_design"></div>
    <div class="field"><label>ملاحظات</label><textarea name="notes" id="e_notes"></textarea></div>
  </div>
  <div class="modal-f"><button class="btn btn-primary" type="submit">حفظ</button><button class="btn btn-ghost" type="button" onclick="closeModal('mEdit')">إلغاء</button></div>
</form></div></div>

<script>
var _rDep=0;
function openRemaining(id,name,total,dep){
  document.getElementById('r_id').value=id;
  document.getElementById('r_name').value=name;
  document.getElementById('r_total').value=total;
  document.getElementById('r_dep').innerText=dep.toLocaleString()+' ﷼';
  _rDep=dep; calcRemain2(); openModal('mRemain');
}
function calcRemain2(){
  var t=parseFloat(document.getElementById('r_total').value)||0;
  var rem=Math.max(0,t-_rDep);
  document.getElementById('r_remain').innerText=rem.toLocaleString();
  var name=document.getElementById('r_name').value;
  var msg='مرحباً '+name+'، باقي مبلغ جلستكِ: '+rem.toLocaleString()+' ريال — ادفعي من هنا: [رابط الدفع]';
  document.getElementById('r_wa').href='https://wa.me/?text='+encodeURIComponent(msg);
}
function editOrder(id,total,design,notes){
  document.getElementById('e_id').value=id;
  document.getElementById('e_total').value=total;
  document.getElementById('e_design').value=design||'';
  document.getElementById('e_notes').value=notes||'';
  openModal('mEdit');
}
</script>
