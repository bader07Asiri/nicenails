<?php
// ═══════════════════════════════════════════════════════════
//  سلة المهملات — العناصر المحذوفة: استرجاع أو حذف نهائي
// ═══════════════════════════════════════════════════════════
$trash = array_reverse(db_load('trash'));
$typeNames = [
  'orders'=>'طلب','customers'=>'عميلة','services'=>'خدمة',
  'appointments'=>'موعد','courses'=>'متدربة','payments'=>'دفعة',
];
$ret = urlencode('index.php?page=trash');
?>
<div class="toolbar">
  <div class="muted" style="max-width:560px;line-height:1.7">
    🛟 أي عنصر يُحذف من النظام يجي هنا ويختفي من كل الصفحات (الطلبات، العملاء، القوائم المالية…).
    تقدرين تسترجعينه في أي وقت. <b style="color:var(--red)">تفريغ السلة = حذف نهائي لا يمكن التراجع عنه.</b>
  </div>
  <?php if($trash): ?>
    <a class="btn btn-danger" href="actions.php?do=trash_empty&return=<?= $ret ?>"
       onclick="return confirm('⚠️ تحذير: تفريغ سلة المهملات يحذف كل العناصر (<?= count($trash) ?>) نهائياً ولا يمكن استرجاعها إطلاقاً.\n\nمتأكدة؟')">🗑️ تفريغ السلة نهائياً</a>
  <?php endif; ?>
</div>

<?php if(!$trash): ?>
  <div class="card"><div class="empty"><div class="ei">🗑️</div>سلة المهملات فارغة</div></div>
<?php else: ?>
<div class="card">
  <div class="card-h"><h3>العناصر المحذوفة</h3><span class="muted"><?= count($trash) ?> عنصر</span></div>
  <div class="tbl-wrap"><table>
    <thead><tr><th>النوع</th><th>العنصر</th><th>تاريخ الحذف</th><th style="text-align:left">إجراء</th></tr></thead>
    <tbody>
    <?php foreach($trash as $t): ?>
      <tr>
        <td><span class="tag t-visitor"><?= h($typeNames[$t['type']]??$t['type']) ?></span></td>
        <td>
          <b><?= h($t['label']) ?></b>
          <?php if(!empty($t['related'])): ?>
            <div class="muted" style="font-size:.72rem">يشمل <?= count($t['related']) ?> عنصر مرتبط (مدفوعات/مواعيد)</div>
          <?php endif; ?>
        </td>
        <td class="muted" style="white-space:nowrap"><?= h(substr($t['deleted_at'],0,16)) ?></td>
        <td style="white-space:nowrap;text-align:left">
          <a class="btn btn-sm btn-success" href="actions.php?do=trash_restore&id=<?= h($t['id']) ?>&return=<?= $ret ?>">↩️ استرجاع</a>
          <a class="btn btn-sm btn-danger" href="actions.php?do=trash_purge&id=<?= h($t['id']) ?>&return=<?= $ret ?>"
             onclick="return confirm('حذف هذا العنصر نهائياً بلا رجعة؟')">حذف نهائي</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
