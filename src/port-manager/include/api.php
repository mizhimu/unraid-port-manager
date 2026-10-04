<?php
declare(strict_types=1);
require_once __DIR__.'/Scanner.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { http_response_code(405); header('Allow: GET'); throw new InvalidArgumentException('只支持 GET'); }
    $action=$_GET['action'] ?? 'scan';
    if (!in_array($action,['scan','available'],true)) throw new InvalidArgumentException('未知操作');
    $start=8000;$end=9000;$protocol=$_GET['protocol'] ?? 'both';
    if ($action==='available') {
        foreach (['start','end'] as $key) {
            $value=filter_var($_GET[$key] ?? ($key==='start'?8000:9000),FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);
            if ($value===false) throw new InvalidArgumentException('端口必须是 1–65535 的整数');
            if ($key==='start') $start=$value; else $end=$value;
        }
        if ($start>$end || !in_array($protocol,['tcp','udp','both'],true)) throw new InvalidArgumentException('范围或协议无效');
    }
    $snapshot=\PortManager\Scanner::scan();
    if ($action==='available') $snapshot['protocolAvailability']=\PortManager\Scanner::protocolAvailability($snapshot,$start,$end);
    if ($action==='available') $snapshot['available']=\PortManager\Scanner::available($snapshot,$start,$end,$protocol);
    echo json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    if (http_response_code()===200) http_response_code($e instanceof InvalidArgumentException?400:503);
    echo json_encode(['error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
}
