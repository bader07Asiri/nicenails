// ═══════════════════════════════════════════════════════════
//  Nice Nail — app.js | تفاعلات الواجهة (مودال، فلاتر، بحث)
// ═══════════════════════════════════════════════════════════
function openModal(id){var m=document.getElementById(id);if(m)m.classList.add('show');}
function closeModal(id){var m=document.getElementById(id);if(m)m.classList.remove('show');}
// إغلاق المودال عند الضغط على الخلفية
document.addEventListener('click',function(e){
  if(e.target.classList&&e.target.classList.contains('modal-bg'))e.target.classList.remove('show');
});
document.addEventListener('keydown',function(e){
  if(e.key==='Escape')document.querySelectorAll('.modal-bg.show').forEach(function(m){m.classList.remove('show');});
});

// بحث فوري داخل الجداول (data-search على الـ input، يبحث في tbody)
function liveSearch(input,tableId){
  var q=input.value.trim().toLowerCase();
  var rows=document.querySelectorAll('#'+tableId+' tbody tr');
  rows.forEach(function(r){
    if(r.classList.contains('empty-row'))return;
    r.style.display=r.innerText.toLowerCase().indexOf(q)>-1?'':'none';
  });
}

// نسخ نص (روابط الدفع مثلاً)
function copyText(t,btn){
  navigator.clipboard.writeText(t).then(function(){
    if(btn){var o=btn.innerText;btn.innerText='✓ نُسخ';setTimeout(function(){btn.innerText=o;},1400);}
  });
}

// تأكيد الحذف
function confirmDel(msg){return confirm(msg||'هل أنتِ متأكدة من الحذف؟ لا يمكن التراجع.');}

// حساب المتبقي تلقائياً في نموذج الطلب
function calcRemaining(){
  var t=parseFloat((document.getElementById('o_total')||{}).value)||0;
  var d=parseFloat((document.getElementById('o_deposit')||{}).value)||0;
  var el=document.getElementById('o_remaining');
  if(el)el.innerText=Math.max(0,t-d).toLocaleString();
}

// تغيير نوع الدفع حسب الخدمة المختارة في نموذج الطلب
function onServiceChange(sel){
  var opt=sel.options[sel.selectedIndex];
  var price=opt.getAttribute('data-price')||'';
  var dep=opt.getAttribute('data-deposit')||'0';
  var pt=opt.getAttribute('data-ptype')||'full';
  var t=document.getElementById('o_total');if(t&&price)t.value=price;
  var d=document.getElementById('o_deposit');
  if(d){d.value=(pt==='deposit')?dep:(pt==='free'?0:'');}
  calcRemaining();
}
