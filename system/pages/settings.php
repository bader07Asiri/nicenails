<?php
$s = settings_get();
$wa = $s['whatsapp'] ?? [];
$gd = $s['geidea'] ?? [];
$bot = $s['bot'] ?? [];
$webhookUrl = ($s['site_url']??'https://نطاقك') . '/system/whatsapp.php';
?>
<div class="grid g2" style="align-items:start">

  <!-- معلومات النشاط -->
  <div class="card">
    <div class="card-h"><h3>معلومات النشاط</h3></div>
    <form method="post" action="actions.php">
      <input type="hidden" name="do" value="settings_save">
      <div class="frow">
        <div class="field"><label>اسم النشاط</label><input name="biz_name" value="<?= h($s['biz_name']??'Nice Nail') ?>"></div>
        <div class="field"><label>اسم الأخصائية</label><input name="specialist_name" value="<?= h($s['specialist_name']??'') ?>"></div>
      </div>
      <div class="frow">
        <div class="field"><label>رقم الواتساب</label><input name="phone" dir="ltr" value="<?= h($s['phone']??'') ?>" placeholder="9665xxxxxxxx"></div>
        <div class="field"><label>حساب انستقرام</label><input name="instagram" value="<?= h($s['instagram']??'') ?>" placeholder="nicenails"></div>
      </div>
      <div class="frow">
        <div class="field"><label>أوقات العمل</label><input name="working_hours" value="<?= h($s['working_hours']??'') ?>"></div>
        <div class="field"><label>العربون الافتراضي (ريال)</label><input type="number" name="default_deposit" value="<?= h($s['default_deposit']??200) ?>"></div>
      </div>
      <div class="frow">
        <div class="field"><label>مهلة الإلغاء (ساعة)</label><input type="number" name="cancel_policy_hours" value="<?= h($s['cancel_policy_hours']??24) ?>"></div>
        <div class="field"><label>رابط الموقع (Domain)</label><input name="site_url" dir="ltr" value="<?= h($s['site_url']??'') ?>" placeholder="https://nailart.sa"><span class="hint">يُستخدم في روابط البوت والـ Webhook.</span></div>
      </div>
      <div class="field"><label>الشروط والأحكام</label><textarea name="terms" rows="6"><?= h($s['terms']??'') ?></textarea><span class="hint">تُرسل تلقائياً للعميلة بعد الدفع.</span></div>
      <button class="btn btn-primary btn-block" type="submit">حفظ معلومات النشاط</button>
    </form>
  </div>

  <div>
    <!-- بوابة الدفع Geidea -->
    <div class="card" style="margin-bottom:1.4rem">
      <div class="card-h"><h3>بوابة الدفع — Geidea</h3>
        <span class="tag <?= !empty($gd['enabled'])?'t-paid':'t-pending' ?>"><?= !empty($gd['enabled'])?'مفعّلة':'قيد المراجعة' ?></span></div>
      <form method="post" action="actions.php">
        <input type="hidden" name="do" value="settings_save">
        <input type="hidden" name="biz_name" value="<?= h($s['biz_name']??'') ?>">
        <div class="field"><label>الحالة</label>
          <select name="geidea_enabled"><option value="0" <?= empty($gd['enabled'])?'selected':'' ?>>غير مفعّلة (انتظار الموافقة)</option><option value="1" <?= !empty($gd['enabled'])?'selected':'' ?>>مفعّلة</option></select>
        </div>
        <div class="field"><label>Merchant ID</label><input name="geidea_merchant" dir="ltr" value="<?= h($gd['merchant_id']??'') ?>"></div>
        <div class="field"><label>API Key</label><input name="geidea_key" dir="ltr" value="<?= h($gd['api_key']??'') ?>"></div>
        <div class="field"><label>رابط الدفع الأساسي (Payment Link)</label><input name="geidea_link" dir="ltr" value="<?= h($gd['base_link']??'') ?>" placeholder="https://pay.geidea.net/..."><span class="hint">يُستخدم لتوليد روابط الدفع تلقائياً في الطلبات. تابي وتمارا تُفعّل من لوحة Geidea.</span></div>
        <button class="btn btn-primary btn-block" type="submit">حفظ إعدادات Geidea</button>
      </form>
    </div>

    <!-- واتساب Cloud API -->
    <div class="card">
      <div class="card-h"><h3>واتساب — Cloud API</h3>
        <span class="tag <?= !empty($wa['enabled'])?'t-paid':'t-pending' ?>"><?= !empty($wa['enabled'])?'مفعّل':'غير مفعّل' ?></span></div>
      <div class="flash ok" style="margin-bottom:1rem">
        رابط الـ Webhook (ضعيه في Meta):<br>
        <code dir="ltr" style="font-size:.78rem;word-break:break-all"><?= h($webhookUrl) ?></code>
      </div>
      <form method="post" action="actions.php">
        <input type="hidden" name="do" value="settings_save">
        <input type="hidden" name="biz_name" value="<?= h($s['biz_name']??'') ?>">
        <div class="field"><label>الحالة</label>
          <select name="wa_enabled"><option value="0" <?= empty($wa['enabled'])?'selected':'' ?>>غير مفعّل</option><option value="1" <?= !empty($wa['enabled'])?'selected':'' ?>>مفعّل (يرسل ردود تلقائية)</option></select>
        </div>
        <div class="field"><label>Verify Token</label><input name="wa_verify" dir="ltr" value="<?= h($wa['verify_token']??'') ?>"></div>
        <div class="field"><label>Phone Number ID</label><input name="wa_phoneid" dir="ltr" value="<?= h($wa['phone_number_id']??'') ?>"></div>
        <div class="field"><label>Access Token</label><textarea name="wa_token" rows="2" dir="ltr"><?= h($wa['access_token']??'') ?></textarea><span class="hint">من إعدادات تطبيق Meta. مجاني حتى 1,000 محادثة/شهر.</span></div>
        <button class="btn btn-primary btn-block" type="submit">حفظ إعدادات الواتساب</button>
      </form>
    </div>
  </div>
