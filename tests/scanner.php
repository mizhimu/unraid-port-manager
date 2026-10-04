<?php
require __DIR__.'/../src/port-manager/include/Scanner.php';
use PortManager\Scanner as S;
function check($value, $message) { if (!$value) throw new RuntimeException($message); echo "PASS $message\n"; }
$l=S::parseSs('tcp LISTEN 0 4096 0.0.0.0:8080 0.0.0.0:* users:(("docker-proxy",pid=123,fd=4))'."\n".'udp UNCONN 0 0 [::]:5353 [::]:* users:(("avahi-daemon",pid=5,fd=6))');
check(count($l)===2 && $l[1]['address']==='::' && $l[0]['pid']===123,'ss IPv4 IPv6 UDP process parsing');
$c=['Id'=>str_repeat('a',64),'Name'=>'/demo','State'=>['Running'=>true],'HostConfig'=>['NetworkMode'=>'bridge','PortBindings'=>['80/tcp'=>[['HostPort'=>'8080','HostIp'=>'']]]],'NetworkSettings'=>['Ports'=>[]]];
[$b,$h]=S::dockerRows([$c]);
$r=S::resolve($l,$b);check(count($r)===2 && $r[1]['status']==='occupied','docker-proxy deduplicated');
$c['Id']=str_repeat('b',64);$c['Name']='/stopped';$c['State']['Running']=false;
[$b2]=S::dockerRows([$c]);$r=S::resolve([],$b2);check($r[0]['status']==='configured','stopped binding reserved');
$r=S::resolve([] ,array_merge($b,$b2));check($r[0]['risk']==='conflict','overlapping Docker ownership risk');
$b2[0]['address']='192.168.1.2';$b[0]['address']='192.168.1.3';check(S::resolve([],array_merge($b,$b2))[0]['risk']!=='conflict','distinct IPv4 bindings coexist');
$b[0]['address']='::';$b2[0]['address']='0.0.0.0';check(S::resolve([],array_merge($b,$b2))[0]['risk']==='warning','dual stack warning');
$c['HostConfig']['NetworkMode']='host';[$b,$h]=S::dockerRows([$c]);check(!$b,'host EXPOSE is not host reservation');
$s=['complete'=>true,'ephemeral'=>[8002,8003],'rows'=>[['protocol'=>'tcp','port'=>8001]]];check(S::available($s,8000,8004,'tcp')===[8000,8004],'recommend excludes assigned and ephemeral');
$s['complete']=false;try {S::available($s,8000,8004,'tcp');throw new LogicException('must fail');}catch(RuntimeException $e){check(true,'partial data blocks recommendation');}
try {S::parseSs('unexpected format');throw new LogicException('must fail');}catch(RuntimeException $e){check(true,'invalid ss rejects incomplete scan');}
$duplicates=S::parseSs('udp UNCONN 0 0 0.0.0.0:3702 0.0.0.0:* users:(("wsdd2",pid=42,fd=1))'."\n".'udp UNCONN 0 0 0.0.0.0:3702 0.0.0.0:* users:(("wsdd2",pid=42,fd=2))');
$resolved=S::resolve($duplicates,[]);check($resolved[0]['status']==='occupied' && $resolved[1]['status']==='occupied','same process reused sockets are normal');
$groups=S::groups($resolved);check(count($groups)===1 && $groups[0]['sockets']===2 && count($groups[0]['addresses'])===1,'duplicate socket records grouped with detail');
check(S::summary($resolved)['occupied']===1 && S::summary($resolved)['risks']===0,'summary counts unique protocol and port');
$duplicates[1]['owner']='pid:99';$duplicates[1]['pid']=99;$duplicates[1]['processes']=[['name'=>'wsdd2','pid'=>99]];
check(S::resolve($duplicates,[])[0]['risk']==='warning','same name different process remains a risk');
$dual=S::parseSs('tcp LISTEN 0 4096 0.0.0.0:111 0.0.0.0:* users:(("rpcbind",pid=7,fd=1))'."\n".'tcp LISTEN 0 4096 [::]:111 [::]:* users:(("rpcbind",pid=7,fd=2))');
check(S::summary(S::resolve($dual,[]))['risks']===0,'same process IPv4 IPv6 does not imply abnormality');
$sample=['complete'=>true,'ephemeral'=>[40000,50000],'rows'=>[['port'=>8001,'protocol'=>'tcp'],['port'=>8002,'protocol'=>'udp'],['port'=>8003,'protocol'=>'tcp'],['port'=>8003,'protocol'=>'udp']]];
check(S::available($sample,8000,8003,'both')===[8000],'joint recommendation requires both protocols free');
$availability=S::protocolAvailability($sample,8000,8003);check(array_column($availability,'kind','port')===[8000=>'both',8001=>'udp',8002=>'tcp'],'joint result distinguishes both TCP-only UDP-only');
$space=S::parseSs('tcp LISTEN 0 4096 0.0.0.0:9000 0.0.0.0:* users:(("node /usr/local",pid=15,fd=4))');check($space[0]['name']==='node /usr/local','process name with spaces preserved');
check(S::describe($space[0])['purpose']==='Node.js 服务','runtime named without guessing specific app');
$space[0]['name']='unrecognized';check(S::describe($space[0])['purpose']==='用途未识别','unknown service not guessed by port number');
$app=$space[0];$app['source']='Docker';$app['kind']='binding';$app['image']='jellyfin/jellyfin:latest';check(S::describe($app)['purpose']==='Jellyfin 媒体服务器','container purpose comes from exact image repository');
$vm=$space[0];$vm['name']='qemu-system-x86';$vm['vmName']='FnOS';check(S::describe($vm)['purpose']==='虚拟机：FnOS','VM purpose includes verified libvirt name');

