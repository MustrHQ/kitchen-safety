<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/inc/domain.php';
$d=default_rules();$cases=[
 ['fridge',5,0,$d['fridge'],null,'pass'],['fridge',8,0,$d['fridge'],null,'warn'],['fridge',8.01,0,$d['fridge'],null,'fail'],
 ['hot',63,0,$d['hot'],null,'pass'],['hot',62.9,0,$d['hot'],null,'fail'],
 ['freezer',-18,0,$d['freezer'],null,'pass'],['freezer',-17.9,0,$d['freezer'],null,'fail'],
 ['cook',75,30,$d['cook'],null,'pass'],['cook',75,29,$d['cook'],null,'fail'],['cook',74.9,30,$d['cook'],null,'fail'],
 ['rice',8,0,$d['rice'],60,'pass'],['rice',8,0,$d['rice'],60.1,'fail'],['rice',8.1,0,$d['rice'],59,'fail'],
 ['probe_ice',-1,0,$d['probe_ice'],null,'pass'],['probe_ice',1.1,0,$d['probe_ice'],null,'fail']
];
foreach($cases as $i=>[$kind,$temp,$hold,$limits,$elapsed,$expected]){if(evaluate($kind,$temp,$hold,$limits,$elapsed)!==$expected){fwrite(STDERR,'Failed case '.($i+1).PHP_EOL);exit(1);}}
try{validate_limits('fridge',['max'=>9,'critical_max'=>8]);exit(1);}catch(InvalidArgumentException $e){}
try{parse_local('2026-02-30T12:00','Europe/London');exit(1);}catch(InvalidArgumentException $e){}
echo "17 domain assertions passed.\n";
