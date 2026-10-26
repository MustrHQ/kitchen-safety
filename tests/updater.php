<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/version.php';require dirname(__DIR__).'/inc/domain.php';require dirname(__DIR__).'/inc/updater.php';
function now():string{return gmdate('Y-m-d H:i:s');}
function json(array $x):string{return json_encode($x,JSON_THROW_ON_ERROR);}
function arr(string $x):array{return json_decode($x,true,64,JSON_THROW_ON_ERROR);}
function test_assert(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}
$root=sys_get_temp_dir().'/ks-update-tests-'.bin2hex(random_bytes(6));mkdir($root,0700);define('ROOT',$root);$_SESSION=[];$n=0;
try{
 foreach(['../config.php','/etc/passwd','config.php','private/backup.zip','assets/../../config.php','assets\\bad.php','assets//bad.php','.env','inc/./bad.php'] as $path){test_assert(!update_path_allowed($path),'Unsafe path accepted: '.$path);$n++;}
 foreach(['index.php','admin/index.php','assets/app.css','inc/.htaccess','migrations/003.json'] as $path){test_assert(update_path_allowed($path),'Safe path rejected: '.$path);$n++;}
 $r=['tag_name'=>'v1.2.0','draft'=>false,'prerelease'=>false,'published_at'=>'2026-10-26T12:00:00Z','body'=>'Example release','assets'=>[['name'=>'mustrhq-kitchen-safety-1.2.0.zip','digest'=>'sha256:'.str_repeat('a',64),'browser_download_url'=>'https://github.com/'.APP_REPO.'/releases/download/v1.2.0/mustrhq-kitchen-safety-1.2.0.zip']]];
 $parsed=release_details($r);test_assert($parsed['version']==='1.2.0'&&$parsed['sha256']===str_repeat('a',64),'Release parse failed');$n++;
 foreach(['digest','browser_download_url'] as $key){$bad=$r;$bad['assets'][0][$key]='https://evil.invalid/';try{release_details($bad);throw new RuntimeException('Unsafe GitHub metadata accepted');}catch(InvalidArgumentException $e){$n++;}}
 $bad=$r;$bad['prerelease']=true;try{release_details($bad);throw new RuntimeException('Prerelease accepted');}catch(InvalidArgumentException $e){$n++;}
 $files=[];foreach(['index.php','admin/index.php','inc/bootstrap.php','inc/updater.php','assets/app.css','assets/pwa.js','sw.js'] as $path)$files[$path]='/* release fixture */';$files['version.php']="<?php const APP_VERSION='1.2.0';";
 $manifest=['app_id'=>APP_ID,'version'=>'1.2.0','schema'=>2,'min_php'=>'8.1.0','files'=>array_map(fn($s)=>hash('sha256',$s),$files),'remove'=>[]];
 $z=new ZipArchive();$zip=$root.'/test.zip';$z->open($zip,ZipArchive::CREATE);foreach($files as $path=>$data)$z->addFromString('package/'.$path,$data);$z->addFromString('package/release.json',json($manifest));$z->close();
 $plan=stage_update($zip,'CLI test');test_assert($plan['version']==='1.2.0','Valid archive did not stage');$n++;
 $manifest['files']['index.php']=str_repeat('0',64);$z->open($zip,ZipArchive::OVERWRITE);foreach($files as $path=>$data)$z->addFromString($path,$data);$z->addFromString('release.json',json($manifest));$z->close();
 try{stage_update($zip,'CLI test');throw new RuntimeException('Bad hash accepted');}catch(InvalidArgumentException $e){$n++;}
 echo $n." updater assertions passed.\n";
}finally{remove_job_tree($root);}
