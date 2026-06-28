<?php
// ═══════════════════════════════════════════════════════════
//  Nice Nail — bot.php  |  محرّك بوت الواتساب (منطق الردود)
//  دالة نقية قابلة للاختبار: تأخذ الرسالة وحالة المستخدم وترجع الرد
// ═══════════════════════════════════════════════════════════
require_once __DIR__ . '/lib.php';

// حالة المحادثات لكل رقم (data/wa_sessions.json)
function wa_state_get($phone){
    $all = db_load('wa_sessions');
    foreach ($all as $s) if (($s['phone']??'')===$phone) return $s;
    return ['phone'=>$phone,'step'=>'menu','data'=>[]];
}
function wa_state_set($phone,$step,$data=[]){
    $all = db_load('wa_sessions'); $found=false;
    foreach ($all as &$s){ if(($s['phone']??'')===$phone){ $s['step']=$step;$s['data']=$data;$s['updated']=now_str();$found=true; } }
    unset($s);
    if(!$found) $all[]=['phone'=>$phone,'step'=>$step,'data'=>$data,'updated'=>now_str()];
    db_save('wa_sessions',$all);
}

// توليد المواعيد المتاحة للأسبوعين القادمين من التقويم
function wa_available_slots($maxPerDay=4){
    $appts = db_load('appointments');
    $busy=[];
    foreach($appts as $a){ if(($a['status']??'')!=='cancelled'){ $d=$a['date']??''; $busy[$d]=($busy[$d]??0)+1; } }
    $slots=[]; $arMonths=['','يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
    $days=['Sunday'=>'الأحد','Monday'=>'الإثنين','Tuesday'=>'الثلاثاء','Wednesday'=>'الأربعاء','Thursday'=>'الخميس','Friday'=>'الجمعة','Saturday'=>'السبت'];
    for($i=1;$i<=14 && count($slots)<8;$i++){
        $ts=strtotime("+$i days"); $dow=date('N',$ts); // 5=Fri,6=Sat off
        if($dow==5||$dow==6) continue;                  // أيام العمل: الأحد-الخميس
        $d=date('Y-m-d',$ts);
        if(($busy[$d]??0)>=$maxPerDay) continue;        // اليوم ممتلئ
        $slots[]=$days[date('l',$ts)].' '.date('j',$ts).' '.$arMonths[(int)date('n',$ts)];
    }
    return $slots;
}

// تطبيع إدخال المستخدم
function wa_norm($t){
    $t=trim($t);
    $map=['١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','٠'=>'0'];
    return strtr($t,$map);
}

// المحرّك الرئيسي: يرجّع نص الرد ويحدّث الحالة
function bot_reply($phone, $text, $settings=null){
    if($settings===null) $settings=settings_get();
    $GLOBALS['bot_handoff']=false;
    $bot=$settings['bot']??[];
    $t=wa_norm($text);
    $low=mb_strtolower($t);
    $state=wa_state_get($phone);
    $step=$state['step']??'menu';
    $hours=$settings['working_hours']??'الأحد - الخميس: 10 ص - 9 م';

    // كلمات مفتاحية تُرجع للقائمة في أي وقت
    if(in_array($low,['القائمة','الرئيسية','رجوع','menu','start','ابدأ','مرحبا','السلام عليكم','هلا'])){
        wa_state_set($phone,'menu'); return $bot['greeting']??'مرحباً!';
    }

    switch($step){
        // ── القائمة الرئيسية ─────────────────────────────────
        case 'menu':
        default:
            if($t==='1'||mb_strpos($low,'حجز')!==false){
                $slots=wa_available_slots();
                if(!$slots){ wa_state_set($phone,'menu'); return "لا توجد مواعيد متاحة حالياً، تواصلي معنا مباشرة 🌸"; }
                $list=''; foreach($slots as $i=>$s){ $list.=($i+1).". $s\n"; }
                wa_state_set($phone,'booking_slot',['slots'=>$slots]);
                return strtr($bot['booking_intro']??"المواعيد المتاحة:\n{slots}",['{slots}'=>trim($list)]);
            }
            if($t==='2'||mb_strpos($low,'استفسار')!==false){
                $inq=$settings['inquiries']??[]; $list='';
                foreach($inq as $i=>$q){ $list.=($i+1).'. '.$q['q']."\n"; }
                $list.=(count($inq)+1).". رجوع للقائمة الرئيسية";
                wa_state_set($phone,'inquiry_menu');
                return "اختاري استفسارك:\n".$list;
            }
            if($t==='3'||mb_strpos($low,'اسعار')!==false||mb_strpos($low,'أسعار')!==false||mb_strpos($low,'نماذج')!==false){
                wa_state_set($phone,'menu');
                $services=db_load('services'); $sl='';
                foreach($services as $s){ if($s['active']??true){ $sl.='• '.$s['name'].' — '.($s['payment_type']==='deposit'?'عربون '.money($s['deposit']??200).' + الباقي':money($s['price']).' ريال')."\n"; } }
                $ig=$settings['instagram']??'nicenails';
                return strtr($bot['samples']??"خدماتنا:\n{services}",['{services}'=>trim($sl),'{instagram}'=>'instagram.com/'.$ig]);
            }
            if($t==='4'||mb_strpos($low,'اسئلة')!==false||mb_strpos($low,'أسئلة')!==false){
                $faq=$settings['faq']??[]; $list='';
                foreach($faq as $i=>$q){ $list.=($i+1).'. '.$q['q']."\n"; }
                $list.=(count($faq)+1).". رجوع للقائمة الرئيسية";
                wa_state_set($phone,'faq_menu');
                return "اختاري سؤالك:\n".$list;
            }
            if($t==='5'||mb_strpos($low,'الأخصائية')!==false||mb_strpos($low,'تواصل')!==false){
                wa_state_set($phone,'human');
                $GLOBALS['bot_handoff']=true;
                return strtr($bot['to_human']??'سيتم تحويلك للأخصائية. {hours}',['{hours}'=>$hours]);
            }
            if($t==='6'||mb_strpos($low,'دورة')!==false||mb_strpos($low,'تدريب')!==false){
                wa_state_set($phone,'menu');
                return strtr($bot['course_info']??'دورة جل اكستنشن',['{link}'=>bot_site_link('courses')]);
            }
            // غير مفهوم → القائمة
            wa_state_set($phone,'menu');
            return $bot['greeting']??'مرحباً!';

        // ── اختيار الموعد ───────────────────────────────────
        case 'booking_slot':
            $slots=$state['data']['slots']??[];
            $idx=(int)$t-1;
            if($idx>=0 && $idx<count($slots)){
                wa_state_set($phone,'booking_service',['slot'=>$slots[$idx]]);
                return $bot['booking_service_q']??"اختاري نوع الخدمة:\nأ) بناء جديد\nب) فيلر\nج) إزالة\nد) نيل آرت\nهـ) إزالة+بناء";
            }
            return "اكتبي رقم الموعد من القائمة، أو اكتبي «رجوع» للقائمة.";

        // ── اختيار الخدمة ───────────────────────────────────
        case 'booking_service':
            $svcMap=['ا'=>'بناء جل جديد','أ'=>'بناء جل جديد','a'=>'بناء جل جديد','1'=>'بناء جل جديد',
                     'ب'=>'تعديل (فيلر)','b'=>'تعديل (فيلر)','2'=>'تعديل (فيلر)',
                     'ج'=>'إزالة','c'=>'إزالة','3'=>'إزالة',
                     'د'=>'نيل آرت','d'=>'نيل آرت','4'=>'نيل آرت',
                     'ه'=>'إزالة + بناء جديد','هـ'=>'إزالة + بناء جديد','e'=>'إزالة + بناء جديد','5'=>'إزالة + بناء جديد'];
            $svc=$svcMap[$low]??$svcMap[$t]??null;
            if($svc){
                $slot=$state['data']['slot']??'';
                wa_state_set($phone,'menu');
                $link=bot_site_link('orders');
                $msg=strtr($bot['booking_link']??'أكملي طلبك: {link}',['{link}'=>$link]);
                return "تم اختيار: $svc 💅\nالموعد: $slot\n\n".$msg."\n\n(ستتواصل معكِ الأخصائية لتأكيد التفاصيل)";
            }
            return "اختاري حرف الخدمة (أ/ب/ج/د/هـ) أو اكتبي «رجوع».";

        // ── قائمة الاستفسارات ───────────────────────────────
        case 'inquiry_menu':
            $inq=$settings['inquiries']??[];
            $idx=(int)$t-1;
            if($idx===count($inq)){ wa_state_set($phone,'menu'); return $bot['greeting']??'مرحباً!'; }
            if(isset($inq[$idx])){ return $inq[$idx]['a']."\n\nاكتبي رقم آخر أو «رجوع» للقائمة."; }
            return "اكتبي رقم الاستفسار أو «رجوع».";

        // ── قائمة الأسئلة الشائعة ───────────────────────────
        case 'faq_menu':
            $faq=$settings['faq']??[];
            $idx=(int)$t-1;
            if($idx===count($faq)){ wa_state_set($phone,'menu'); return $bot['greeting']??'مرحباً!'; }
            if(isset($faq[$idx])){ return $faq[$idx]['a']."\n\nاكتبي رقم آخر أو «رجوع» للقائمة."; }
            return "اكتبي رقم السؤال أو «رجوع».";

        // ── تحويل بشري ──────────────────────────────────────
        case 'human':
            wa_state_set($phone,'menu');
            return "شكراً لكِ 🌸 لو حابة ترجعي للقائمة اكتبي «القائمة».";
    }
}

function bot_site_link($page){
    $s=settings_get();
    $base=$s['site_url']??'';
    if($base) return rtrim($base,'/').'/system/index.php?page='.$page;
    return '[رابط الموقع]';
}
