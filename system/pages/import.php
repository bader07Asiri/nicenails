<?php
$msgCount = count(db_load('messages'));
$custCount = count(db_load('customers'));
?>
<div class="grid g2" style="grid-template-columns:1.4fr 1fr;align-items:start">
  <div class="card">
    <div class="card-h"><h3>📥 استيراد محادثات واتساب</h3></div>
    <p class="muted" style="margin-bottom:1rem">
      ارفعي ملف <b>JSON</b> الناتج من أداة استخراج محادثات الواتساب (من نسختك الاحتياطية).
      راح تُستورد كل المحادثات بالأرقام والتواريخ، وتُسجّل العميلات تلقائياً، وتظهر في صفحة <b>المحادثات</b>.
    </p>
    <form method="post" action="actions.php" enctype="multipart/form-data">
      <input type="hidden" name="do" value="import_whatsapp">
      <input type="hidden" name="return" value="index.php?page=import">
      <div class="field">
        <label>ملف المحادثات (JSON) <span>*</span></label>
        <input type="file" name="chatfile" accept=".json,application/json" required>
        <span class="hint">الملف الناتج من WhatsApp-Chat-Exporter بصيغة JSON.</span>
      </div>
      <button class="btn btn-primary" type="submit">استيراد الآن</button>
    </form>
    <div class="flash ok" style="margin-top:1rem">
      💡 الرسائل المستوردة تُعلّم بـ "import" وتدخل في نفس الـ Inbox مع الرسائل الجديدة.
    </div>
  </div>

  <div class="card">
    <div class="card-h"><h3>الحالة</h3></div>
    <div class="kv"><b>الرسائل المخزّنة</b><?= number_format($msgCount) ?></div>
    <div class="kv"><b>العملاء المسجّلون</b><?= number_format($custCount) ?></div>
    <div class="divider"></div>
    <b style="font-size:.85rem">كيف أطلّع ملف الـ JSON؟</b>
    <ol style="margin:.6rem 1.2rem 0;font-size:.82rem;color:var(--mid);line-height:1.9">
      <li>سوّي نسخة احتياطية مشفّرة للآيفون على الكمبيوتر.</li>
      <li>شغّل أداة WhatsApp-Chat-Exporter على النسخة.</li>
      <li>اطلب الإخراج بصيغة JSON.</li>
      <li>ارفع الملف هنا.</li>
    </ol>
  </div>
</div>
