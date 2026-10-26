<?php
declare(strict_types=1);
function add_event(array $r,string $type,string $body,array $data=[]):void{ $u=need_user();if(public_mode())$data['identity']='Self-reported name; no staff login'; run('INSERT INTO ks_events (record_id,user_id,user_name,type,body,data_json,created_at) VALUES (?,?,?,?,?,?,?)',[$r['id'],$u['id'],$u['name'],$type,$body,json($data),now()]); audit('record.'.$type,'record:'.$r['id'],$data); }
function schedule_values():array{
 $raw=text_input('times',100);$times=array_values(array_unique(array_map('trim',explode(',',$raw))));sort($times);
 if(count($times)>12)throw new InvalidArgumentException('Use up to twelve daily check times.');
 foreach($times as $t)if(!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$t))throw new InvalidArgumentException('Use check times like 08:00, 16:00.');
 $days=$_POST['days']??[];if(!is_array($days)||!$days)throw new InvalidArgumentException('Select at least one operating day.');$days=array_values(array_unique(array_map('intval',$days)));sort($days);foreach($days as $d)if($d<1||$d>7)throw new InvalidArgumentException('Invalid operating day.');
 return [json($times),json($days)];
}
function handle_post():void{
 check_csrf();$action=text_input('action',60);if(public_mode()){if(!in_array($action,['switch_site','record','event'],true))fail(403,'Use the protected admin panel for this action.');if(in_array($action,['record','event'],true)){identify_public_recorder();limit_public_writes();}}$u=need_user();
 if($action==='logout'){$_SESSION=[];session_destroy();go('p=login');}
 if($action==='switch_site') { $s=site_access((int)($_POST['site_id']??0));$_SESSION['site_id']=$s['id'];go(); }
 if($action==='password'){$current=text_input('current_password',1000);$new=text_input('password',1000);if(!password_verify($current,$u['password_hash']))throw new InvalidArgumentException('Current password does not match.');if(strlen($new)<12)throw new InvalidArgumentException('Use at least twelve characters for the new password.');run('UPDATE ks_users SET password_hash=?,auth_version=auth_version+1 WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$u['id']]);$_SESSION['av']=(int)$u['auth_version']+1;session_regenerate_id(true);audit('user.password','user:'.$u['id']);flash('Password changed. Other sessions have been signed out.');go('p=account');}
 $s=site();
 if($action==='record'){
  active_site();$kind=text_input('kind',40);if($kind!=='task'&&!isset(KINDS[$kind]))throw new InvalidArgumentException('Select a valid check type.');
  $request=text_input('request_id',64);if(!preg_match('/^[a-f0-9]{32}$/',$request))throw new InvalidArgumentException('Invalid form. Reload the page.');
  if($old=one('SELECT id,site_id FROM ks_records WHERE request_id=?',[$request])){site_access((int)$old['site_id']);go('p=record&id='.$old['id']);}
  $observed=observed_input('observed_at',$s['timezone']);$late=text_input('late_reason',2000,false);if(time()-strtotime($observed.' UTC')>900&&$late==='')throw new InvalidArgumentException('Explain why this reading is being entered more than 15 minutes later.');
  $equipment=null;$task=null;$scheduled=null;$payload=[];$limits=[];$temp=null;$hold=null;$status='pass';
  $subject=text_input('subject',200,false);
  if(in_array($kind,['fridge','freezer','hot','task'],true)){
   $taskMode=$kind==='task';$x=one('SELECT * FROM ks_'.($taskMode?'tasks':'equipment').' WHERE id=? AND site_id=? AND active=1',[(int)($_POST['asset_id']??0),$s['id']]);
   if(!$x||(!$taskMode&&$x['rule_code']!==$kind))throw new InvalidArgumentException('Select an active item in this kitchen.');
   $subject=$x['name'];if($taskMode){$task=$x['id'];$payload['instructions']=$x['instructions'];}else $equipment=$x['id'];
   $slot=text_input('slot',5,false);if($slot!==''){
    $date=local_time($observed,'Y-m-d',$s['timezone']);$day=(int)local_time($observed,'N',$s['timezone']);
    if(!in_array($slot,arr($x['times_json']),true)||!in_array($day,arr($x['days_json']),true))throw new InvalidArgumentException('This scheduled slot is not active for the measured date. Use an extra reading instead.');
    $scheduled=parse_local($date.'T'.$slot,$s['timezone'])->format('Y-m-d H:i:s');
    if(strtotime($observed.' UTC')<strtotime($scheduled.' UTC')-1800)throw new InvalidArgumentException('Scheduled checks can be recorded up to 30 minutes early. Use an extra reading if needed.');
    if(strtotime($observed.' UTC')>strtotime($scheduled.' UTC')+1800&&$late==='')throw new InvalidArgumentException('Add a reason for this late scheduled check.');
   }
  }
  if($subject==='')throw new InvalidArgumentException('Enter the food, batch or probe name.');
  if($kind==='task'){
   $status=isset($_POST['task_ok'])?'pass':'fail';$payload['result']=$status==='pass'?'Completed / satisfactory':'Problem / not completed';
  }else{
   $limits=rules((int)$s['id'])[$kind];$temp=number_input('temperature');$payload['method']=text_input('method',150);$payload['batch']=text_input('batch',150,false);
   if(in_array($kind,['cook','reheat'],true)){$hold=(int)number_input('hold_seconds',0,3600);if($kind==='reheat'&&!isset($_POST['once_only']))throw new InvalidArgumentException('Confirm this is the first and only reheating.');$payload['once_only']=$kind==='reheat';}
   if(in_array($kind,['delivery_cold','delivery_frozen'],true)){$payload['supplier']=text_input('supplier',150);$payload['packaging_ok']=isset($_POST['packaging_ok']);}
   if(in_array($kind,['rice','cool'],true)){$status='pending';$payload['cooling_method']=text_input('cooling_method',500);}else $status=evaluate($kind,$temp,$hold??0,$limits);
   if(isset($payload['packaging_ok'])&&!$payload['packaging_ok'])$status='fail';
  }
  if(public_mode())$payload['identity']='Self-reported name; no staff login';
  $notes=text_input('notes',5000,false);$actionNote=text_input('action_note',5000,false);if(in_array($status,['warn','fail'],true)&&$actionNote==='')throw new InvalidArgumentException('This check needs attention. Describe what you did immediately in the corrective-action field.');
  db()->beginTransaction();try{
   run('INSERT INTO ks_records (request_id,site_id,user_id,site_name,user_name,kind,subject,equipment_id,task_id,scheduled_for,observed_at,recorded_at,timezone,temperature,hold_seconds,status,limits_json,payload_json,notes,late_reason) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$request,$s['id'],$u['id'],$s['name'],$u['name'],$kind,$subject,$equipment,$task,$scheduled,$observed,now(),$s['timezone'],$temp,$hold,$status,json($limits),json($payload),$notes,$late]);
   $id=(int)db()->lastInsertId();audit('record.create','record:'.$id,['status'=>$status,'kind'=>$kind]);if($actionNote!=='')add_event(['id'=>$id],'action',$actionNote);
   db()->commit();
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if(($e instanceof PDOException)&&($e->errorInfo[1]??0)===1062){$old=one('SELECT id FROM ks_records WHERE request_id=?',[$request]);if($old)go('p=record&id='.$old['id']);}throw $e;}
  flash($status==='pending'?'Cooling started. Record the finish before the deadline.':'Check saved.');go('p=record&id='.$id);
 }
 if($action==='event'){
  $id=(int)($_POST['record_id']??0);$r=one('SELECT * FROM ks_records WHERE id=?',[$id]);if(!$r)throw new InvalidArgumentException('Record not found.');site_access((int)$r['site_id']);$type=text_input('type',40);$body=text_input('body',5000);$data=[];
  if(public_mode()&&$type==='resolve')fail(403,'Manager access required.');
  if(!in_array($type,['action','recheck','cooling_complete','correction','resolve'],true))throw new InvalidArgumentException('Invalid update.');
  db()->beginTransaction();try{
   $r=one('SELECT * FROM ks_records WHERE id=? FOR UPDATE',[$id]);$st=state($r);
   if($type==='resolve'){
    need_manager();if(!$st['needs_action'])throw new InvalidArgumentException('Only an open exception can be resolved.');
    $disposition=text_input('disposition',60);if(!in_array($disposition,['discarded','rejected','verified','other'],true))throw new InvalidArgumentException('Choose a resolution.');
    if($disposition==='verified'){$last=null;foreach($st['events'] as $ev)if(in_array($ev['type'],['recheck','cooling_complete'],true))$last=arr($ev['data_json']);if(!$last||($last['status']??'')!=='pass')throw new InvalidArgumentException('Record a passing follow-up reading before resolving as verified.');}
    $data=['disposition'=>$disposition];
   }elseif($type==='recheck'){
    if(in_array($r['kind'],['task','cool','rice'],true))throw new InvalidArgumentException('Use the cooling finish or an action note for this type of record.');
    $data['temperature']=number_input('temperature');$data['hold_seconds']=in_array($r['kind'],['cook','reheat'],true)?(int)number_input('hold_seconds',0,3600):0;
    $data['observed_at']=observed_input('observed_at',$r['timezone']);if($data['observed_at']<$r['observed_at'])throw new InvalidArgumentException('Follow-up cannot precede the original reading.');
    $data['status']=evaluate($r['kind'],$data['temperature'],$data['hold_seconds'],arr($r['limits_json']));
   }elseif($type==='cooling_complete'){
    if(!in_array($r['kind'],['cool','rice'],true)||$st['completed']||$st['closed'])throw new InvalidArgumentException('This record cannot accept a cooling finish.');
    $data['observed_at']=observed_input('observed_at',$r['timezone']);$elapsed=(strtotime($data['observed_at'].' UTC')-strtotime($r['observed_at'].' UTC'))/60;
    if($elapsed<=0)throw new InvalidArgumentException('Finish time must be after the cooling start.');
    $data['temperature']=number_input('temperature');$data['elapsed_minutes']=round($elapsed,2);$data['storage_location']=text_input('storage_location',200);
    if(!isset($_POST['refrigerated']))throw new InvalidArgumentException('Confirm the food is now in chilled storage.');
    $data['status']=evaluate($r['kind'],$data['temperature'],0,arr($r['limits_json']),$elapsed);
    if(time()-strtotime($data['observed_at'].' UTC')>900)$data['late_entry_note']=text_input('late_reason',2000);
   }
   if($type==='recheck'&&time()-strtotime($data['observed_at'].' UTC')>900)$data['late_entry_note']=text_input('late_reason',2000);
   if($st['closed']&&!in_array($type,['correction','action'],true))throw new InvalidArgumentException('This exception has already been resolved. Add a correction or create a new check.');
   add_event($r,$type,$body,$data);db()->commit();
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  flash('Update saved to the permanent record.');go('p=record&id='.$id);
 }
 if($action==='review'){
  need_manager();$date=text_input('review_date',10);$dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$dt||$dt->format('Y-m-d')!==$date||$date>local_time(now(),'Y-m-d'))throw new InvalidArgumentException('Choose today or an earlier date.');
  $summary=day_summary($s,$date);$notes=text_input('notes',5000);run('INSERT INTO ks_reviews (site_id,review_date,user_id,user_name,notes,snapshot_json,created_at) VALUES (?,?,?,?,?,?,?)',[$s['id'],$date,$u['id'],$u['name'],$notes,json($summary),now()]);audit('day.review','site:'.$s['id'],['date'=>$date,'summary'=>$summary]);flash('Daily review saved.');go('p=history&from='.$date.'&to='.$date);
 }
 if($action==='asset'){
  need_manager();active_site();$type=text_input('asset_type',20);if(!in_array($type,['equipment','tasks'],true))throw new InvalidArgumentException('Invalid item.');
  $id=(int)($_POST['id']??0);$name=text_input('name',150);[$times,$days]=schedule_values();$active=isset($_POST['active'])?1:0;
  $rule=$type==='equipment'?text_input('rule_code',40):null;if($rule&&!in_array($rule,['fridge','freezer','hot'],true))throw new InvalidArgumentException('Select fridge, freezer or hot holding.');
  $instructions=$type==='tasks'?text_input('instructions',4000):'';
  db()->beginTransaction();try{
   if($id){$old=one('SELECT * FROM ks_'.$type.' WHERE id=? AND site_id=? FOR UPDATE',[$id,$s['id']]);if(!$old)throw new InvalidArgumentException('Item not found in this kitchen.');if($rule&&$old['rule_code']!==$rule)throw new InvalidArgumentException('Create a new item to change its type; historical schedules must retain their meaning.');}
   $fields=$type==='equipment'?['rule_code',$rule]:['instructions',$instructions];
   if($id)run('UPDATE ks_'.$type.' SET name=?,'.$fields[0].'=?,times_json=?,days_json=?,active=? WHERE id=? AND site_id=?',[$name,$fields[1],$times,$days,$active,$id,$s['id']]);
   else{run('INSERT INTO ks_'.$type.' (site_id,name,'.$fields[0].',times_json,days_json,active,created_at) VALUES (?,?,?,?,?,?,?)',[$s['id'],$name,$fields[1],$times,$days,$active,now()]);$id=(int)db()->lastInsertId();}
   run('UPDATE ks_schedules SET valid_to=? WHERE site_id=? AND asset_type=? AND asset_id=? AND valid_to IS NULL',[now(),$s['id'],$type,$id]);
   run('INSERT INTO ks_schedules (site_id,asset_type,asset_id,name,kind,times_json,days_json,active,valid_from) VALUES (?,?,?,?,?,?,?,?,?)',[$s['id'],$type,$id,$name,$rule??'task',$times,$days,$active,now()]);
   audit($type.'.save',$type.':'.$id,['before'=>$old??null,'name'=>$name,'times'=>arr($times),'days'=>arr($days),'active'=>$active]);set_setting('plan_approved_'.$s['id'],'');db()->commit();
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  flash('Saved. New timetable revisions preserve earlier scheduled checks and submitted records.');go('p=equipment');
 }
 if($action==='rule'){
  need_manager();active_site();$kind=text_input('kind',40);$l=validate_limits($kind,$_POST);$reason=text_input('reason',2000);$old=rules((int)$s['id'])[$kind];
  db()->beginTransaction();try{run('UPDATE ks_rules SET limits_json=?,updated_at=? WHERE site_id=? AND code=?',[json($l),now(),$s['id'],$kind]);audit('limits.change','site:'.$s['id'].':'.$kind,['before'=>$old,'after'=>$l,'reason'=>$reason]);set_setting('plan_approved_'.$s['id'],'');db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  flash('Limit changed for new records. Existing records keep their original limits.');go('p=limits');
 }
 if($action==='approve_plan'){
  need_manager();$notes=text_input('notes',5000);$data=['by'=>$u['name'],'at'=>now(),'notes'=>$notes,'rules'=>rules((int)$s['id'])];db()->beginTransaction();try{set_setting('plan_approved_'.$s['id'],json($data));audit('plan.review','site:'.$s['id'],$data);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}flash('Control review recorded.');go('p=limits');
 }
 if($action==='site'){
  need_admin();$id=(int)($_POST['id']??0);$name=text_input('name',150);$address=text_input('address',500,false);$timezone=text_input('timezone',80);if(!in_array($timezone,DateTimeZone::listIdentifiers(),true))throw new InvalidArgumentException('Choose a valid IANA time zone.');$active=isset($_POST['active'])?1:0;
  db()->beginTransaction();try{if($id){$old=one('SELECT * FROM ks_sites WHERE id=? FOR UPDATE',[$id]);if(!$old)throw new InvalidArgumentException('Kitchen not found.');if($old['timezone']!==$timezone&&(one('SELECT id FROM ks_records WHERE site_id=? LIMIT 1',[$id])))throw new InvalidArgumentException('Time zone cannot change after records exist. Create a new kitchen instead.');run('UPDATE ks_sites SET name=?,address=?,timezone=?,active=? WHERE id=?',[$name,$address,$timezone,$active,$id]);}else{run('INSERT INTO ks_sites (name,address,timezone,active,created_at) VALUES (?,?,?,?,?)',[$name,$address,$timezone,$active,now()]);$id=(int)db()->lastInsertId();seed_rules(db(),$id);}audit('site.save','site:'.$id,['before'=>$old??null,'name'=>$name,'address'=>$address,'timezone'=>$timezone,'active'=>$active]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}flash('Kitchen saved.');go('p=sites');
 }
 if($action==='user'){
  need_admin();$id=(int)($_POST['id']??0);$name=text_input('name',150);$email=strtolower(text_input('email',190));$role=text_input('role',20);$pass=text_input('password',1000,false);$active=isset($_POST['active'])?1:0;
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)||!in_array($role,['admin','manager','staff'],true))throw new InvalidArgumentException('Check email and role.');if(($id===0||$pass!=='')&&strlen($pass)<12)throw new InvalidArgumentException('Use a password of at least twelve characters.');
  if($id===(int)$u['id']&&(!$active||$role!=='admin'))throw new InvalidArgumentException('You cannot remove your own administrator access.');
  $assigned=$_POST['sites']??[];if(!is_array($assigned))throw new InvalidArgumentException('Invalid kitchen assignments.');$assigned=array_values(array_unique(array_map('intval',$assigned)));foreach($assigned as $sid)if(!one('SELECT id FROM ks_sites WHERE id=?',[$sid]))throw new InvalidArgumentException('Invalid kitchen assignment.');
  if($role!=='admin'&&!$assigned)throw new InvalidArgumentException('Assign at least one kitchen.');
  db()->beginTransaction();try{
   if($id){$old=one('SELECT id,name,email,role,active FROM ks_users WHERE id=? FOR UPDATE',[$id]);if(!$old)throw new InvalidArgumentException('User not found.');run('UPDATE ks_users SET name=?,email=?,role=?,active=?,auth_version=auth_version+1 WHERE id=?',[$name,$email,$role,$active,$id]);if($pass!=='')run('UPDATE ks_users SET password_hash=? WHERE id=?',[password_hash($pass,PASSWORD_DEFAULT),$id]);}
   else{run('INSERT INTO ks_users (name,email,password_hash,role,active,created_at) VALUES (?,?,?,?,?,?)',[$name,$email,password_hash($pass,PASSWORD_DEFAULT),$role,$active,now()]);$id=(int)db()->lastInsertId();}
   run('DELETE FROM ks_user_sites WHERE user_id=?',[$id]);foreach($assigned as $sid)run('INSERT INTO ks_user_sites (user_id,site_id) VALUES (?,?)',[$id,$sid]);
   audit('user.save','user:'.$id,['before'=>$old??null,'name'=>$name,'email'=>$email,'role'=>$role,'active'=>$active,'sites'=>$assigned,'password_reset'=>$pass!=='']);db()->commit();
   if($id===(int)$u['id'])$_SESSION['av']=(int)one('SELECT auth_version FROM ks_users WHERE id=?',[$id])['auth_version'];
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if($e instanceof PDOException&&($e->errorInfo[1]??0)===1062)throw new InvalidArgumentException('That email address is already registered.');throw $e;}
  flash('User saved. Changed permissions and passwords sign out their other sessions.');go('p=users');
 }
 throw new InvalidArgumentException('Unknown action.');
}