</div>


<!-- الإشعارات -->
<div class="card" style="margin-top:1.4rem">
  <div class="card-h"><h3>🔔 الإشعارات</h3><span class="muted">يصلك تنبيه على جوالك عند رسالة/حجز جديد</span></div>

  <div class="flash ok" style="margin-bottom:1rem">
    <div style="flex:1">
      <b>إشعارات هذا الجهاز (داخل التطبيق)</b><br>
      <span style="font-size:.8rem">ثبّتي النظام على الشاشة الرئيسية ثم فعّلي الإشعارات ليصلك تنبيه حتى والتطبيق مقفل.</span>
    </div>
    <button type="button" class="btn btn-primary btn-sm" onclick="enablePush()">تفعيل على هذا الجهاز</button>
  </div>

  <form method="post" action="actions.php">
    <input type="hidden" name="do" value="notify_save">
    <?php $tg=$s['telegram']??[]; $wp=$s['webpush']??[]; $nf=$s['notify']??[]; ?>

    <h4 style="margin:.4rem 0 .6rem;color:var(--mid)">قنوات الإشعار</h4>
    <div class="notif-toggle">
      <div><b>Telegram</b><div class="muted" style="font-size:.78rem">إشعار فوري وموثوق على جوالك</div></div>
      <label class="switch"><input type="checkbox" name="tg_enabled" value="1" <?= !empty($tg['enabled'])?'checked':'' ?>><span class="sl"></span></label>
    </div>
    <div class="field" style="margin-top:.7rem"><label>Telegram Bot Token</label><input name="tg_token" dir="ltr" value="<?= h($tg['bot_token']??'') ?>" placeholder="من @BotFather"></div>
    <div class="field"><label>Chat ID (رقم محادثتك)</label><input name="tg_chat" dir="ltr" value="<?= h($tg['chat_id']??'') ?>" placeholder="من @userinfobot"><span class="hint">أرسلي رسالة للبوت أولاً، ثم احفظي مع "إرسال تجربة".</span></div>

    <div class="notif-toggle" style="margin-top:.6rem">
      <div><b>إشعار داخل التطبيق (Push)</b><div class="muted" style="font-size:.78rem">يحتاج تفعيل على الجهاز من الزر بالأعلى</div></div>
      <label class="switch"><input type="checkbox" name="wp_enabled" value="1" <?= !empty($wp['enabled'])?'checked':'' ?>><span class="sl"></span></label>
    </div>

    <div class="divider"></div>
    <h4 style="margin:.4rem 0 .6rem;color:var(--mid)">متى أُشعَر؟</h4>
    <div class="notif-toggle"><div>رسالة واتساب جديدة</div><label class="switch"><input type="checkbox" name="n_message" value="1" <?= ($nf['on_message']??true)?'checked':'' ?>><span class="sl"></span></label></div>
    <div class="notif-toggle"><div>طلب/دفعة جديدة</div><label class="switch"><input type="checkbox" name="n_order" value="1" <?= ($nf['on_order']??true)?'checked':'' ?>><span class="sl"></span></label></div>
    <div class="notif-toggle"><div>موعد جديد</div><label class="switch"><input type="checkbox" name="n_booking" value="1" <?= ($nf['on_booking']??true)?'checked':'' ?>><span class="sl"></span></label></div>
    <div class="notif-toggle"><div>تسجيل متدربة بالدورة</div><label class="switch"><input type="checkbox" name="n_course" value="1" <?= ($nf['on_course']??true)?'checked':'' ?>><span class="sl"></span></label></div>

    <div style="display:flex;gap:.6rem;margin-top:1.1rem">
      <button class="btn btn-primary" type="submit">حفظ الإعدادات</button>
      <button class="btn btn-ghost" type="submit" name="tg_test" value="1">حفظ + إرسال تجربة على Telegram</button>
    </div>
  </form>
