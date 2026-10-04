<?php
declare(strict_types=1);
namespace PortManager;
require_once __DIR__.'/Recommendation.php';
final class Settings {
    private const MARKER = '# port-manager-recommendation: off';
    public static function read(string $root=''): array {
        $path=$root.'/boot/config/plugins/port-manager/reserved-ports.conf';
        if (!file_exists($path)) return ['enabled'=>true,'reserved'=>''];
        $text=@file_get_contents($path);
        if ($text===false) throw new \RuntimeException('无法读取推荐设置');
        $enabled=true;$lines=[];
        foreach (preg_split('/\R/',$text) as $line) {
            if (trim($line)===self::MARKER) $enabled=false;
            else $lines[]=$line;
        }
        return ['enabled'=>$enabled,'reserved'=>rtrim(implode("\n",$lines))];
    }
    public static function reservations(string $text): array {
        if (strlen($text)>32768) throw new \InvalidArgumentException('预留清单不能超过 32 KB');
        $ranges=[];
        foreach (preg_split('/\R/',$text) as $index=>$line) {
            if (strpos($line,'port-manager-recommendation:')!==false) throw new \InvalidArgumentException('第 '.($index+1).' 行包含保留的配置标记');
            try { $ranges=array_merge($ranges,Recommendation::ranges(trim(explode('#',$line,2)[0]))); }
            catch (\RuntimeException $e) { throw new \InvalidArgumentException('第 '.($index+1).' 行：'.$e->getMessage()); }
        }
        return $ranges;
    }
    public static function save(bool $enabled,string $text,string $root=''): array {
        $text=trim(str_replace(["\r\n","\r"],"\n",$text));
        self::reservations($text);
        $dir=$root.'/boot/config/plugins/port-manager';
        if (!is_dir($dir) && !@mkdir($dir,0755,true) && !is_dir($dir)) throw new \RuntimeException('无法创建插件设置目录');
        $temporary=@tempnam($dir,'.reserved-');
        if ($temporary===false) throw new \RuntimeException('无法创建设置临时文件');
        try {
            $content=($enabled?'':self::MARKER."\n").$text."\n";
            if (@file_put_contents($temporary,$content,LOCK_EX)!==strlen($content) || !@chmod($temporary,0644) || !@rename($temporary,$dir.'/reserved-ports.conf')) throw new \RuntimeException('无法保存推荐设置');
        } finally { if (file_exists($temporary)) @unlink($temporary); }
        return ['enabled'=>$enabled,'reserved'=>$text];
    }
}
