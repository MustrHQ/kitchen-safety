<?php
declare(strict_types=1);
const APP_NAME='MustrHQ Kitchen Safety';
require_once dirname(__DIR__).'/version.php';
define('ROOT',dirname(__DIR__));
require_once __DIR__.'/domain.php';
function e(mixed $v): string {return htmlspecialchars((string)($v??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function public_mode():bool{return !defined('ADMIN_CONTEXT')||!ADMIN_CONTEXT;}
function base_path():string{$d=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/')),'/');if(str_ends_with($d,'/admin'))$d=substr($d,0,-6);return $d==='.'?'':$d;}
function base_url(string $path=''):string{return base_path().'/'.ltrim($path,'/');}
function url(string $query=''):string{return base_url((public_mode()?'':'admin/').'index.php'.($query!==''?'?'.$query:''));}
function go(string $query=''): never {header('Location: '.url($query),true,303);exit;}
function now(): string {return gmdate('Y-m-d H:i:s');}
function json(array $a): string {return json_encode($a,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);}
function arr(string $a): array {return json_decode($a,true,512,JSON_THROW_ON_ERROR);}
function fail(int $status,string $message): never {http_response_code($status); echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Kitchen Safety</title><body><h1>'.e($message).'</h1><p><a href="'.e(url()).'">Return to portal</a></p></body></html>';exit;}
header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: same-origin');header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'");
if(is_file(ROOT.'/config.php')) require ROOT.'/config.php';
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
session_name('mustr_safety');
session_set_cookie_params(['path'=>base_path().'/', 'httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off']);session_start();
if(!isset($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
function csrf(): string{return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">';}
function check_csrf():void{if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf']))fail(403,'Session expired. Reload the page and try again.');}
function db():PDO{static $p;if(!$p){$p=new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);$p->exec("SET time_zone = '+00:00'");}return $p;}
function rows(string $sql,array $params=[]):array{$s=db()->prepare($sql);$s->execute($params);return $s->fetchAll();}
function one(string $sql,array $params=[]):?array{return rows($sql,$params)[0]??null;}
function run(string $sql,array $params=[]):void{$s=db()->prepare($sql);$s->execute($params);}
function setting(string $k,string $default=''):string{return one('SELECT v FROM ks_settings WHERE k=?',[$k])['v']??$default;}
function set_setting(string $k,string $v):void{run('INSERT INTO ks_settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)',[$k,$v]);}
function user():?array{static $u=false;if($u===false){$u=isset($_SESSION['uid'])?one('SELECT * FROM ks_users WHERE id=? AND active=1',[$_SESSION['uid']]):null;if($u&&((int)($_SESSION['av']??0)!==(int)$u['auth_version'] || time()-(int)($_SESSION['last_seen']??0)>3600)){$u=null;unset($_SESSION['uid']);} if($u)$_SESSION['last_seen']=time();}return $u;}
function need_user():array{if(public_mode())return public_actor();$u=user();if(!$u)go('p=login');return $u;}
function manager():bool{if(public_mode())return false;return in_array(user()['role']??'',['admin','manager'],true);}
function need_manager():void{if(!manager())fail(403,'Manager access required.');}
function need_admin():void{if(public_mode())fail(403,'Administrator access required.');if((user()['role']??'')!=='admin')fail(403,'Administrator access required.');}
function sites():array{if(public_mode())return rows('SELECT * FROM ks_sites WHERE active=1 ORDER BY name');$u=need_user();return $u['role']==='admin'?rows('SELECT * FROM ks_sites ORDER BY active DESC,name'):rows('SELECT s.* FROM ks_sites s JOIN ks_user_sites a ON a.site_id=s.id WHERE a.user_id=? AND s.active=1 ORDER BY s.name',[$u['id']]);}
function site():array{$all=sites();foreach($all as $s)if((int)$s['id']===(int)($_SESSION['site_id']??0))return $s;if($all){$_SESSION['site_id']=$all[0]['id'];return $all[0];}fail(403,'No kitchen assigned. Ask your administrator to assign an active kitchen.');}
function site_access(int $id):array{foreach(sites() as $s)if((int)$s['id']===$id)return $s;fail(403,'This kitchen is not assigned to you.');}
function active_site():void{if(!(int)site()['active'])throw new InvalidArgumentException('This kitchen is archived. Restore it before adding records.');}
function audit(string $action,string $entity,array $data=[]):void{$u=public_mode()&&defined('DB_HOST')?public_actor():user();run('INSERT INTO ks_audit (user_id,actor,action,entity,data_json,created_at) VALUES (?,?,?,?,?,?)',[$u['id']??null,$u['name']??'Installer',$action,$entity,json($data),now()]);}
function flash(string $msg):void{$_SESSION['flash']=$msg;}
function text_input(string $key,int $max=200,bool $required=true):string{$v=$_POST[$key]??'';if(!is_string($v))throw new InvalidArgumentException('Invalid field.');$v=in_array($key,['password','current_password','db_pass'],true)?$v:trim($v);if(($required&&trim($v)==='')||mb_strlen($v)>$max)throw new InvalidArgumentException('Check '.str_replace('_',' ',$key).' (maximum '.$max.' characters).');return $v;}
function number_input(string $key,float $min=-60,float $max=150):float{$s=text_input($key,32);if(!is_numeric($s)||!is_finite((float)$s)||(float)$s<$min||(float)$s>$max)throw new InvalidArgumentException('Check '.str_replace('_',' ',$key).'.');return(float)$s;}
function local_time(string $utc,?string $format='d M Y, H:i',?string $zone=null):string{return (new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($zone??site()['timezone']))->format($format);}
function observed_input(string $key,string $zone):string{$dt=parse_local(text_input($key,40),$zone);$age=time()-$dt->getTimestamp();if($age < -60 || $age>7*86400)throw new InvalidArgumentException('Use a time within the last seven days and not in the future.');return $dt->format('Y-m-d H:i:s');}
function rules(int $id):array{$r=[];foreach(rows('SELECT * FROM ks_rules WHERE site_id=?',[$id]) as $x)$r[$x['code']]=arr($x['limits_json']);return $r;}
function events(int $record):array{return rows('SELECT * FROM ks_events WHERE record_id=? ORDER BY id',[$record]);}
function state(array $r,?array $events=null):array{
 $events??=events((int)$r['id']);$status=$r['status'];$closed=false;$completed=false;
 foreach($events as $ev){if($ev['type']==='recheck'&&arr($ev['data_json'])['status']==='fail')$status='fail';if($ev['type']==='cooling_complete'){$status=arr($ev['data_json'])['status'];$completed=true;}if($ev['type']==='resolve')$closed=true;}
 if($status==='pending'&&!$completed){$l=arr($r['limits_json']);if(time()>strtotime($r['observed_at'].' UTC')+(int)$l['max_minutes']*60)$status='overdue';}
 return ['status'=>$status,'closed'=>$closed,'completed'=>$completed,'needs_action'=>in_array($status,['warn','fail','overdue'],true)&&!$closed,'events'=>$events];
}
function scheduled_items(array $s,string $date):array{
 $out=[];$day=(int)(new DateTimeImmutable($date,new DateTimeZone($s['timezone'])))->format('N');
 foreach(rows('SELECT * FROM ks_schedules WHERE site_id=? AND active=1',[$s['id']]) as $x){if(!in_array($day,arr($x['days_json']),true))continue;
 foreach(arr($x['times_json']) as $t){$when=parse_local($date.'T'.$t,$s['timezone'])->format('Y-m-d H:i:s');if($when<$x['valid_from']||($x['valid_to']!==null&&$when>=$x['valid_to']))continue;
 $field=$x['asset_type']==='equipment'?'equipment_id':'task_id';$r=one('SELECT * FROM ks_records WHERE site_id=? AND '.$field.'=? AND scheduled_for=? ORDER BY recorded_at LIMIT 1',[$s['id'],$x['asset_id'],$when]);
 $out[]=['id'=>$x['asset_id'],'kind'=>$x['kind'],'name'=>$x['name'],'slot'=>$t,'utc'=>$when,'state'=>$r?'recorded':(strtotime($when.' UTC')<time()?'overdue':'upcoming'),'record'=>$r];}
 }usort($out,fn($a,$b)=>strcmp($a['utc'],$b['utc']));return $out;
}
function seed_rules(PDO $p,int $site):void{foreach(default_rules() as $code=>$limits){$p->prepare('INSERT INTO ks_rules (site_id,code,label,limits_json,updated_at) VALUES (?,?,?,?,?)')->execute([$site,$code,KINDS[$code],json($limits),now()]);}}

function public_actor():array{
 $id=(int)setting('public_actor_id');if(!$id)throw new RuntimeException('Public recorder is not configured.');
 return ['id'=>$id,'name'=>$_SESSION['recorder_name']??'Kitchen tablet','role'=>'kiosk','auth_version'=>0];
}
function identify_public_recorder():void{
 $name=text_input('recorded_by',80);$_SESSION['recorder_name']=$name;
}
function limit_public_writes():void{
 $bucket=hash_hmac('sha256','kiosk:'.($_SERVER['REMOTE_ADDR']??'unknown'),APP_SECRET);
 $count=(int)(one('SELECT COUNT(*) n FROM ks_login_attempts WHERE bucket=? AND created_at>?',[$bucket,gmdate('Y-m-d H:i:s',time()-600)])['n']??0);
 if($count>=300)fail(429,'Too many submissions. Wait a few minutes and try again.');
 run('INSERT INTO ks_login_attempts (bucket,created_at) VALUES (?,?)',[$bucket,now()]);
}
if(defined('DB_HOST')){
 require_once __DIR__.'/migrations.php';
 upgrade_database(db());
 if(is_file(ROOT.'/private/maintenance.json') && (!defined('UPDATER_CONTEXT')||!UPDATER_CONTEXT))fail(503,'An update is in progress. Please return in a moment.');
}
