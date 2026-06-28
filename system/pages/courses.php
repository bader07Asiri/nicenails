<?php
$courses = array_reverse(db_load('courses'));
$settings = settings_get();
$course = $settings['course'] ?? [];
$price = $course['price'] ?? 4500;
$paid = array_filter($courses, fn($c)=>($c['payment_status']??'')==='paid');
$revenue = array_sum(array_map(fn($c)=>(float)($c['price']??$price), $paid));
?>
<div class="grid g4" style="margin-bottom:1.3rem">
  <div class="stat rose"><div class="si">🎓</div><div class="sv"><?= count($courses) ?></div><div class="sl">متدربات مسجّلات</div></div>
  <div class="stat green"><div class="si">✅</div><div class="sv"><?= count($paid) ?></div><div class="sl">دفعن بالكامل</div></div>
  <div class="stat amber"><div class="si">⏳</div><div class="sv"><?= count($courses)-count($paid) ?></div><div class="sl">بانتظار الدفع</div></div>
  <div class="stat blue"><div class="si">💰</div><div class="sv"><?= money($revenue) ?></div><div class="sl">إيراد الدورات (ريال)</div></div>
</div>

<div class="grid g2" style="grid-template-columns:1fr 1.6fr;align-items:start">
  <div class="card">
    <div class="card-h"><h3>تفاصيل الدورة</h3></div>
    <div class="kv"><b>الاسم</b><?= h($course['name']??'دورة جل اكستنشن') ?></div>
    <div class="kv"><b>المدربة</b><?= h($course['trainer']??'أسماء') ?></div>
    <div class="kv"><b>المدة</b><?= h($course['duration']??'3 أيام / 9 ساعات') ?></div>
    <div class="kv"><b>السعر</b><span class="serif" style="color:var(--rose-deep);font-size:1.1rem"><?= money($price) ?> ريال</span></div>
    <div class="kv"><b>الشهادة</b><span style="font-size:.8rem"><?= h($course['certificate']??'') ?></span></div>
    <div class="divider"></div>
    <b style="font-size:.85rem">المحاور (<?= count($course['topics']??[]) ?>):</b>
    <ol style="margin:.6rem 1.2rem 0;font-size:.84rem;color:var(--mid);line-height:1.9">
      <?php foreach(($course['topics']??[]) as $t): ?><li><?= h($t) ?></li><?php endforeach; ?>
    </ol>
  </div>

  <div>
    <div class="toolbar"><button class="btn btn-primary" onclick="openModal('mCourse')">+ تسجيل متدربة</button>
      <div class="search"><input type="text" placeholder="🔍 بحث..." oninput="liveSearch(this,'crsTable')"></div>
    </div>
    <?php if(!$courses): ?>
      <div class="card"><div class="empty"><div class="ei">🎓</div>لا توجد متدربات مسجّلات بعد</div></div>
    <?php else: ?>
    <div class="tbl-wrap"><table id="crsTable"><thead><tr><th>المتدربة</th><th>الجوال</th><th>موعد الدورة</th><th>الدفع</th><th></th></tr></thead><tbody>
    <?php foreach($courses as $c): $pst=($c['payment_status']??'')==='paid'; ?>
      <tr>
        <td><b><?= h($c['name']) ?></b><div class="muted" style="font-size:.72rem"><?= h(substr($c['date'],0,10)) ?></div></td>
        <td dir="ltr" style="text-align:right"><?= h($c['phone']) ?></td>
        <td><?= $c['course_date']?ar_date($c['course_date']):'<span class="muted">غير محدد</span>' ?></td>
        <td><span class="tag <?= $pst?'t-paid':'t-pending' ?>"><?= $pst?'مدفوع':'معلق' ?></span></td>
        <td><div class="t-actions">
          <?php if(!$pst): ?>
            <?php $msg=strtr($settings['bot']['course_info']??'',['{link}'=>'[رابط التسجيل]']); ?>
            <a class="btn btn-sm btn-wa" target="_blank" href="<?= h(wa_link($c['phone'], "مرحباً ".$c['name'].'، رسوم دورة جل اكستنشن: '.money($price).' ريال. ادفعي لتأكيد مقعدك: [رابط الدفع]')) ?>">📤 رابط الدفع</a>
            <a class="btn btn-sm btn-success" href="actions.php?do=course_pay&id=<?= $c['id'] ?>&return=<?= urlencode('index.php?page=courses') ?>" onclick="return confirm('تأكيد دفع 4,500 ريال؟')">✓ تأكيد الدفع</a>
          <?php else: ?>
            <a class="btn btn-sm btn-ghost" target="_blank" href="booklet.php?id=<?= $c['id'] ?>">📄 كتيب المتدربة</a>
          <?php endif; ?>
          <button class="btn btn-sm btn-ghost" onclick='editCourse(<?= json_encode($c,JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>تعديل</button>
          <a class="btn btn-sm btn-danger" href="actions.php?do=course_delete&id=<?= $c['id'] ?>&return=<?= urlencode('index.php?page=courses') ?>" onclick="return confirmDel()">حذف</a>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </div>
