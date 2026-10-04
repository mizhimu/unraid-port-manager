<?php
declare(strict_types=1);
namespace PortManager;
final class Scanner {
    public static function command(string $command): string {
        $lines = []; $code = 0;
        exec('timeout 12 ' . $command . ' 2>/dev/null', $lines, $code);
        if ($code !== 0) throw new \RuntimeException('采集命令失败或超时：' . explode(' ', $command)[0]);
        return implode("\n", $lines);
    }
    public static function parseSs(string $text): array {
        $rows = [];
        foreach (preg_split('/\R/', trim($text)) as $line) {
            if ($line === '') continue;
            $parts = preg_split('/\s+/', trim($line), 7);
            if (count($parts) < 6 || !in_array($parts[0], ['tcp','udp'], true)) throw new \RuntimeException('无法解析 ss 输出');
            if (!preg_match('/^(.*):(\d+)$/', $parts[4], $m)) throw new \RuntimeException('无法解析监听地址');
            $address = trim($m[1], '[]');
            $process = $parts[6] ?? '';
            preg_match('/pid=(\d+)/', $process, $pid);
            preg_match_all('/\"([^\"]+)\",pid=(\d+)/',$process,$users,PREG_SET_ORDER);
            $processes=array_map(fn($u)=>['name'=>$u[1],'pid'=>(int)$u[2]],$users);
            preg_match('/\("([^"\n]+)"/', $process, $name);
            $rows[] = ['protocol'=>$parts[0], 'port'=>(int)$m[2], 'address'=>$address,
                'processes'=>$processes, 'source'=>'System', 'name'=>$name[1] ?? '未知进程', 'pid'=>(int)($pid[1] ?? 0),
                'kind'=>'listener', 'running'=>true, 'mapping'=>'', 'owner'=>isset($pid[1]) ? 'pid:'.$pid[1] : ''];
        }
        return $rows;
    }
    public static function dockerRows(array $containers, array $networkDrivers = []): array {
        $rows = []; $hosts = [];
        foreach ($containers as $c) {
            $id = $c['Id']; $name = ltrim($c['Name'], '/'); $running = (bool)($c['State']['Running'] ?? false);
            $mode = $c['HostConfig']['NetworkMode'] ?? '';
            if ($mode === 'host') { if ($running) $hosts[] = ['id'=>$id, 'name'=>$name]; continue; }
            // Macvlan/ipvlan container IP ports do not reserve NAS host ports.
            foreach ((in_array($networkDrivers[$mode] ?? $mode, ['macvlan','ipvlan'], true) ? [] : ($c['HostConfig']['PortBindings'] ?? [])) as $target=>$bindings) {
                [$port, $protocol] = explode('/', $target);
                foreach ($bindings ?? [] as $binding) {
                    $host = (int)($binding['HostPort'] ?? 0);
                    if (!$host) continue; // Docker-assigned ports are read below.
                    $rows[] = ['protocol'=>$protocol,'port'=>$host,'address'=>($binding['HostIp'] ?? '') ?: '0.0.0.0',
                        'image'=>$c['Config']['Image'] ?? '', 'source'=>'Docker','name'=>$name,'pid'=>0,'kind'=>'binding','running'=>$running,
                        'mapping'=>"$host → $port",'owner'=>$id];
                }
            }
            if ($running) foreach (($c['NetworkSettings']['Ports'] ?? []) as $target=>$bindings) {
                [$port,$protocol] = explode('/', $target);
                foreach ($bindings ?? [] as $b) {
                    $host = (int)$b['HostPort']; $address = $b['HostIp'] ?: '0.0.0.0';
                    $exists = false;
                    foreach ($rows as $r) if ($r['owner']===$id && $r['port']===$host && $r['protocol']===$protocol && $r['address']===$address) $exists=true;
                    if (!$exists) $rows[]=['protocol'=>$protocol,'port'=>$host,'address'=>$address,'image'=>$c['Config']['Image'] ?? '', 'source'=>'Docker','name'=>$name,'pid'=>0,'kind'=>'binding','running'=>true,'mapping'=>"$host → $port",'owner'=>$id];
                }
            }
        }
        return [$rows,$hosts];
    }
    public static function overlap(string $a, string $b): bool {
        if ($a === $b || $a === '*' || $b === '*') return true;
        $a6 = strpos($a, ':') !== false; $b6 = strpos($b, ':') !== false;
        if ($a6 !== $b6) return false; // Dual-stack ambiguity is reported separately.
        return in_array($a,['0.0.0.0','::'],true) || in_array($b,['0.0.0.0','::'],true);
    }
    public static function resolve(array $listeners, array $bindings): array {
        // A docker-proxy listener is evidence for an existing binding, not a second owner.
        $rows = $bindings;
        foreach ($listeners as $l) {
            $matched = false;
            if ($l['name']==='docker-proxy') foreach ($bindings as $b) {
                if ($b['running'] && $l['port']===$b['port'] && $l['protocol']===$b['protocol'] && self::overlap($l['address'],$b['address'])) $matched=true;
            }
            if (!$matched) $rows[]=$l;
        }
        foreach ($rows as &$r) {
            $r['status']=$r['running'] ? 'occupied' : 'configured'; $r['risk']='none'; $r['notes']=[];
            if ($r['kind']==='binding' && $r['running']) $r['notes'][]='运行容器发布端口（可能由 NAT 转发，无 ss 监听）';
        } unset($r);
        for ($i=0;$i<count($rows);$i++) for ($j=$i+1;$j<count($rows);$j++) {
            $a=$rows[$i];$b=$rows[$j];
            if ($a['port']!==$b['port'] || $a['protocol']!==$b['protocol']) continue;
            $sharedPid=array_intersect(array_column($a['processes'] ?? [],'pid'),array_column($b['processes'] ?? [],'pid'));
            if ($sharedPid && $a['kind']==='listener' && $b['kind']==='listener') continue;
            if ($a['owner']!=='' && $a['owner']===$b['owner'] && !($a['kind']==='binding' && $b['kind']==='binding' && $a['mapping']!==$b['mapping'])) continue;
            if (self::overlap($a['address'],$b['address'])) {
                // SO_REUSEPORT and multi-process listeners may intentionally coexist.
                $status=($a['kind']==='listener' && $b['kind']==='listener') ? 'warning' : 'conflict';
                foreach ([$i,$j] as $k) if ($rows[$k]['risk']!=='conflict') $rows[$k]['risk']=$status;
                $rows[$i]['notes'][]='与 '.$b['name'].' 的 '.$b['address'].' 绑定重叠';
                $rows[$j]['notes'][]='与 '.$a['name'].' 的 '.$a['address'].' 绑定重叠';
            } elseif ((strpos($a['address'],':')!==false)!==(strpos($b['address'],':')!==false) && ($a['address']==='::' || $b['address']==='::' || $a['address']==='*' || $b['address']==='*')) {
                foreach ([$i,$j] as $k) { if ($rows[$k]['risk']!=='conflict') $rows[$k]['risk']='warning'; $rows[$k]['notes'][]='IPv4/IPv6 双栈重叠风险'; }
            }
        }
        foreach ($rows as &$row) $row['notes']=array_values(array_unique($row['notes'])); unset($row);
        usort($rows,fn($a,$b)=>[$a['port'],$a['protocol'],$a['name']]<=>[$b['port'],$b['protocol'],$b['name']]);
        return $rows;
    }
    public static function groups(array $rows): array {
        $groups=[]; $rank=['none'=>0,'warning'=>1,'conflict'=>2];
        foreach ($rows as $r) {
            $owner=$r['owner'] ?: $r['name'];
            $key=$r['protocol'].':'.$r['port'].':'.$r['source'].':'.$owner.':'.$r['mapping'];
            if (!isset($groups[$key])) {
                $groups[$key]=$r; $groups[$key]['addresses']=[]; $groups[$key]['sockets']=0; $groups[$key]['processes']=[];
            }
            $g=&$groups[$key];
            $g['addresses'][]=$r['address']; $g['sockets']++;
            $g['notes']=array_merge($g['notes'],$r['notes']);
            foreach ($r['processes'] ?? [] as $process) $g['processes'][$process['pid'].':'.$process['name']]=$process;
            if ($rank[$r['risk']]>$rank[$g['risk']]) $g['risk']=$r['risk'];
            unset($g);
        }
        foreach ($groups as &$g) {
            $g['processes']=array_values($g['processes']);
            $g['addresses']=array_values(array_unique($g['addresses']));
            $g['notes']=array_values(array_unique($g['notes']));
        } unset($g);
        return array_values($groups);
    }
    public static function summary(array $rows): array {
        $occupied=[];$configured=[];$risks=[];$docker=[];
        foreach ($rows as $r) {
            $key=$r['protocol'].':'.$r['port'];
            if ($r['running']) $occupied[$key]=true; else $configured[$key]=true;
            if (in_array($r['risk'],['warning','conflict'],true)) $risks[$key]=true;
            if ($r['source']==='Docker') $docker[$r['owner'] ?: $r['name']]=true;
        }
        return ['occupied'=>count($occupied),'configured'=>count($configured),'risks'=>count($risks),'containers'=>count($docker)];
    }
    public static function describe(array $row): array {
        $name=$row['name'];
        $services=[
            'nginx'=>['网页管理服务','提供 HTTP / HTTPS 网页访问'],
            'sshd'=>['SSH 远程管理','用于终端登录与文件传输'],
            'smbd'=>['SMB 文件共享','供 Mac / Windows 访问 NAS 共享文件'],
            'nmbd'=>['SMB 网络发现','帮助局域网设备发现文件共享'],
            'nfs'=>['NFS 文件共享','内核提供的 NFS 文件共享监听'],
            'nlockmgr'=>['NFS 文件锁服务','管理 NFS 客户端的共享文件锁'],
            'rpcbind'=>['RPC 服务目录','为 NFS 等 RPC 服务提供端口查询'],
            'rpc.mountd'=>['NFS 挂载服务','处理 NFS 文件共享的挂载请求'],
            'rpc.statd'=>['NFS 锁状态服务','处理 NFS 文件锁状态与恢复'],
            'avahi-daemon'=>['局域网服务发现','通过 mDNS 广播与发现服务'],
            'wsdd2'=>['Windows 设备发现','让 NAS 出现在 Windows 网络设备列表'],
            'dnsmasq'=>['DNS / DHCP 服务','为网络提供域名解析与地址分配'],
            'dhcpcd'=>['网络地址客户端','获取与维护网卡的动态地址'],
            'ntpd'=>['系统时间同步','与时间服务器同步 NAS 时钟'],
            'apcupsd'=>['UPS 电源监控','读取不间断电源状态'],
            'libvirtd'=>['虚拟机管理服务','管理虚拟机与虚拟网络'],
            'mihomo'=>['Mihomo 代理服务','提供代理、DNS 或控制接口，具体功能取决于配置'],
            'tailscaled'=>['Tailscale 网络服务','维护 Tailscale 虚拟网络连接'],
        ];
        $purpose='用途未识别';$description='未取得足够证据，未按端口号推测用途';$evidence='未识别';
        if ($row['source']==='Docker') {
            $purpose=$row['kind']==='binding'?'容器发布端口':'容器直接监听';
            $description=$row['running']?'该容器正在使用宿主机端口':'该容器已停止，但启动时会申请这个端口';
            $evidence='Docker 配置 / 进程';
            $repository=preg_replace('/:[^\/]+$/','',explode('@',$row['image'] ?? '')[0]);
            $apps=[
                'emby/embyserver'=>'Emby 媒体服务器','jellyfin/jellyfin'=>'Jellyfin 媒体服务器',
                'vaultwarden/server'=>'密码库服务','ghcr.io/swingmx/swingmusic'=>'音乐播放器',
                'mtphotos/mt-photos'=>'照片管理服务','soulteary/flare'=>'书签导航页',
                'ghcr.io/advplyr/audiobookshelf'=>'有声书媒体库','linuxserver/pairdrop'=>'局域网文件分享',
                'lanol/filecodebox'=>'文件分享服务','pretty66/iptables-web'=>'防火墙管理网页',
                'lscr.io/linuxserver/nginx'=>'Nginx 网页服务','filegator/filegator'=>'网页文件管理器',
                'ghcr.io/kiwix/kiwix-tools'=>'离线知识库服务',
                'zhulinsen/daily_stock_analysis'=>'股票分析服务',
            ];
            if (isset($apps[$repository])) { $purpose=$apps[$repository];$evidence='Docker 镜像标识'; }

        } elseif (isset($services[$name])) { [$purpose,$description]=$services[$name];$evidence=$row['serviceEvidence'] ?? '进程名称'; }
        elseif (isset($row['vmName'])) { $purpose='虚拟机：'.$row['vmName'];$description='该虚拟机进程的宿主机监听，不是虚拟机内部端口';$evidence='libvirt PID 文件'; }
        elseif (strpos($name,'qemu')===0) { $purpose='虚拟机进程';$description='虚拟机相关的宿主机监听；不是虚拟机内部端口';$evidence='进程名称'; }
        elseif (strpos($name,'node')===0) { $purpose='Node.js 服务';$description='已识别运行时，尚未确认具体应用';$evidence='进程名称'; }
        $pidDescription=$row['pid']?'监听进程 PID：'.$row['pid']:($row['kind']==='binding'?'配置记录，不适用监听进程 PID':(strpos($row['owner'],'kernel:')===0?'内核服务，无普通进程 PID':'未识别到监听进程'));
        return array_merge($row,['pidDescription'=>$pidDescription,'purpose'=>$purpose,'description'=>$description,'evidence'=>$evidence]);
    }
    public static function protocolAvailability(array $snapshot,int $start,int $end): array {
        if (!$snapshot['complete']) throw new \RuntimeException('采集不完整，不能判断可用端口');
        $used=['tcp'=>[],'udp'=>[]];
        foreach ($snapshot['rows'] as $row) $used[$row['protocol']][$row['port']]=true;
        $result=[];
        for ($p=$start;$p<=$end;$p++) {
            if ($p<1024 || ($p>=$snapshot['ephemeral'][0]&&$p<=$snapshot['ephemeral'][1])) continue;
            $tcp=!isset($used['tcp'][$p]);$udp=!isset($used['udp'][$p]);
            if (!$tcp&&!$udp) continue;
            $kind=$tcp&&$udp?'both':($tcp?'tcp':'udp');
            $result[]=['port'=>$p,'tcp'=>$tcp,'udp'=>$udp,'kind'=>$kind];
        }
        return $result;
    }
    public static function scan(): array {
        $errors=[];$listeners=[];$bindings=[];$hosts=[];
        try { $listeners=self::parseSs(self::command('ss -H -lntup')); } catch (\Throwable $e) { $errors[]=$e->getMessage(); }
        try {
            $ids=trim(self::command('docker ps -aq --no-trunc'));
            if ($ids!=='') {
                $containers=[];
                foreach (array_chunk(preg_split('/\s+/', $ids), 32) as $chunk) {
                    foreach ($chunk as $id) if (!preg_match('/^[a-f0-9]{64}$/',$id)) throw new \RuntimeException('无效 Docker ID');
                    $batch=json_decode(self::command('docker inspect '.implode(' ',$chunk)),true,512,JSON_THROW_ON_ERROR);
                    $containers=array_merge($containers,$batch);
                }
                $networkIds=[];
                foreach ($containers as $container) {
                    $mode=$container['HostConfig']['NetworkMode'] ?? '';
                    if ($mode!=='' && !in_array($mode,['host','none','default'],true) && strpos($mode,'container:')!==0) $networkIds[$mode]=true;
                }
                foreach ($containers as $container) foreach ($container['NetworkSettings']['Networks'] ?? [] as $network) {
                    $networkId=$network['NetworkID'] ?? '';
                    if ($networkId==='') continue;
                    if (!preg_match('/^[a-f0-9]{64}$/',$networkId)) throw new \RuntimeException('无效 Docker 网络 ID');
                    $networkIds[$networkId]=true;
                }
                $networkDrivers=[];
                foreach (array_chunk(array_keys($networkIds),32) as $chunk) {
                    $networks=json_decode(self::command('docker network inspect '.implode(' ',array_map('escapeshellarg',$chunk))),true,512,JSON_THROW_ON_ERROR);
                    foreach ($networks as $network) {
                        $networkDrivers[$network['Name']]=$network['Driver'];
                        $networkDrivers[$network['Id']]=$network['Driver'];
                    }
                }
                [$bindings,$hosts]=self::dockerRows($containers,$networkDrivers);
                // Attribute host-network listeners using the actual container process PIDs.
                foreach ($hosts as $host) {
                    $pids=preg_split('/\s+/',trim(self::command('docker top '.$host['id'].' -eo pid')));
                    foreach ($listeners as &$l) if ($l['pid'] && in_array((string)$l['pid'],$pids,true)) { $l['source']='Docker';$l['name']=$host['name'].' / '.$l['name'];$l['owner']=$host['id']; } unset($l);
                }
            }
        } catch (\Throwable $e) { $errors[]='Docker 数据不完整：'.$e->getMessage(); }
        $range=trim((string)@file_get_contents('/proc/sys/net/ipv4/ip_local_port_range'));
        $ephemeral=preg_split('/\s+/', $range);
        if (count($ephemeral)!==2 || !ctype_digit($ephemeral[0]) || !ctype_digit($ephemeral[1])) { $errors[]='无法读取动态端口范围';$ephemeral=[32768,60999]; }
        // Enrich unidentified kernel sockets using actual RPC registration, never a port-number guess.
        try {
            $rpc=self::command('rpcinfo -p 127.0.0.1');$rpcPorts=[];
            foreach (preg_split('/\R/',$rpc) as $line) if (preg_match('/^\s*\d+\s+\d+\s+(tcp|udp)\s+(\d+)\s+(nfs|nlockmgr)\s*$/',$line,$m)) $rpcPorts[$m[1].':'.$m[2]]=$m[3];
            foreach ($listeners as &$l) if ($l['pid']===0 && $l['name']==='未知进程' && isset($rpcPorts[$l['protocol'].':'.$l['port']])) {
                $l['name']=$rpcPorts[$l['protocol'].':'.$l['port']];$l['owner']='kernel:'.$l['name'];$l['serviceEvidence']='RPC 注册信息';
            } unset($l);
        } catch (\Throwable $e) { /* Optional explanation; actual listener inventory is still valid. */ }
        $vmPids=[];
        foreach (glob('/var/run/libvirt/qemu/*.pid') ?: [] as $pidFile) {
            $pid=trim((string)@file_get_contents($pidFile));
            if (ctype_digit($pid) && basename($pidFile)!=='driver.pid') $vmPids[(int)$pid]=basename($pidFile,'.pid');
        }
        foreach ($listeners as &$listener) {
            if (strpos($listener['name'],'qemu')===0 && isset($vmPids[$listener['pid']])) $listener['vmName']=$vmPids[$listener['pid']];
            if ($listener['name']==='未知进程' && $listener['pid']) {
                $comm=trim((string)@file_get_contents('/proc/'.$listener['pid'].'/comm'));
                if ($comm!=='') $listener['name']=$comm;
            }
        } unset($listener);
        $rows=array_map([self::class,'describe'],self::resolve($listeners,$bindings));
        return ['groups'=>self::groups($rows),'summary'=>self::summary($rows),'timestamp'=>gmdate('c'),'complete'=>!$errors,'errors'=>$errors,'ephemeral'=>array_map('intval',$ephemeral),'rows'=>$rows,'hostContainers'=>$hosts];
    }
    public static function available(array $snapshot,int $start,int $end,string $protocol,int $limit=65535): array {
        if (!$snapshot['complete']) throw new \RuntimeException('采集不完整，不能判断可用端口');
        $used=[];foreach ($snapshot['rows'] as $r) if ($protocol==='both' || $r['protocol']===$protocol) $used[$r['port']]=true;
        $result=[];
        for ($p=$start;$p<=$end && count($result)<$limit;$p++) {
            if ($p<1024 || isset($used[$p]) || ($p>=$snapshot['ephemeral'][0] && $p<=$snapshot['ephemeral'][1])) continue;
            $result[]=$p;
        }
        return $result;
    }
}
