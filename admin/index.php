<?php
define('ADMIN_CONTEXT',true);define('UPDATER_CONTEXT',in_array($_GET['p']??'',['updates','login'],true));
require dirname(__DIR__).'/inc/bootstrap.php';
require dirname(__DIR__).'/inc/ui.php';
if(!defined('DB_HOST')){header('Location: ../install.php');exit;}
set_exception_handler(function(Throwable $e){error_log('Kitchen Safety: '.$e);try{if(db()->inTransaction())db()->rollBack();}catch(Throwable $ignored){}fail(500,'The request could not be completed. Please try again or contact your administrator.');});
$page=is_string($_GET['p']??'')?($_GET['p']??''):'';$error='';
if($page==='login'){
 if(user())go();
 if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf();$email=strtolower(trim(is_string($_POST['email']??null)?$_POST['email']:''));$pass=is_string($_POST['password']??null)?$_POST['password']:'';
  $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');$buckets=[hash_hmac('sha256','ip:'.$ip,APP_SECRET),hash_hmac('sha256','email:'.$email,APP_SECRET)];$limited=false;
  foreach($buckets as $b){$n=one('SELECT COUNT(*) n FROM ks_login_attempts WHERE bucket=? AND created_at>?',[$b,gmdate('Y-m-d H:i:s',time()-900)]);if((int)$n['n']>=10)$limited=true;}
  $u=one('SELECT * FROM ks_users WHERE email=? AND active=1',[$email]);$valid=password_verify($pass,$u['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
  if(!$limited&&$valid&&$u){session_regenerate_id(true);$_SESSION['uid']=$u['id'];$_SESSION['av']=$u['auth_version'];$_SESSION['last_seen']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));run('INSERT INTO ks_audit (user_id,actor,action,entity,data_json,created_at) VALUES (?,?,?,?,?,?)',[$u['id'],$u['name'],'auth.login','user:'.$u['id'],'{}',now()]);run('DELETE FROM ks_login_attempts WHERE created_at<?',[gmdate('Y-m-d H:i:s',time()-86400)]);go();}
  foreach($buckets as $b)run('INSERT INTO ks_login_attempts (bucket,created_at) VALUES (?,?)',[$b,now()]);$error=$limited?'Too many sign-in attempts. Wait 15 minutes and try again.':'Email or password not recognised.';
 }
 head('Sign in','',true);
 echo '<a class="brand auth-brand" href="'.e(url()).'"><span class="brand-mark">'.icon('thermo').'</span><span>MustrHQ<small>KITCHEN SAFETY</small></span></a><div class="auth-card"><p class="eyebrow">YOUR KITCHEN, UNDER CONTROL</p><h1>Welcome back.</h1><p class="muted">Sign in to record checks and keep every shift on track.</p>';
 if($error)echo '<div class="notice danger" role="alert">'.e($error).'</div>';
 echo '<form method="post">'.csrf();input('email','Email address','','email','required autocomplete="username" maxlength="190"');input('password','Password','','password','required autocomplete="current-password"');button('Sign in');echo '</form><p class="help">Need access or a password reset? Contact your kitchen administrator.</p></div><p class="auth-foot">Temperature monitoring and corrective-action records.</p>';foot(true);exit;
}
need_user();require dirname(__DIR__).'/inc/actions.php';require dirname(__DIR__).'/inc/updater.php';
if($_SERVER['REQUEST_METHOD']==='POST'){try{if(str_starts_with((string)($_POST['action']??''),'update_'))handle_update_post();else handle_post();}catch(InvalidArgumentException $e){$error=$e->getMessage();}}
if(!sites()){head('Kitchen access','',true);echo '<section class="auth-card"><h1>No kitchen assigned.</h1><p>Ask your administrator to assign an active kitchen to your account.</p>';form_start('logout');button('Sign out');echo '</form></section>';foot(true);exit;}
$allowed=['','new','record','alerts','history','equipment','limits','sites','users','audit','help','account','updates'];if(!in_array($page,$allowed,true))fail(404,'Page not found.');
if(in_array($page,['equipment','limits'],true))need_manager();if(in_array($page,['sites','users','audit','updates'],true))need_admin();
if($page==='record'){$record=one('SELECT * FROM ks_records WHERE id=?',[(int)($_GET['id']??0)]);if(!$record)fail(404,'Record not found.');site_access((int)$record['site_id']);}
require dirname(__DIR__).'/inc/reports.php';
if($page==='history'&&isset($_GET['export'])){export_report();exit;}
head([''=>'Overview','new'=>'Record a check','record'=>'Check details','alerts'=>'Needs attention','history'=>'History & reports','equipment'=>'Equipment & checks','limits'=>'Controls & limits','sites'=>'Kitchens','users'=>'Team','audit'=>'Audit trail','help'=>'Kitchen guide','account'=>'Your account','updates'=>'Updates'][$page],$page);
if($error)echo '<div class="notice danger" role="alert">'.e($error).'</div>';
if($page==='updates')require dirname(__DIR__).'/inc/update-page.php';else require dirname(__DIR__).'/inc/pages.php';foot();
