<?php
require __DIR__.'/inc/bootstrap.php';require __DIR__.'/inc/ui.php';
if(!defined('DB_HOST')){header('Location: install.php');exit;}
set_exception_handler(function(Throwable $e){error_log('Kitchen Safety: '.$e);try{if(db()->inTransaction())db()->rollBack();}catch(Throwable $ignored){}fail(500,'The request could not be completed. Please try again or contact your manager.');});
$page=is_string($_GET['p']??'')?($_GET['p']??''):'';$error='';
if($page==='login'){header('Location: '.base_url('admin/index.php?p=login'));exit;}
if(!in_array($page,['','new','history','record','help','cooling'],true))fail(404,'Kitchen screen not found.');
require __DIR__.'/inc/actions.php';require __DIR__.'/inc/reports.php';
if($_SERVER['REQUEST_METHOD']==='POST'){try{handle_post();}catch(InvalidArgumentException $e){$error=$e->getMessage();}}
if(!sites()){head('No active kitchens','',true);echo '<section class="card"><h1>No active kitchens</h1><p>A manager needs to create or activate a kitchen in the admin panel.</p><a href="'.e(base_url('admin/')).'">Open admin</a></section>';foot(true);exit;}
if($page==='record'){$record=one('SELECT * FROM ks_records WHERE id=?',[(int)($_GET['id']??0)]);if(!$record)fail(404,'Record not found.');site_access((int)$record['site_id']);}
if($page==='history'&&isset($_GET['export']))fail(403,'Report exports are available in the protected admin panel.');
head([''=>'Start','new'=>'Record temperatures','history'=>'Date records','record'=>'Temperature record','help'=>'Kitchen guide','cooling'=>'Cooling records'][$page],$page);
if($error)echo '<div class="notice danger" role="alert">'.e($error).'</div>';
if($page==='')frontend_home();elseif($page==='cooling'){heading('FOOD COOLING','Cooling records','Start when cooking ends, then record the finish temperature, actual time and chilled-storage location.','<a class="btn primary" href="'.e(url('p=new&kind=rice')).'">Start rice cooling</a>');echo '<div class="report-actions"><a class="btn" href="'.e(url('p=new&kind=cool')).'">Start other food cooling</a><a class="btn" href="'.e(url('p=history&kind=rice')).'">Rice history</a><a class="btn" href="'.e(url('p=history&kind=cool')).'">Food cooling history</a></div><section class="card"><h2>In progress</h2>';records_table(rows("SELECT r.* FROM ks_records r WHERE site_id=? AND status='pending' AND NOT EXISTS(SELECT 1 FROM ks_events e WHERE e.record_id=r.id AND e.type IN ('cooling_complete','resolve')) ORDER BY observed_at",[site()['id']]));echo '</section>';}else{
 if($page==='history'){
  $date=is_string($_GET['from']??null)?$_GET['from']:local_time(now(),'Y-m-d');
  try{$d=new DateTimeImmutable($date);echo '<nav class="k-date-nav"><a class="btn" href="'.e(url('p=history&from='.$d->modify('-1 day')->format('Y-m-d').'&to='.$d->modify('-1 day')->format('Y-m-d'))).'">Previous day</a><a class="btn" href="'.e(url('p=history')).'">Today</a><a class="btn" href="'.e(url('p=history&from='.$d->modify('+1 day')->format('Y-m-d').'&to='.$d->modify('+1 day')->format('Y-m-d'))).'">Next day</a></nav>';}catch(Throwable $ignored){}
 }
 require __DIR__.'/inc/pages.php';
}
foot();
