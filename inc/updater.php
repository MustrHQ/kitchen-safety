<?php
declare(strict_types=1);
const UPDATE_MAX_BYTES=33554432;
function update_private():string{
 $p=ROOT.'/private';if(is_link($p))throw new InvalidArgumentException('Private update directory must not be a symlink.');
 if(!is_dir($p)&&!mkdir($p,0700,true))throw new InvalidArgumentException('The private directory is not writable.');
 if(!is_file($p.'/.htaccess')){if(file_put_contents($p.'/.htaccess',"Require all denied\n")===false)throw new InvalidArgumentException('Could not protect the private directory.');}if(!is_file($p.'/index.php'))file_put_contents($p.'/index.php',"<?php http_response_code(404);exit;\n");
 if(!is_writable($p))throw new InvalidArgumentException('Allow PHP to write to the private directory.');return $p;
}
function remove_job_tree(string $dir):void{
 if(!is_dir($dir)||is_link($dir))return;
 foreach(new FilesystemIterator($dir) as $x){if($x->isDir()&&!$x->isLink())remove_job_tree($x->getPathname());else unlink($x->getPathname());}rmdir($dir);
}
function update_path_allowed(string $path):bool{
 if(strlen($path)>240||!preg_match('~^[A-Za-z0-9_./-]+$~',$path)||str_contains($path,'\\')||str_starts_with($path,'/')||str_contains($path,'//'))return false;
 foreach(explode('/',$path) as $part)if($part===''||$part==='.'||$part==='..')return false;
 if(in_array($path,['config.php','.env'],true)||preg_match('~(^|/)(private|uploads|backups|\.git)(/|$)~i',$path))return false;
 if(preg_match('~^(admin|inc|assets|docs|tests|migrations)/~',$path))return true;
 return in_array($path,['index.php','install.php','version.php','manifest.php','sw.js','offline.html','release.json','README.md','LICENSE','NOTICE','CHANGELOG.md','SECURITY.md','CONTRIBUTING.md','CODE_OF_CONDUCT.md','.htaccess','.gitignore'],true);
}
function update_manifest(array $m):void{
 if(($m['app_id']??'')!==APP_ID||!preg_match('/^\d+\.\d+\.\d+$/',(string)($m['version']??'')))throw new InvalidArgumentException('This is not a Kitchen Safety release package.');
 if(!version_compare($m['version'],APP_VERSION,'>'))throw new InvalidArgumentException('The package must be newer than the installed version.');
 if(!version_compare(PHP_VERSION,(string)($m['min_php']??'8.1'),'>='))throw new InvalidArgumentException('This release needs PHP '.$m['min_php'].' or newer.');
 if(!isset($m['schema'])||!is_int($m['schema'])||$m['schema']<DB_SCHEMA_VERSION||$m['schema']>1000)throw new InvalidArgumentException('Incompatible database schema.');
 if(!isset($m['files'])||!is_array($m['files'])||count($m['files'])<8||count($m['files'])>1500)throw new InvalidArgumentException('The release file manifest is invalid.');
 foreach(['index.php','admin/index.php','inc/bootstrap.php','inc/updater.php','version.php','assets/app.css','assets/pwa.js','sw.js'] as $required)if(!isset($m['files'][$required]))throw new InvalidArgumentException('The package is missing '.$required.'.');
 foreach($m['files'] as $path=>$hash)if(!is_string($path)||!update_path_allowed($path)||$path==='release.json'||!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/',$hash))throw new InvalidArgumentException('A release path or checksum is invalid.');
 if(!is_array($m['remove']??[]))throw new InvalidArgumentException('Invalid removal list.');
 foreach($m['remove']??[] as $path)if(!is_string($path)||!update_path_allowed($path)||isset($m['files'][$path])||in_array($path,['version.php','release.json'],true))throw new InvalidArgumentException('Invalid obsolete-file path.');
}
function stage_update(string $zipFile,string $source,?string $expected=null):array{
 if(!class_exists('ZipArchive'))throw new InvalidArgumentException('Enable the PHP zip extension first.');
 if(filesize($zipFile)>UPDATE_MAX_BYTES)throw new InvalidArgumentException('Update ZIPs must be under 32 MiB.');
 $zip=new ZipArchive();if($zip->open($zipFile)!==true)throw new InvalidArgumentException('The uploaded file is not a readable ZIP.');
 $job=bin2hex(random_bytes(16));$dir=update_private().'/job-'.$job;mkdir($dir,0700);$entries=[];$seen=[];$total=0;
 try{
  if($zip->numFiles>2000)throw new InvalidArgumentException('Too many entries in this ZIP.');
  $prefix=null;for($i=0;$i<$zip->numFiles;$i++){ $name=$zip->getNameIndex($i);if($name==='release.json'){$prefix='';break;}if(str_ends_with($name,'/release.json')&&substr_count($name,'/')===1){if($prefix!==null)throw new InvalidArgumentException('Multiple release manifests.');$prefix=substr($name,0,-12);}}
  if($prefix===null)throw new InvalidArgumentException('This ZIP has no release.json manifest. Use a built release ZIP.');
  for($i=0;$i<$zip->numFiles;$i++){
   $stat=$zip->statIndex($i);$raw=$stat['name'];$zip->getExternalAttributesIndex($i,$opsys,$attr);if((($attr>>16)&0170000)===0120000)throw new InvalidArgumentException('Symlinks are not allowed.');
   if(str_ends_with($raw,'/'))continue;
   if($prefix!==''&&!str_starts_with($raw,$prefix))throw new InvalidArgumentException('ZIP entries have mixed roots.');
   $path=substr($raw,strlen($prefix));if(!update_path_allowed($path))throw new InvalidArgumentException('Disallowed update path: '.$path);
   $lower=strtolower($path);if(isset($seen[$lower]))throw new InvalidArgumentException('Duplicate ZIP entry.');$seen[$lower]=true;
   $total+=(int)$stat['size'];if($total>104857600||$stat['size']>16777216||($stat['comp_size']>0&&$stat['size']/$stat['comp_size']>400))throw new InvalidArgumentException('ZIP extraction limits exceeded.');
   $entries[$path]=$i;
  }
  $raw=$zip->getFromIndex($entries['release.json']??-1);if($raw===false||strlen($raw)>500000)throw new InvalidArgumentException('Invalid release manifest.');
  try{$m=json_decode($raw,true,64,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new InvalidArgumentException('The release manifest is not valid JSON.');}if(!is_array($m))throw new InvalidArgumentException('Invalid release manifest.');update_manifest($m);
  if($expected!==null&&$m['version']!==$expected)throw new InvalidArgumentException('Release version does not match the GitHub asset.');
  if(count($entries)!==count($m['files'])+1)throw new InvalidArgumentException('Unlisted or missing files in the update ZIP.');
  foreach($m['files'] as $path=>$hash){if(!isset($entries[$path]))throw new InvalidArgumentException('A declared file is missing: '.$path);$data=$zip->getFromIndex($entries[$path]);if($data===false||!hash_equals($hash,hash('sha256',$data)))throw new InvalidArgumentException('Checksum failed for '.$path);$target=$dir.'/files/'.$path;if(!is_dir(dirname($target)))mkdir(dirname($target),0700,true);if(file_put_contents($target,$data,LOCK_EX)===false)throw new RuntimeException('Could not stage update files.');}
  file_put_contents($dir.'/files/release.json',$raw,LOCK_EX);
  $php=file_get_contents($dir.'/files/version.php');if(!preg_match("/const\\s+APP_VERSION\\s*=\\s*['\"]".preg_quote($m['version'],'/')."['\"]/",$php))throw new InvalidArgumentException('version.php does not match the release manifest.');
  $plan=['job'=>$job,'source'=>$source,'version'=>$m['version'],'schema'=>$m['schema'],'files'=>count($m['files']),'created_at'=>now(),'manifest'=>$m];file_put_contents($dir.'/plan.json',json($plan),LOCK_EX);$_SESSION['update_job']=$job;return $plan;
 }catch(Throwable $e){remove_job_tree($dir);throw $e;}finally{$zip->close();}
}
function staged_plan():?array{
 $id=$_SESSION['update_job']??'';if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/',$id))return null;$path=update_private().'/job-'.$id.'/plan.json';if(!is_file($path))return null;return arr(file_get_contents($path));
}
function assert_destination(string $path):void{
 $parts=explode('/',$path);$target=ROOT;
 foreach($parts as $part){$target.='/'.$part;if(is_link($target))throw new InvalidArgumentException('Update destinations may not contain symlinks.');}
 if(is_file($target)&&!is_writable($target))throw new InvalidArgumentException('Not writable: '.$path);
 $parent=dirname($target);while(!is_dir($parent))$parent=dirname($parent);if(!is_writable($parent))throw new InvalidArgumentException('Directory is not writable for '.$path);
}
function backup_write($stream,string $data):void{if(fwrite($stream,$data)!==strlen($data))throw new RuntimeException('The database backup could not be written completely.');}
function database_snapshot(string $file):void{
 $out=fopen($file,'wb');if(!$out)throw new RuntimeException('Cannot create database backup.');
 $tables=array_values(array_filter(db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN),fn($x)=>str_starts_with($x,'ks_')));
 db()->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');db()->beginTransaction();
 try{
  backup_write($out,"-- Kitchen Safety backup ".now()." UTC\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
  foreach($tables as $table){if(!preg_match('/^ks_[a-z_]+$/',$table))throw new RuntimeException('Unexpected database table.');$create=db()->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];backup_write($out,'DROP TABLE IF EXISTS `'.$table."`;\n".$create.";\n");
   for($offset=0;;$offset+=500){$batch=rows('SELECT * FROM `'.$table.'` LIMIT 500 OFFSET '.$offset);if(!$batch)break;foreach($batch as $row){$values=array_map(fn($v)=>$v===null?'NULL':db()->quote((string)$v),array_values($row));backup_write($out,'INSERT INTO `'.$table.'` VALUES ('.implode(',',$values).");\n");}}
  }
  backup_write($out,"SET FOREIGN_KEY_CHECKS=1;\n");db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}finally{fclose($out);chmod($file,0600);}
}
function atomic_copy(string $source,string $dest):void{
 if(!is_dir(dirname($dest))&&!mkdir(dirname($dest),0755,true))throw new RuntimeException('Could not create a release directory.');
 $tmp=tempnam(dirname($dest),'.ks-');if($tmp===false)throw new RuntimeException('Could not prepare an updated file.');
 try{if(!copy($source,$tmp)||!chmod($tmp,0644)||!rename($tmp,$dest))throw new RuntimeException('Could not replace '.basename($dest).'.');}finally{if(is_file($tmp))unlink($tmp);}
}
function apply_update():array{
 need_admin();$plan=staged_plan();if(!$plan)throw new InvalidArgumentException('Stage an update ZIP first.');$dir=update_private().'/job-'.$plan['job'];$m=$plan['manifest'];update_manifest($m);
 if(time()-strtotime($plan['created_at'].' UTC')>3600)throw new InvalidArgumentException('The staged update has expired. Upload it again.');
 $lock=fopen(update_private().'/update.lock','c+');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new InvalidArgumentException('Another update is running.');
 $changed=[];$before=[];$maintenance=update_private().'/maintenance.json';$schemaChanged=false;$success=false;
 try{
  foreach($m['files'] as $path=>$hash){assert_destination($path);if(!is_file($dir.'/files/'.$path)||!hash_equals($hash,hash_file('sha256',$dir.'/files/'.$path)))throw new InvalidArgumentException('A staged file changed. Upload the release again.');}
  $paths=array_values(array_unique(array_merge(array_keys($m['files']),['release.json'],$m['remove']??[])));foreach($paths as $path)assert_destination($path);
  file_put_contents($maintenance,json(['started_at'=>now(),'from'=>APP_VERSION,'to'=>$m['version']]),LOCK_EX);
  database_snapshot($dir.'/database.sql');$backup=new ZipArchive();$backupPath=update_private().'/backup-'.$plan['job'].'.zip';if($backup->open($backupPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Cannot create backup ZIP.');
  foreach($paths as $path){$before[$path]=is_file(ROOT.'/'.$path);if($before[$path]){if(!is_dir(dirname($dir.'/rollback/'.$path)))mkdir(dirname($dir.'/rollback/'.$path),0700,true);if(!copy(ROOT.'/'.$path,$dir.'/rollback/'.$path))throw new RuntimeException('Could not back up an existing file.');$backup->addFile($dir.'/rollback/'.$path,'files/'.$path);}}
  $backup->addFile($dir.'/database.sql','database.sql');$backup->addFromString('recovery.json',json(['from'=>APP_VERSION,'to'=>$m['version'],'existing'=>$before,'created_at'=>now()]));if(!$backup->close())throw new RuntimeException('Could not finish backup ZIP.');chmod($backupPath,0600);
  $ordered=array_keys($m['files']);usort($ordered,fn($a,$b)=>($a==='version.php'?1:0)<=>($b==='version.php'?1:0));$ordered[]='release.json';
  foreach($ordered as $path){atomic_copy($dir.'/files/'.$path,ROOT.'/'.$path);$changed[]=$path;}
  foreach($m['remove']??[] as $path)if(is_file(ROOT.'/'.$path)){if(!unlink(ROOT.'/'.$path))throw new RuntimeException('Could not remove an obsolete file.');$changed[]=$path;}
  $current=(int)setting('schema_version','1');
  for($n=$current+1;$n<=$m['schema'];$n++){
   $mp='migrations/'.sprintf('%03d',$n).'.json';if(!isset($m['files'][$mp]))throw new RuntimeException('Missing database migration '.$n.'.');$migration=arr(file_get_contents($dir.'/files/'.$mp));if(($migration['schema']??0)!==$n||!is_array($migration['statements']??null))throw new RuntimeException('Invalid database migration.');
   $schemaChanged=true;foreach($migration['statements'] as $sql){if(!is_string($sql))throw new RuntimeException('Invalid migration SQL.');db()->exec($sql);}set_setting('schema_version',(string)$n);
  }
  $history=arr(setting('update_history','[]'));$history[]=['from'=>APP_VERSION,'version'=>$m['version'],'source'=>$plan['source'],'by'=>need_user()['name'],'at'=>now(),'backup'=>$plan['job']];set_setting('update_history',json(array_slice($history,-20)));audit('update.apply','portal',['from'=>APP_VERSION,'to'=>$m['version'],'source'=>$plan['source'],'backup'=>$plan['job']]);
  $success=true;unset($_SESSION['update_job']);return $plan;
 }catch(Throwable $e){
  $rollbackOK=true;foreach(array_reverse($changed) as $path){try{if($before[$path])atomic_copy($dir.'/rollback/'.$path,ROOT.'/'.$path);elseif(is_file(ROOT.'/'.$path)&&!unlink(ROOT.'/'.$path))$rollbackOK=false;}catch(Throwable $ignored){$rollbackOK=false;}}
  if($schemaChanged||!$rollbackOK){file_put_contents($maintenance,json(['error'=>'Recovery required','at'=>now(),'backup'=>$plan['job']]),LOCK_EX);throw new InvalidArgumentException('Update stopped. File rollback was attempted. Maintenance remains enabled because database or file recovery is required. Restore the private backup with your host. Backup ID: '.$plan['job']);}
  if(is_file($maintenance))unlink($maintenance);throw new InvalidArgumentException('Update stopped; changed files were rolled back. '.$e->getMessage());
 }finally{if($success){if(is_file($maintenance))unlink($maintenance);remove_job_tree($dir);}flock($lock,LOCK_UN);fclose($lock);}
}
function github_http(string $url,?string $dest=null):string{
 if(!extension_loaded('curl'))throw new InvalidArgumentException('Enable the PHP curl extension for GitHub updates.');
 $allowed=['api.github.com','github.com','release-assets.githubusercontent.com','objects.githubusercontent.com'];
 for($redirects=0;$redirects<6;$redirects++){
  $p=parse_url($url);if(($p['scheme']??'')!=='https'||!in_array(strtolower($p['host']??''),$allowed,true)||isset($p['user'])||isset($p['pass'])||isset($p['port']))throw new InvalidArgumentException('GitHub returned an unsupported download host.');
  $headers=[];$body='';$size=0;$stream=$dest?fopen($dest,'wb'):null;if($dest&&!$stream)throw new RuntimeException('Could not prepare release download.');
  $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>120,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>['Accept: application/vnd.github+json','User-Agent: MustrHQ-Kitchen-Safety/'.APP_VERSION,'X-GitHub-Api-Version: 2022-11-28'],CURLOPT_HEADERFUNCTION=>function($c,$line)use(&$headers){$parts=explode(':',$line,2);if(count($parts)===2)$headers[strtolower(trim($parts[0]))]=trim($parts[1]);return strlen($line);},CURLOPT_WRITEFUNCTION=>function($c,$data)use(&$body,&$size,$stream,$dest){$size+=strlen($data);if($size>($dest?UPDATE_MAX_BYTES:2097152))return 0;if($stream)return fwrite($stream,$data);$body.=$data;return strlen($data);}]);
  $ok=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);if($stream)fclose($stream);
  if($ok===false)throw new InvalidArgumentException('GitHub download failed or exceeded the size limit. Try again later.');
  if(in_array($code,[301,302,303,307,308],true)&&isset($headers['location'])){$url=$headers['location'];continue;}
  if($code===404)throw new InvalidArgumentException('No published release was found for '.APP_REPO.'.');if($code===403||$code===429)throw new InvalidArgumentException('GitHub is temporarily limiting requests. Try again later.');if($code!==200)throw new InvalidArgumentException('GitHub returned HTTP '.$code.'. Try again later.');return $body;
 }
 throw new InvalidArgumentException('Too many GitHub download redirects.');
}
function release_details(array $r):array{
 $v=ltrim((string)($r['tag_name']??''),'v');if(!preg_match('/^\d+\.\d+\.\d+$/',$v)||($r['draft']??false)||($r['prerelease']??false))throw new InvalidArgumentException('No supported stable release is available.');
 $wanted='mustrhq-kitchen-safety-'.$v.'.zip';$asset=null;foreach($r['assets']??[] as $a)if(($a['name']??'')===$wanted)$asset=$a;
 if(!$asset)throw new InvalidArgumentException('The latest release has no installable ZIP asset.');
 $expected='https://github.com/'.APP_REPO.'/releases/download/'.rawurlencode($r['tag_name']).'/'.$wanted;
 if(($asset['browser_download_url']??'')!==$expected||!preg_match('/^sha256:([a-f0-9]{64})$/',(string)($asset['digest']??''),$match))throw new InvalidArgumentException('The release asset has no verified SHA-256 digest or uses an unexpected URL.');
 $out=['version'=>$v,'url'=>$expected,'sha256'=>$match[1],'published_at'=>$r['published_at']??'','checked_at'=>time(),'notes'=>mb_substr((string)($r['body']??''),0,6000)];return $out;
}
function latest_release():array{
 try{$r=json_decode(github_http('https://api.github.com/repos/'.APP_REPO.'/releases/latest'),true,64,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new InvalidArgumentException('GitHub returned an unreadable release response.');}
 $out=release_details($r);$_SESSION['github_release']=$out;return $out;
}
function handle_update_post():void{
 check_csrf();need_admin();$action=text_input('action',40);
 if($action==='update_upload'){
  $f=$_FILES['package']??null;if(!$f||is_array($f['error']??null)||($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name']))throw new InvalidArgumentException('Upload failed. Select a ZIP within the server upload limits (application maximum 32 MiB).');
  $plan=stage_update($f['tmp_name'],'ZIP upload');flash('Release '.$plan['version'].' is staged. Review it before installing.');go('p=updates');
 }
 if($action==='update_check'){latest_release();flash('GitHub release information refreshed.');go('p=updates');}
 if($action==='update_github'){
  $r=$_SESSION['github_release']??null;if(!$r||time()-$r['checked_at']>600)throw new InvalidArgumentException('Check for the latest release again before downloading.');if(!version_compare($r['version'],APP_VERSION,'>'))throw new InvalidArgumentException('The installed version is already current.');
  $file=update_private().'/download-'.bin2hex(random_bytes(12)).'.zip';try{github_http($r['url'],$file);if(!hash_equals($r['sha256'],hash_file('sha256',$file)))throw new InvalidArgumentException('Downloaded ZIP checksum did not match GitHub.');stage_update($file,'GitHub '.APP_REPO,$r['version']);}finally{if(is_file($file))unlink($file);}flash('GitHub release downloaded and verified. Review the staged update.');go('p=updates');
 }
 if($action==='update_apply'){if(!isset($_POST['confirm']))throw new InvalidArgumentException('Confirm installation before continuing.');$p=apply_update();flash('Updated to '.$p['version'].'. Database and affected-file backup retained in the private directory.');go('p=updates');}
 if($action==='update_cancel'){$p=staged_plan();if($p)remove_job_tree(update_private().'/job-'.$p['job']);unset($_SESSION['update_job']);flash('Staged update discarded.');go('p=updates');}
 throw new InvalidArgumentException('Unknown update action.');
}
