<?php
define('ADMIN_CONTEXT',true);define('UPDATER_CONTEXT',true);require dirname(__DIR__).'/inc/bootstrap.php';need_user();need_admin();
$id=$_GET['id']??'';if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/',$id))fail(400,'Invalid backup reference.');
$file=ROOT.'/private/backup-'.$id.'.zip';if(!is_file($file)||is_link($file))fail(404,'Backup not found.');
header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="kitchen-safety-backup-'.$id.'.zip"');header('Content-Length: '.filesize($file));audit('update.backup_download','backup:'.$id);readfile($file);exit;
