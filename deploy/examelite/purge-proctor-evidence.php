<?php
// Maintenance CLI only; install/run as the ExamElite OS user, never through a web route.
if(PHP_SAPI!=='cli')exit(1);
require '/home/examelite/public_html/vendor/autoload.php';
$app=require '/home/examelite/public_html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$total=0;
for($batch=0;$batch<20;$batch++){
 $deleted=app(\App\Services\Tech4LearnProctorEvidence::class)->purgeExpired();$total+=$deleted;
 if($deleted<500)break;
}
echo 'Expired private proctor captures deleted: '.$total.PHP_EOL;
