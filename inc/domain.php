<?php
declare(strict_types=1);
const KINDS = ['fridge'=>'Fridge','freezer'=>'Freezer','hot'=>'Hot holding','cook'=>'Cooking','reheat'=>'Reheating','cool'=>'Cooling food','rice'=>'Cooling rice','delivery_cold'=>'Chilled delivery','delivery_frozen'=>'Frozen delivery','probe_ice'=>'Probe · ice point','probe_boiling'=>'Probe · boiling point'];
function default_rules(): array {
 return [
 'fridge'=>['max'=>5,'critical_max'=>8], 'freezer'=>['max'=>-18], 'hot'=>['min'=>63],
 'cook'=>['min'=>75,'hold_seconds'=>30], 'reheat'=>['min'=>75,'hold_seconds'=>30],
 'cool'=>['max'=>8,'max_minutes'=>90], 'rice'=>['max'=>8,'max_minutes'=>60],
 'delivery_cold'=>['max'=>5,'critical_max'=>8], 'delivery_frozen'=>['max'=>-18],
 'probe_ice'=>['min'=>-1,'max'=>1], 'probe_boiling'=>['min'=>99,'max'=>101]
 ];
}
function evaluate(string $kind, float $temp, int $hold, array $limits, ?float $elapsed=null): string {
 if (in_array($kind,['cool','rice'],true)) return $temp <= $limits['max'] && $elapsed !== null && $elapsed <= $limits['max_minutes'] ? 'pass' : 'fail';
 if (isset($limits['min']) && $temp < $limits['min']) return 'fail';
 if (isset($limits['hold_seconds']) && $hold < $limits['hold_seconds']) return 'fail';
 if (isset($limits['critical_max']) && $temp > $limits['critical_max']) return 'fail';
 if (isset($limits['max']) && $temp > $limits['max']) return isset($limits['critical_max']) ? 'warn' : 'fail';
 return 'pass';
}
function limits_label(array $l): string {
 $parts=[];
 if(isset($l['min'])) $parts[]='≥ '.(float)$l['min'].'°C';
 if(isset($l['max'])) $parts[]='≤ '.(float)$l['max'].'°C';
 if(isset($l['hold_seconds'])) $parts[]='for '.(int)$l['hold_seconds'].' sec';
 if(isset($l['max_minutes'])) $parts[]='within '.(int)$l['max_minutes'].' min';
 if(isset($l['critical_max'])) $parts[]='critical above '.(float)$l['critical_max'].'°C';
 return implode(' · ',$parts);
}
function kind_guidance(string $kind): string {
 return match($kind) {
 'fridge'=>'Check the actual temperature using the method in your plan. Above 5°C needs attention; above 8°C breaches the starting chilled-food limit. An air/display reading alone does not establish food safety.',
 'freezer','delivery_frozen'=>'Starting operating target −18°C or colder. Check food condition, packaging and supplier instructions; temperature alone does not prove food is suitable.',
 'hot'=>'Measure the food, not just the display. Below 63°C: isolate affected food and record action. Do not assume a two-hour exception applies when the time below the limit is unknown.',
 'cook'=>'Probe a representative coldest/thickest part with a clean, disinfected probe. Record the measured temperature and continuous time at that temperature.',
 'reheat'=>'Starting house standard 75°C for 30 seconds. Reheat only once; confirm this before saving. Check local requirements, especially Scotland, and use a validated process.',
 'rice'=>'Start the clock when cooking ends. Use shallow portions or an approved rapid-cooling method. Default house target: ≤8°C within 60 minutes. FSA advice is rapid cooling ideally within one hour, then refrigeration; this numeric target is a configurable house rule. Keep refrigerated and use within 24 hours; reheat once only.',
 'cool'=>'Start when cooking ends. Default house target ≤8°C within 90 minutes is an example to validate for your food and process, not a universal legal rule. Record the finish temperature and chilled-storage location.',
 'delivery_cold'=>'Check product temperature by your approved method, use-by date, packaging and supplier requirements. Reject or isolate unsatisfactory deliveries and record the outcome.',
 'probe_ice'=>'Use a correctly prepared melting-ice mixture. Starting tolerance is 0°C ±1°C. Clean the probe before and after checking; follow manufacturer instructions.',
 'probe_boiling'=>'Starting check 100°C ±1°C at normal atmospheric pressure. Boiling point changes with altitude and pressure. Follow manufacturer instructions and avoid scalds.',
 default=>''};
}
function parse_local(string $value, string $zone): DateTimeImmutable {
 $tz=new DateTimeZone($zone);
 $dt=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value,$tz);
 $err=DateTimeImmutable::getLastErrors();
 if (!$dt || ($err && ($err['warning_count'] || $err['error_count'])) || $dt->format('Y-m-d\TH:i')!==$value) throw new InvalidArgumentException('Enter a valid local date and time.');
 return $dt->setTimezone(new DateTimeZone('UTC'));
}
function validate_limits(string $kind, array $input): array {
 $shape=default_rules()[$kind]??throw new InvalidArgumentException('Unknown control.'); $out=[];
 foreach($shape as $key=>$default){
  $raw=$input[$key]??null;
  if(!is_scalar($raw)||!is_numeric($raw)) throw new InvalidArgumentException('Enter all limit values.');
  $v=(float)$raw;
  if(!is_finite($v)||$v < ($key==='hold_seconds'||$key==='max_minutes'?1:-60)||$v>($key==='hold_seconds'?3600:($key==='max_minutes'?1440:150))) throw new InvalidArgumentException('Limit is outside the allowed range.');
  if(in_array($key,['hold_seconds','max_minutes'],true)&&floor($v)!==$v) throw new InvalidArgumentException('Use whole seconds or minutes.');
  $out[$key]=$v;
 }
 if(isset($out['min'],$out['max'])&&$out['min']>$out['max']) throw new InvalidArgumentException('Minimum must not exceed maximum.');
 if(isset($out['critical_max'])&&$out['critical_max']<$out['max']) throw new InvalidArgumentException('Critical maximum must not be lower than the operating maximum.');
 return $out;
}
