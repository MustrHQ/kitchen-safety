<?php
define('ADMIN_CONTEXT',true);require __DIR__.'/inc/bootstrap.php';require __DIR__.'/inc/ui.php';
$error='';$done=false;$installed=defined('DB_HOST');
$assetPermissions=true;foreach(['assets/app.css','assets/frontend.css','assets/app.js','assets/fonts/archivo-var.woff2'] as $file){$mode=@fileperms(ROOT.'/'.$file);if(!is_file(ROOT.'/'.$file)||$mode===false||($mode&0004)===0)$assetPermissions=false;}
$checks=['Public asset file permissions'=>$assetPermissions,'PHP 8.1 or newer'=>version_compare(PHP_VERSION,'8.1.0','>='),'PDO MySQL extension'=>extension_loaded('pdo_mysql'),'mbstring extension'=>extension_loaded('mbstring'),'Application directory writable'=>is_writable(ROOT),'HTTPS connection'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'];
if(!$installed&&$_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();$lock=fopen(ROOT.'/.install.lock','c+');
 if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))$error='Setup is already running. Wait a moment, then reload.';
 else{
  $tempConfig='';$pdo=null;
  try{
   clearstatcache();if(is_file(ROOT.'/config.php'))throw new InvalidArgumentException('Already installed. Open the portal.');
   foreach($checks as $label=>$ok)if(!$ok&&$label!=='HTTPS connection')throw new InvalidArgumentException('Fix the server requirement: '.$label);
   $host=text_input('db_host',150);$port=(int)number_input('db_port',1,65535);$database=text_input('db_name',100);$dbUser=text_input('db_user',150);$dbPass=text_input('db_pass',1000,false);
   if(!preg_match('/^[a-zA-Z0-9._:\-]+$/',$host)||!preg_match('/^[a-zA-Z0-9_\-]+$/',$database))throw new InvalidArgumentException('Check the database host and database name.');
   $name=text_input('name',150);$email=strtolower(text_input('email',190));$pass=text_input('password',1000);$kitchen=text_input('kitchen',150);$tz=text_input('timezone',80);
   if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid administrator email.');if(strlen($pass)<12)throw new InvalidArgumentException('Use an administrator password of at least twelve characters.');if(!in_array($tz,DateTimeZone::listIdentifiers(),true))throw new InvalidArgumentException('Enter a valid IANA time zone.');
   $values=['DB_HOST'=>$host,'DB_PORT'=>$port,'DB_NAME'=>$database,'DB_USER'=>$dbUser,'DB_PASS'=>$dbPass,'APP_SECRET'=>bin2hex(random_bytes(32))];
   $text="<?php\n// Private database configuration. Do not share or commit this file.\n";foreach($values as $k=>$v)$text.='define('.var_export($k,true).', '.var_export($v,true).");\n";
   $tempConfig=ROOT.'/.config-'.bin2hex(random_bytes(8)).'.tmp';if(file_put_contents($tempConfig,$text,LOCK_EX)===false)throw new RuntimeException('Could not prepare configuration.');chmod($tempConfig,0600);
   $pdo=new PDO('mysql:host='.$host.';port='.$port.';dbname='.$database.';charset=utf8mb4',$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
   foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table)if(str_starts_with($table,'ks_'))throw new InvalidArgumentException('This database already contains Kitchen Safety tables. Use a new empty database, or restore the existing configuration. Setup never overwrites data.');
   foreach(require __DIR__.'/inc/schema.php' as $sql)$pdo->exec($sql);
   $pdo->beginTransaction();$pdo->prepare('INSERT INTO ks_users (name,email,password_hash,role,created_at) VALUES (?,?,?,?,?)')->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT),'admin',now()]);
   $pdo->prepare('INSERT INTO ks_sites (name,timezone,created_at) VALUES (?,?,?)')->execute([$kitchen,$tz,now()]);$siteId=(int)$pdo->lastInsertId();seed_rules($pdo,$siteId);
   $pdo->prepare('INSERT INTO ks_settings (k,v) VALUES (?,?)')->execute(['schema_version','1']);
   $pdo->prepare('INSERT INTO ks_audit (actor,action,entity,data_json,created_at) VALUES (?,?,?,?,?)')->execute([$name,'install','portal',json(['version'=>APP_VERSION,'kitchen'=>$kitchen]),now()]);
   $pdo->commit();if(!rename($tempConfig,ROOT.'/config.php'))throw new RuntimeException('Could not activate configuration. Contact your host before retrying.');$tempConfig='';chmod(ROOT.'/config.php',0640);$done=true;$installed=true;
  }catch(Throwable $e){if($pdo&&$pdo->inTransaction())$pdo->rollBack();if($tempConfig&&is_file($tempConfig))unlink($tempConfig);$error=$e instanceof InvalidArgumentException?$e->getMessage():'Setup could not finish. Check database credentials, empty database, MySQL privileges and directory permissions. Your hosting error log has details.';if(!($e instanceof InvalidArgumentException))error_log('Kitchen Safety installer: '.$e->getMessage());}
  flock($lock,LOCK_UN);fclose($lock);
 }
}
head('Install','',true);echo '<div class="installer"><a class="brand auth-brand" href="index.php"><span class="brand-mark"><img src="'.e(base_url('assets/brand/kitchen-safety.svg')).'" width="42" height="42" alt=""></span><span>MustrHQ<small>KITCHEN SAFETY</small></span></a>';
if($installed){echo '<section class="auth-card"><p class="eyebrow">'.($done?'SETUP COMPLETE':'INSTALLER LOCKED').'</p><h1>'.($done?'Your kitchen portal is ready.':'Already installed.').'</h1><p class="muted">Delete <strong>install.php</strong> using cPanel File Manager. The installer also locks itself whenever config.php exists.</p><p class="muted">Sign in, add equipment and staff, then have a manager validate the starting controls against your kitchen’s HACCP plan.</p><a class="btn primary" href="admin/">Open admin panel</a></section>';}
else{
 echo '<p class="eyebrow">CPANEL INSTALLER</p><h1>Set up Kitchen Safety.</h1><p class="muted">Use a separate database and folder from your Recipes portal.</p>';
 if($error)echo '<div class="notice danger" role="alert">'.e($error).'</div>';
 echo '<section class="card"><h2>Server requirements</h2>';foreach($checks as $label=>$ok)echo '<div class="check-status"><span>'.e($label).'</span>'.'<span class="badge '.($ok?'pass':'warn').'">'.($ok?'Ready':'Needs attention').'</span>'.'</div>';if(!$assetPermissions)echo '<p class="notice danger">Public stylesheets/scripts/fonts are missing or have unreadable permissions. In cPanel, set assets folders to 755 and public asset files to 644, or extract the latest release ZIP. Keep config.php and private backups protected.</p>';echo '<p class="help">Requires PHP 8.1+, PDO MySQL, mbstring and MySQL 8.0+ or MariaDB 10.6+. Enable SSL before live use. No Composer, Node, cron or paid service is required.</p></section><form method="post" autocomplete="off">'.csrf().'<section class="card"><h2>1. Database connection</h2><p class="help">In cPanel, create a MySQL database and user, then add the user to the database with All Privileges. Do not reuse the Recipes database.</p><div class="form-grid">';input('db_host','Database host','localhost','text','required maxlength="150"');input('db_port','Port','3306','number','required min="1" max="65535"');input('db_name','Database name','','text','required maxlength="100"');input('db_user','Database username','','text','required maxlength="150"');echo '</div>';input('db_pass','Database password','','password','autocomplete="new-password"');echo '</section><section class="card"><h2>2. First kitchen</h2>';input('kitchen','Kitchen name','','text','required maxlength="150" placeholder="e.g. Brewery Road"');input('timezone','Time zone','Europe/London','text','required maxlength="80"');echo '</section><section class="card"><h2>3. Administrator account</h2>';input('name','Your full name','','text','required maxlength="150"');input('email','Sign-in email','','email','required maxlength="190"');input('password','Password (12 characters minimum)','','password','required minlength="12" autocomplete="new-password"');echo '</section>';button('Install Kitchen Safety');echo '</form>';
}
echo '</div>';foot(true);
