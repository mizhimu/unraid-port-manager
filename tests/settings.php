<?php
require __DIR__.'/../src/port-manager/include/RecommendationSettings.php';
use PortManager\Settings as S;
function check($value,$message) {if (!$value) throw new RuntimeException($message);echo "PASS $message\n";}
$root=sys_get_temp_dir().'/pm-settings-test-'.bin2hex(random_bytes(6));
try {
    check(S::read($root)===['enabled'=>true,'reserved'=>''],'missing settings defaults enabled without writing');
    S::save(false,"5500 # host service\r\n5600-5610,6200",$root);
    $read=S::read($root);
    check(!$read['enabled'] && $read['reserved']==="5500 # host service\n5600-5610,6200",'disabled state and comments round trip atomically');
    check(S::reservations($read['reserved'])===[[5500,5500],[5600,5610],[6200,6200]],'UI reservation text uses existing range parser');
    foreach (['6000-5000','oops',str_repeat('5',32769),'# port-manager-recommendation: off'] as $invalid) {
        try {S::save(true,$invalid,$root);throw new LogicException('accepted invalid settings');}
        catch (InvalidArgumentException $e) {check(S::read($root)===$read,'invalid save preserves prior settings');}
    }
    S::save(true,'',$root);
    check(S::read($root)===['enabled'=>true,'reserved'=>''],'enable and clear reservations');
    $path=$root.'/boot/config/plugins/port-manager/reserved-ports.conf';
    file_put_contents($path,"5500 # manually created list\n");
    check(S::read($root)===['enabled'=>true,'reserved'=>'5500 # manually created list'],'existing reservation file stays editable');
    check(count(glob(dirname($path).'/.reserved-*'))===0,'atomic saves leave no temporary files');
} finally {
    if (is_dir($root)) {
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $item) {if ($item->isDir()) rmdir($item->getPathname());else unlink($item->getPathname());}rmdir($root);
    }
}
