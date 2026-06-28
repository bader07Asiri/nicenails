<?php
$convs = conv_list();
$sel = normalize_phone($_GET['phone'] ?? '');
if ($sel) conv_mark_read($sel);
$thread = $sel ? msg_thread($sel) : [];
$selConv = null;
foreach ($convs as $c) if ($c['phone']===$sel) { $selConv=$c; break; }
$selName = $selConv['name'] ?? '';
$selMode = $sel ? conv_get_mode($sel) : 'bot';
$waActive = !empty(settings_get()['whatsapp']['enabled']);
?>
<?php if(!$waActive): ?>
<div class="flash err" style="margin-bottom:1rem">⚠️ واتساب غير مفعّل بعد — فعّليه من الإعدادات لاستقبال وإرسال الرسائل فعلياً. (المحادثات تظهر هنا بمجرد ربط الـ API.)</div>
<?php endif; ?>

<div class="inbox <?= $sel?'has-sel':'' ?>">
  <!-- قائمة المحادثات -->
  <div class="inbox-list">
    <div class="inbox-list-h">
      <b>المحادثات</b>
      <span class="muted" style="font-size:.75rem"><?= count($convs) ?></span>
    </div>
    <div class="inbox-search"><input type="text" placeholder="🔍 بحث..." oninput="filterConvs(this.value)"></div>
    <div id="convScroll" style="overflow-y:auto;flex:1">
      <?php if(!$convs): ?>
        <div class="empty" style="padding:2rem 1rem"><div class="ei">💬</div>لا توجد محادثات بعد</div>
      <?php else: foreach($convs as $c): $active=$c['phone']===$sel; ?>
        <a class="conv-item <?= $active?'active':'' ?>" data-search="<?= h(mb_strtolower(($c['name']??'').' '.$c['phone'])) ?>" href="index.php?page=inbox&phone=<?= h($c['phone']) ?>">
          <div class="conv-av"><?= h(mb_substr($c['name']?:'؟',0,1)) ?></div>
          <div style="flex:1;min-width:0">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:6px">
              <b style="font-size:.88rem"><?= h($c['name']?:$c['phone']) ?></b>
              <span class="muted" style="font-size:.66rem"><?= h(substr($c['last_ts'],11,5)) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:6px">
              <span class="conv-last"><?= h(mb_substr($c['last'],0,32)) ?></span>
              <?php if($c['unread']>0): ?><span class="conv-badge"><?= $c['unread'] ?></span><?php endif; ?>
            </div>
          </div>
          <?php if(($c['mode']??'bot')==='human'): ?><span class="conv-mode" title="رد يدوي">✋</span><?php else: ?><span class="conv-mode bot" title="رد آلي">🤖</span><?php endif; ?>
        </a>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- سلسلة المحادثة -->
  <div class="inbox-thread">
    <?php if(!$sel): ?>
      <div class="empty" style="margin:auto"><div class="ei">💬</div>اختاري محادثة لعرضها</div>
    <?php else: ?>
      <div class="thread-h">
        <div style="display:flex;align-items:center;gap:.5rem">
          <a class="btn btn-sm btn-ghost mob-back" href="index.php?page=inbox" style="display:none">‹</a>
          <div>
          <b><?= h($selName?:$sel) ?></b>
          <div class="muted" style="font-size:.74rem" dir="ltr"><?= h($sel) ?></div>
          </div>
        </div>
        <div class="t-actions">
          <?php if($selMode==='human'): ?>
            <span class="tag t-confirmed">✋ رد يدوي</span>
            <a class="btn btn-sm btn-ghost" href="actions.php?do=wa_set_mode&phone=<?= h($sel) ?>&mode=bot&return=<?= urlencode("index.php?page=inbox&phone=$sel") ?>">↩️ تسليم للبوت</a>
          <?php else: ?>
            <span class="tag t-client">🤖 رد آلي</span>
            <a class="btn btn-sm btn-primary" href="actions.php?do=wa_set_mode&phone=<?= h($sel) ?>&mode=human&return=<?= urlencode("index.php?page=inbox&phone=$sel") ?>">✋ استلام يدوي</a>
          <?php endif; ?>
          <a class="btn btn-sm btn-ghost" href="index.php?page=customers">العميلة</a>
        </div>
      </div>

      <div class="thread-body" id="threadBody">
        <?php if(!$thread): ?>
          <div class="empty" style="margin:auto">لا توجد رسائل بعد</div>
        <?php else: $lastDay=''; foreach($thread as $m): $day=substr($m['ts'],0,10); ?>
          <?php if($day!==$lastDay){ $lastDay=$day; ?><div class="thread-day"><?= ar_date($day) ?></div><?php } ?>
          <div class="bubble <?= ($m['dir']==='out')?'out':'in' ?>">
            <?= nl2br(h($m['text'])) ?>
            <span class="bubble-meta"><?= h(substr($m['ts'],11,5)) ?><?= ($m['via']??'')==='bot'?' · بوت':'' ?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <form class="thread-reply" method="post" action="actions.php">
        <input type="hidden" name="do" value="wa_reply">
        <input type="hidden" name="phone" value="<?= h($sel) ?>">
        <input type="hidden" name="return" value="index.php?page=inbox&phone=<?= h($sel) ?>">
        <textarea name="text" id="replyBox" rows="1" placeholder="اكتبي ردّك..." required oninput="autoGrow(this)"></textarea>
        <button class="btn btn-primary" type="submit" title="إرسال">➤</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
function filterConvs(q){q=q.toLowerCase();document.querySelectorAll('.conv-item').forEach(function(el){el.style.display=(el.getAttribute('data-search')||'').indexOf(q)>-1?'':'none';});}
function autoGrow(t){t.style.height='auto';t.style.height=Math.min(120,t.scrollHeight)+'px';}
// مرّر لأسفل السلسلة
var tb=document.getElementById('threadBody'); if(tb) tb.scrollTop=tb.scrollHeight;
// تحديث تلقائي كل 15ث (إلا إذا تكتبين)
setInterval(function(){
  var r=document.getElementById('replyBox');
  if(document.hidden) return;
  if(r && (r.value.trim()!=='' || document.activeElement===r)) return;
  location.reload();
},15000);
</script>