</div>

<!-- نصوص البوت -->
<div class="card" style="margin-top:1.4rem">
  <div class="card-h"><h3>نصوص بوت الواتساب</h3><span class="muted">عدّلي ردود القائمة التلقائية</span></div>
  <form method="post" action="actions.php">
    <input type="hidden" name="do" value="bot_save">
    <div class="grid g2">
      <div class="field"><label>القائمة الرئيسية (تحية)</label><textarea name="bot_greeting" rows="7"><?= h($bot['greeting']??'') ?></textarea></div>
      <div class="field"><label>مقدمة حجز موعد</label><textarea name="bot_booking_intro" rows="7"><?= h($bot['booking_intro']??'') ?></textarea><span class="hint">استخدمي {slots} لإدراج المواعيد المتاحة.</span></div>
      <div class="field"><label>سؤال نوع الخدمة</label><textarea name="bot_booking_service_q" rows="6"><?= h($bot['booking_service_q']??'') ?></textarea></div>
      <div class="field"><label>رسالة رابط إكمال الطلب</label><textarea name="bot_booking_link" rows="6"><?= h($bot['booking_link']??'') ?></textarea><span class="hint">استخدمي {link}.</span></div>
      <div class="field"><label>التحويل للأخصائية</label><textarea name="bot_to_human" rows="4"><?= h($bot['to_human']??'') ?></textarea><span class="hint">استخدمي {hours}.</span></div>
      <div class="field"><label>نماذج وأسعار</label><textarea name="bot_samples" rows="4"><?= h($bot['samples']??'') ?></textarea><span class="hint">{services} و {instagram}.</span></div>
      <div class="field" style="grid-column:1/-1"><label>تفاصيل الدورة التدريبية</label><textarea name="bot_course_info" rows="6"><?= h($bot['course_info']??'') ?></textarea><span class="hint">استخدمي {link}.</span></div>
    </div>
    <button class="btn btn-primary" type="submit">حفظ نصوص البوت</button>
  </form>
</div>

<!-- الردود الجاهزة -->
<div class="grid g2" style="margin-top:1.4rem;align-items:start">
  <div class="card">
    <div class="card-h"><h3>ردود الاستفسارات الجاهزة</h3></div>
    <?php foreach(($s['inquiries']??[]) as $q): ?>
      <div style="padding:9px 0;border-bottom:1px solid var(--line)"><b style="font-size:.86rem"><?= h($q['q']) ?></b><div class="muted"><?= h($q['a']) ?></div></div>
    <?php endforeach; ?>
  </div>
  <div class="card">
    <div class="card-h"><h3>الأسئلة الشائعة الجاهزة</h3></div>
    <?php foreach(($s['faq']??[]) as $q): ?>
      <div style="padding:9px 0;border-bottom:1px solid var(--line)"><b style="font-size:.86rem"><?= h($q['q']) ?></b><div class="muted"><?= h($q['a']) ?></div></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card" style="margin-top:1.4rem">
  <div class="card-h"><h3>كلمة المرور والأمان</h3></div>
  <p class="muted">لتغيير كلمة مرور الدخول، عدّلي السطر في ملف <code>system/config.php</code>:<br>
  <code dir="ltr">define('ADMIN_PASSWORD', 'كلمة_المرور_الجديدة');</code></p>
</div>
