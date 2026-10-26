<?php
// Emergency CLI-only administrator password reset. Not accessible through Apache.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/config.php';
$email=$argv[1]??'';if(!$email){fwrite(STDERR,"Usage: php docs/reset-admin.php admin@example.com\nPassword is read from standard input.\n");exit(1);}
fwrite(STDERR,"New password (12+ characters; input is visible unless your shell hides it): ");$pass=rtrim(fgets(STDIN),"\r\n");if(strlen($pass)<12){fwrite(STDERR,"Too short.\n");exit(1);}
$p=new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$s=$p->prepare("UPDATE ks_users SET password_hash=?,auth_version=auth_version+1 WHERE email=? AND role='admin' AND active=1");$s->execute([password_hash($pass,PASSWORD_DEFAULT),strtolower($email)]);
if(!$s->rowCount()){fwrite(STDERR,"No active administrator matched.\n");exit(1);}
$p->prepare('INSERT INTO ks_audit (actor,action,entity,data_json,created_at) VALUES (?,?,?,?,?)')->execute(['Server administrator','user.emergency_password',$email,'{}',gmdate('Y-m-d H:i:s')]);echo "Password reset. Existing sessions are invalidated.\n";
