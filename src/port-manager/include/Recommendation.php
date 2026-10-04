<?php
declare(strict_types=1);
namespace PortManager;

/** Strict recommendations for the native container editor; does not change scan results. */
final class Recommendation {
    public static function ranges(string $text): array {
        $result=[];
        if (trim($text)==='') return $result;
        foreach (explode(',',trim($text)) as $entry) {
            if (!preg_match('/^\s*(\d+)(?:-(\d+))?\s*$/',$entry,$m)) throw new \RuntimeException('预留端口格式无效');
            $start=(int)$m[1];$end=isset($m[2])?(int)$m[2]:$start;
            if ($start<1 || $end>65535 || $start>$end) throw new \RuntimeException('预留端口范围无效');
            $result[]=[$start,$end];
        }
        return $result;
    }
    public static function common(): array {
        // Deliberate avoidance list, not the IANA registered-port registry.
        return [5000=>'Synology / registry',5001=>'Synology HTTPS',5055=>'Overseerr / Jellyseerr',
            5432=>'PostgreSQL',5672=>'RabbitMQ',5683=>'CoAP',5800=>'noVNC',5900=>'VNC',
            6379=>'Redis',6443=>'Kubernetes',6881=>'BitTorrent',6969=>'BitTorrent tracker',
            7474=>'Neo4j',7687=>'Neo4j Bolt',7878=>'Radarr',8000=>'常用 Web 服务',8001=>'常用 Web 服务',
            8006=>'Proxmox',8008=>'常用 Web 服务',8080=>'常用 Web 服务',8081=>'常用 Web 服务',
            8088=>'常用 Web 服务',8090=>'常用 Web 服务',8096=>'Jellyfin / Emby',
            8112=>'Deluge',8123=>'Home Assistant',8181=>'常用 Web 服务',8200=>'Vault',
            8265=>'Tdarr',8388=>'Shadowsocks',8443=>'常用 HTTPS 服务',8686=>'Lidarr',
            8834=>'Nessus',8888=>'常用 Web 服务',8989=>'Sonarr',9000=>'Portainer / MinIO',
            9001=>'MinIO console',9090=>'Prometheus / Transmission',9091=>'Transmission',
            9117=>'Jackett',9200=>'Elasticsearch',9300=>'Elasticsearch transport',
            9443=>'Portainer HTTPS',9696=>'Prowlarr',10000=>'Webmin',11211=>'Memcached',
            11434=>'Ollama',15672=>'RabbitMQ console',25565=>'Minecraft',27017=>'MongoDB',
            32400=>'Plex',32410=>'Plex discovery',32412=>'Plex discovery',32413=>'Plex discovery',
            32414=>'Plex discovery',32469=>'Plex DLNA'];
    }
    public static function collect(string $root=''): array {
        $reserved=[];
        $add=function(int $start,int $end,string $reason) use (&$reserved) {
            $reserved[]=['start'=>$start,'end'=>$end,'reason'=>$reason];
        };
        $kernel=@file_get_contents($root.'/proc/sys/net/ipv4/ip_local_reserved_ports');
        if ($kernel===false) throw new \RuntimeException('无法读取内核预留端口');
        foreach (self::ranges($kernel) as [$start,$end]) $add($start,$end,'内核预留');
        $ident=@parse_ini_file($root.'/boot/config/ident.cfg');
        if ($ident===false) throw new \RuntimeException('无法读取 Unraid 系统端口配置');
        foreach (['PORT'=>'Unraid HTTP','PORTSSL'=>'Unraid HTTPS','PORTSSH'=>'SSH','PORTTELNET'=>'Telnet'] as $key=>$reason) {
            if (!isset($ident[$key])) continue;
            foreach (self::ranges((string)$ident[$key]) as [$start,$end]) $add($start,$end,$reason.' 配置预留');
        }
        // Unraid's Web Terminal uses a fixed port, even if its process is currently stopped.
        $add(7681,7681,'Unraid Web Terminal');
        // Read persistent VM graphics settings, including stopped VMs.
        $autoDisplay=false;$autoWebsocket=false;
        foreach (glob($root.'/etc/libvirt/qemu/*.xml') ?: [] as $file) {
            $xml=@simplexml_load_file($file, 'SimpleXMLElement', LIBXML_NONET);
            if ($xml===false) throw new \RuntimeException('无法读取虚拟机端口配置');
            foreach ($xml->xpath('/domain/devices/graphics') as $graphics) {
                if (!in_array((string)$graphics['type'],['vnc','spice'],true)) continue;
                foreach (['port','tlsPort','websocket'] as $key) {
                    $port=(int)$graphics[$key];
                    if ($port>0 && $port<=65535) $add($port,$port,'虚拟机显示配置预留');
                }
                if ((string)$graphics['autoport']==='yes') $autoDisplay=true;
                if ((string)$graphics['websocket']==='-1') $autoWebsocket=true;
            }
        }
        if ($autoDisplay || $autoWebsocket) {
            $config=@file_get_contents($root.'/etc/libvirt/qemu.conf');
            if ($config===false) throw new \RuntimeException('无法读取虚拟机自动端口范围');
            foreach (['remote_display'=>[$autoDisplay,5900], 'remote_websocket'=>[$autoWebsocket,5700]] as $prefix=>$settings) {
                if (!$settings[0]) continue;
                $bounds=[$settings[1],65535];
                foreach (['min','max'] as $index=>$suffix) {
                    if (preg_match('/^\s*'.$prefix.'_port_'.$suffix.'\s*=\s*(\d+)\s*(?:#.*)?$/m',$config,$m)) $bounds[$index]=(int)$m[1];
                }
                foreach (self::ranges($bounds[0].'-'.$bounds[1]) as [$start,$end]) $add($start,$end,'虚拟机显示自动分配预留');
            }
        }
        require_once __DIR__.'/RecommendationSettings.php';
        $settings=Settings::read($root);
        foreach (Settings::reservations($settings['reserved']) as [$start,$end]) $add($start,$end,'用户预留');
        return $reserved;
    }
    public static function result(array $snapshot,array $reserved,array $sockets): array {
        if (!$snapshot['complete']) throw new \RuntimeException('采集不完整，不能推荐：'.implode('；',$snapshot['errors'] ?? []));
        $blocked=[];
        $add=function(int $port,string $reason) use (&$blocked) {$blocked[$port][]=$reason;};
        foreach ($snapshot['rows'] as $row) $add($row['port'],($row['running']?'占用：':'容器预留：').$row['name'].' / '.strtoupper($row['protocol']));
        foreach ($sockets as $row) $add($row['port'],'系统套接字：'.$row['name'].' / '.strtoupper($row['protocol']));
        foreach (self::common() as $port=>$service) $add($port,'常用端口避让：'.$service);
        [$start,$end]=$snapshot['ephemeral'];
        $reserved[]=['start'=>$start,'end'=>$end,'reason'=>'系统动态端口范围'];
        $used=array_fill_keys(array_keys($blocked),true);
        foreach ($reserved as $range) for ($p=$range['start'];$p<=$range['end'];$p++) $used[$p]=true;
        $available=[];
        for ($p=5000;$p<=65535;$p++) if (!isset($used[$p])) $available[]=$p;
        foreach ($blocked as &$reasons) $reasons=array_values(array_unique($reasons));unset($reasons);
        return ['available'=>$available,'blocked'=>(object)$blocked,'timestamp'=>$snapshot['timestamp'],
            'reserved'=>$reserved,'complete'=>true,'limitations'=>['停止的 host 网络容器和未纳入采集的服务配置可能有额外预留，请加入自定义预留清单。']];
    }
}