</div>

<!-- مودال: تسجيل متدربة -->
<div class="modal-bg" id="mCourse"><div class="modal" style="max-width:460px"><form method="post" action="actions.php">
  <input type="hidden" name="do" value="course_create"><input type="hidden" name="price" value="<?= (float)$price ?>">
  <input type="hidden" name="return" value="index.php?page=courses">
  <div class="modal-h"><h3>تسجيل متدربة</h3><button type="button" class="modal-x" onclick="closeModal('mCourse')">✕</button></div>
  <div class="modal-b">
    <div class="field"><label>اسم المتدربة <span>*</span></label><input type="text" name="name" required></div>
    <div class="field"><label>رقم الجوال <span>*</span></label><input type="text" name="phone" dir="ltr" placeholder="05xxxxxxxx" required></div>
    <div class="field"><label>موعد الدورة المحجوز</label><input type="date" name="course_date"></div>
    <div class="field"><label>حالة الدفع</label><select name="payment_status"><option value="pending">معلق</option><option value="paid">مدفوع</option></select></div>
    <div class="field"><label>ملاحظات</label><textarea name="notes"></textarea></div>
  </div>
  <div class="modal-f"><button class="btn btn-primary" type="submit">تسجيل</button><button class="btn btn-ghost" type="button" onclick="closeModal('mCourse')">إلغاء</button></div>
</form></div></div>

<!-- مودال: تعديل متدربة -->
<div class="modal-bg" id="mCourseEdit"><div class="modal" style="max-width:460px"><form method="post" action="actions.php">
  <input type="hidden" name="do" value="course_update"><input type="hidden" name="id" id="cr_id">
  <input type="hidden" name="return" value="index.php?page=courses">
  <div class="modal-h"><h3>تعديل بيانات المتدربة</h3><button type="button" class="modal-x" onclick="closeModal('mCourseEdit')">✕</button></div>
  <div class="modal-b">
    <div class="field"><label>الاسم</label><input type="text" name="name" id="cr_name"></div>
    <div class="field"><label>الجوال</label><input type="text" name="phone" id="cr_phone" dir="ltr"></div>
    <div class="field"><label>موعد الدورة</label><input type="date" name="course_date" id="cr_date"></div>
    <div class="field"><label>حالة الدفع</label><select name="payment_status" id="cr_pay"><option value="pending">معلق</option><option value="paid">مدفوع</option></select></div>
    <div class="field"><label>ملاحظات</label><textarea name="notes" id="cr_notes"></textarea></div>
  </div>
  <div class="modal-f"><button class="btn btn-primary" type="submit">حفظ</button><button class="btn btn-ghost" type="button" onclick="closeModal('mCourseEdit')">إلغاء</button></div>
</form></div></div>

<script>
function editCourse(c){
  document.getElementById('cr_id').value=c.id;
  document.getElementById('cr_name').value=c.name||'';
  document.getElementById('cr_phone').value=c.phone||'';
  document.getElementById('cr_date').value=c.course_date||'';
  document.getElementById('cr_pay').value=c.payment_status||'pending';
  document.getElementById('cr_notes').value=c.notes||'';
  openModal('mCourseEdit');
}
</script>