$overlap=S::resolve([],array_merge($b2,$b2));check($overlap[0]['status']==='configured','port reservation status never replaced by risk');
$rows=S::parseSs('tcp LISTEN 0 10 0.0.0.0:443 0.0.0.0:* users:(("nginx",pid=12,fd=1),("nginx",pid=13,fd=1))');check(count($rows[0]['processes'])===2,'all shared socket process IDs preserved');
$row=$rows[0];$row['kind']='binding';$row['source']='Docker';$row['pid']=0;check(S::describe($row)['pidDescription']==='配置记录，不适用监听进程 PID','configuration PID not misleadingly missing');
$one=$b2[0];$one['address']='0.0.0.0';$two=$one;$two['mapping']='8080 → 81';$sameContainer=S::resolve([],[$one,$two]);check($sameContainer[0]['risk']==='conflict' && $sameContainer[0]['status']==='configured','same container different targets still checked for startup risk');

$full=['complete'=>true,'rows'=>[],'ephemeral'=>[32768,60999]];
check(count(S::protocolAvailability($full,5000,9000))===4001,'entire requested availability range returned beyond 20 candidates');
$full['rows']=[['protocol'=>'tcp','port'=>5000],['protocol'=>'udp','port'=>5001],['protocol'=>'tcp','port'=>5002],['protocol'=>'udp','port'=>5002]];
$a=S::protocolAvailability($full,5000,9000);$k=array_count_values(array_column($a,'kind'));
check($k['both']===3998 && $k['tcp']===1 && $k['udp']===1,'full range counts distinguish joint and single protocol availability');
check(count(S::protocolAvailability(['complete'=>true,'rows'=>[],'ephemeral'=>[32768,60999]],1,65535))===36280,'full port range retains reserved and ephemeral exclusions');

check(count(S::available(['complete'=>true,'rows'=>[],'ephemeral'=>[32768,60999]],5000,9000,'both'))===4001,'available API field also covers the entire range');

$independent=['Id'=>str_repeat('c',64),'Name'=>'/independent','State'=>['Running'=>true],
    'HostConfig'=>['NetworkMode'=>'br0','PortBindings'=>['80/tcp'=>[['HostPort'=>'8080','HostIp'=>'']]]],
    'NetworkSettings'=>['Ports'=>[]]];
foreach (['macvlan','ipvlan'] as $driver) {
    [$independentRows]=S::dockerRows([$independent],['br0'=>$driver]);
    check(!$independentRows,$driver.' configuration without actual publishing does not occupy host');
    $independent['State']['Running']=false;
    [$independentRows]=S::dockerRows([$independent],['br0'=>$driver]);
    check(!$independentRows,$driver.' stopped configuration does not reserve host');
    $independent['State']['Running']=true;
}
[$bridgeRows]=S::dockerRows([$independent],['br0'=>'bridge']);
check(count($bridgeRows)===1,'custom bridge network keeps host port configuration');
$independent['NetworkSettings']['Ports']=['80/tcp'=>[['HostPort'=>'8081','HostIp'=>'127.0.0.1']]];
[$actualRows]=S::dockerRows([$independent],['br0'=>'macvlan']);
check(count($actualRows)===1 && $actualRows[0]['port']===8081,'actual published port remains recorded');
$shared=S::parseSs('tcp LISTEN 0 10 0.0.0.0:443 0.0.0.0:* users:(("nginx",pid=12,fd=1),("nginx",pid=13,fd=1))'."\n".'tcp LISTEN 0 10 [::]:443 [::]:* users:(("nginx",pid=12,fd=2),("nginx",pid=14,fd=2))');
$sharedGroups=S::groups(S::resolve($shared,[]));
check(count($sharedGroups)===1 && array_column($sharedGroups[0]['processes'],'pid')===[12,13,14],'grouped sockets retain all shared processes without duplicates');
