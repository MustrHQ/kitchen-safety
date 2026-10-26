<?php
declare(strict_types=1);
function upgrade_database(PDO $pdo):void{
 $version=(int)($pdo->query("SELECT v FROM ks_settings WHERE k='schema_version'")->fetchColumn()?:1);
 if($version>=DB_SCHEMA_VERSION)return;
 $key='ks_migrate_'.substr(hash('sha256',DB_NAME),0,30);
 if((int)$pdo->query('SELECT GET_LOCK('.$pdo->quote($key).',15)')->fetchColumn()!==1)throw new RuntimeException('Database upgrade is busy.');
 try{
  $version=(int)($pdo->query("SELECT v FROM ks_settings WHERE k='schema_version'")->fetchColumn()?:1);
  if($version<2){
   $pdo->beginTransaction();
   $q=$pdo->prepare("INSERT INTO ks_users (name,email,password_hash,role,active,created_at) SELECT ?,?,?,?,0,? WHERE NOT EXISTS(SELECT 1 FROM ks_users WHERE email=?)");
   $q->execute(['Kitchen tablet','kiosk@local.invalid','!disabled','system',now(),'kiosk@local.invalid']);
   $id=(int)$pdo->query("SELECT id FROM ks_users WHERE email='kiosk@local.invalid'")->fetchColumn();
   $q=$pdo->prepare('INSERT INTO ks_settings(k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)');$q->execute(['public_actor_id',(string)$id]);$q->execute(['schema_version','2']);
   $pdo->commit();
  }
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 finally{$pdo->query('SELECT RELEASE_LOCK('.$pdo->quote($key).')');}
}
