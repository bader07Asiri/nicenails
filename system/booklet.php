<?php
// كتيب المتدربة — يُولّد تلقائياً باسم المتدربة وتاريخ الدورة
session_start();
require_once __DIR__ . '/lib.php';
if (empty($_SESSION['admin_logged_in'])) { header('Location: index.php'); exit; }

$courses = db_load('courses');
$c = rec_find($courses, $_GET['id'] ?? '');
if (!$c) { die('المتدربة غير موجودة'); }
$s = settings_get();
$course = $s['course'] ?? [];
$topics = $course['topics'] ?? [];
?>
<!DOCTYPE html><html lang="ar" dir="rtl"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>كتيب المتدربة — <?= h($c['name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">
<style>
:root{--rose:#C8686B;--rose-deep:#A34E51;--rose-pale:#FAF0F0;--cream:#FDFAF8;--dark:#2C2420;--mid:#5C4E4A;--light:#8C7E7A;--line:rgba(200,104,107,.15)}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Cairo',sans-serif;background:#e9e3df;color:var(--dark);direction:rtl;padding:2rem 1rem}
.page{max-width:780px;margin:0 auto 1.5rem;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.12)}
.cover{background:linear-gradient(155deg,var(--cream) 45%,#F7EAEA 100%);padding:3.5rem 2.5rem;text-align:center;position:relative}
.cover img{width:120px;margin-bottom:1.2rem;filter:drop-shadow(0 8px 24px rgba(200,104,107,.25))}
.cover .eyebrow{font-size:.78rem;letter-spacing:4px;color:var(--rose);font-weight:700;text-transform:uppercase;margin-bottom:.6rem}
.cover h1{font-family:'Playfair Display',serif;font-size:2.4rem;color:var(--dark);margin-bottom:.3rem}
.cover h1 em{font-style:italic;color:var(--rose)}
.cover .sub{color:var(--mid);font-size:1rem;margin-bottom:2rem}
.name-plate{display:inline-block;background:#fff;border:1.5px solid var(--line);border-radius:50px;padding:.7rem 2rem;box-shadow:0 6px 20px rgba(200,104,107,.12)}
.name-plate small{display:block;font-size:.72rem;color:var(--light);margin-bottom:2px}
.name-plate b{font-family:'Playfair Display',serif;font-size:1.4rem;color:var(--rose-deep)}
.body{padding:2.5rem}
.meta{display:flex;gap:1rem;flex-wrap:wrap;justify-content:center;margin-bottom:2.2rem}
.meta div{background:var(--rose-pale);border-radius:12px;padding:.8rem 1.3rem;text-align:center;min-width:120px}
.meta small{display:block;color:var(--light);font-size:.72rem;margin-bottom:3px}
.meta b{color:var(--rose-deep);font-size:.95rem}
h2{font-family:'Playfair Display',serif;font-size:1.4rem;color:var(--dark);margin-bottom:1.2rem;padding-bottom:.5rem;border-bottom:2px solid var(--rose-pale)}
h2 em{font-style:italic;color:var(--rose)}
.topic{display:flex;gap:1rem;align-items:flex-start;background:var(--cream);border-radius:12px;padding:1rem 1.2rem;margin-bottom:.8rem;border:1px solid var(--line)}
.topic .n{width:34px;height:34px;border-radius:50%;background:var(--rose);color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-family:'Playfair Display',serif}
.topic .tt{font-weight:700;color:var(--dark);margin-bottom:2px}
.cert{margin-top:2.2rem;background:linear-gradient(135deg,var(--rose),var(--rose-deep));color:#fff;border-radius:14px;padding:1.8rem;text-align:center}
.cert .ic{font-size:2.4rem;margin-bottom:.5rem}
.cert b{font-size:1.1rem;display:block;margin-bottom:.4rem;font-family:'Playfair Display',serif}
.notes-area{margin-top:2rem}
.notes-line{border-bottom:1.5px dashed var(--line);height:34px}
footer{text-align:center;padding:1.5rem;color:var(--light);font-size:.8rem}
.toolbar-print{max-width:780px;margin:0 auto 1rem;display:flex;gap:.6rem;justify-content:center}
.btn{display:inline-flex;gap:6px;align-items:center;background:var(--rose);color:#fff;border:none;border-radius:50px;padding:11px 24px;font-family:'Cairo';font-weight:700;cursor:pointer;text-decoration:none;font-size:.9rem}
.btn.ghost{background:#fff;color:var(--mid);border:1.5px solid var(--line)}
@media print{body{background:#fff;padding:0}.toolbar-print{display:none}.page{box-shadow:none;margin:0;border-radius:0}}
</style></head><body>

<div class="toolbar-print">
  <button class="btn" onclick="window.print()">🖨️ طباعة / حفظ PDF</button>
  <a class="btn ghost" href="index.php?page=courses">رجوع</a>
</div>

<div class="page">
  <div class="cover">
    <img src="assets/logo.png" alt="Nice Nail">
    <div class="eyebrow">Nice Nail Academy</div>
    <h1>كتيب <em>المتدربة</em></h1>
    <div class="sub"><?= h($course['name']??'دورة جل اكستنشن') ?></div>
    <div class="name-plate"><small>المتدربة</small><b><?= h($c['name']) ?></b></div>
  </div>
  <div class="body">
    <div class="meta">
      <div><small>المدربة</small><b><?= h($course['trainer']??'أسماء') ?></b></div>
      <div><small>المدة</small><b><?= h($course['duration']??'9 ساعات') ?></b></div>
      <div><small>موعد الدورة</small><b><?= $c['course_date']?h(ar_date($c['course_date'])):'يُحدّد' ?></b></div>
    </div>

    <h2>محاور <em>الدورة</em></h2>
    <?php foreach($topics as $i=>$t): ?>
      <div class="topic"><div class="n"><?= $i+1 ?></div><div><div class="tt"><?= h($t) ?></div></div></div>
    <?php endforeach; ?>

    <div class="cert">
      <div class="ic">🏅</div>
      <b>شهادة معتمدة</b>
      <?= h($course['certificate']??'معتمدة من الأكاديمية العالمية للتدريب والتطوير البريطانية') ?>
    </div>

    <div class="notes-area">
      <h2>ملاحظاتي</h2>
      <?php for($i=0;$i<6;$i++): ?><div class="notes-line"></div><?php endfor; ?>
    </div>
  </div>
  <footer>Nice Nail · <?= h($s['phone']??'') ?> · @<?= h($s['instagram']??'nicenails') ?></footer>
</div>
</body></html>
