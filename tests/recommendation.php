<?php
require __DIR__.'/../src/port-manager/include/Recommendation.php';
use PortManager\Recommendation as R;
function check($value,$message) { if (!$value) throw new RuntimeException($message); echo "PASS $message\n"; }
check(R::ranges('5000, 5002-5004')===[[5000,5000],[5002,5004]],'reserved single ports and ranges');
foreach (['0','65536','6000-5000','5000,','oops'] as $invalid) {
    try {R::ranges($invalid);throw new LogicException('accepted invalid reservation');}
    catch (RuntimeException $e) {check(true,'reject invalid reservation '.$invalid);}
}
$s=['complete'=>true,'timestamp'=>'2026-10-04T12:00:00Z','ephemeral'=>[32768,60999],
    'rows'=>[['port'=>5002,'protocol'=>'udp','running'=>false,'name'=>'stopped'],['port'=>5003,'protocol'=>'tcp','running'=>true,'name'=>'running']]];
$r=R::result($s,[['start'=>5004,'end'=>5005,'reason'=>'system reserved']], [['port'=>5006,'protocol'=>'tcp','name'=>'connection']]);
check(array_slice($r['available'],0,3)===[5007,5008,5009],'ascending excludes common, both protocols, stopped, system reservation and non-listening socket');
check(!in_array(32768,$r['available'],true) && in_array(61000,$r['available'],true) && end($r['available'])===65535,'dynamic range excluded and upper boundary included');
check(isset($r['blocked']->{5002}) && count($r['reserved'])===2,'exclusion reasons preserve ranges without expanding response');
$s['complete']=false;
try {R::result($s,[],[]);throw new LogicException('accepted incomplete scan');}
catch (RuntimeException $e) {check(true,'incomplete scan blocks strict recommendation');}
$root=sys_get_temp_dir().'/pm-reservation-test-'.bin2hex(random_bytes(6));
foreach (['/proc/sys/net/ipv4','/boot/config/plugins/port-manager','/etc/libvirt/qemu'] as $dir) mkdir($root.$dir,0700,true);
try {
    file_put_contents($root.'/proc/sys/net/ipv4/ip_local_reserved_ports','5100,5200-5202');
    file_put_contents($root.'/boot/config/ident.cfg',"PORT=5007\nPORTSSH=5008\n");
    file_put_contents($root.'/boot/config/plugins/port-manager/reserved-ports.conf',"5300-5301 # planned service\n");
    file_put_contents($root.'/etc/libvirt/qemu/stopped.xml',"<domain><devices><graphics type='vnc' port='5400' autoport='yes' websocket='-1'/></devices></domain>");
    file_put_contents($root.'/etc/libvirt/qemu.conf',"remote_display_port_min = 5905\nremote_display_port_max = 5910\n#remote_websocket_port_min = 5700\n");
    $ranges=R::collect($root);
    check(in_array(['start'=>5008,'end'=>5008,'reason'=>'SSH 配置预留'],$ranges,true),'inactive system service configuration reserved');
    check(in_array(['start'=>5300,'end'=>5301,'reason'=>'用户预留'],$ranges,true),'custom reservation file comments supported');
    check(in_array(['start'=>5400,'end'=>5400,'reason'=>'虚拟机显示配置预留'],$ranges,true),'stopped VM explicit display reserved');
    check(in_array(['start'=>5905,'end'=>5910,'reason'=>'虚拟机显示自动分配预留'],$ranges,true),'VM configured automatic range');
    check(in_array(['start'=>5700,'end'=>65535,'reason'=>'虚拟机显示自动分配预留'],$ranges,true),'VM websocket default automatic range');
    unlink($root.'/proc/sys/net/ipv4/ip_local_reserved_ports');
    try {R::collect($root);throw new LogicException('accepted missing kernel data');}
    catch (RuntimeException $e) {check(true,'unreadable reservation data blocks recommendations');}
} finally {
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $item) {if ($item->isDir()) rmdir($item->getPathname());else unlink($item->getPathname());}
    rmdir($root);
}
