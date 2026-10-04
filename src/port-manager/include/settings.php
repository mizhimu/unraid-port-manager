<?php
declare(strict_types=1);
require_once __DIR__.'/RecommendationSettings.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    $method=$_SERVER['REQUEST_METHOD'] ?? '';
    if ($method==='GET') $settings=\PortManager\Settings::read();
    elseif ($method==='POST') {
        // Unraid's PHP prepend validates and removes csrf_token before this endpoint runs.
        $enabled=$_POST['enabled'] ?? null;$reserved=$_POST['reserved'] ?? null;
        if (!in_array($enabled,['0','1'],true) || !is_string($reserved)) throw new InvalidArgumentException('推荐设置格式无效');
        $settings=\PortManager\Settings::save($enabled==='1',$reserved);
    } else { http_response_code(405);header('Allow: GET, POST');throw new InvalidArgumentException('不支持此请求方法'); }
    echo json_encode($settings,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    if (http_response_code()===200) http_response_code($e instanceof InvalidArgumentException?400:503);
    echo json_encode(['error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
}
