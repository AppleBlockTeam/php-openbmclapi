<?php
use Swoole\Coroutine\Http\Server;

class fileserver {
    private $host;
    private $port;
    private $cert;
    private $key;
    private $server;
    private $dir;
    private $secret;
    private $ssl;
    private $lock;
    public function __construct($host,$port,$cert,$key,$secret,$ssl) {
        $this->host = $host;
        $this->port = $port;
        $this->cert = $cert;
        $this->key = $key;
        $this->ssl = $ssl;
        $this->dir = api::getconfig()['file']['cache_dir'];
        $this->secret = $secret;
        $this->lock = new Swoole\Lock(SWOOLE_RWLOCK);
    }

    public function setupserver() {
        if($this->ssl){
            $this->server = $server = new Server($this->host, $this->port, true);
            $server->set([
                'ssl_cert_file' => $this->cert,
                'ssl_key_file' => $this->key,
                'heartbeat_check_interval' => 60,  // 表示每60秒遍历一次
            ]);
        }
        else{
            $this->server = $server = new Server($this->host, $this->port);
            $server->set([
                'heartbeat_check_interval' => 60,  // 表示每60秒遍历一次
            ]);
        }
        $server->handle('/', function ($request, $response) {
            $code = 404;
            $response->status($code);
            $response->header('Content-Type', 'text/html; charset=utf-8');
            $response->end("<title>Error</title><pre>404 Not Found</pre>");
            if(!isset($request->server['query_string'])){
                $url = $request->server['request_uri'];
            }
            else{
                $url = $request->server['request_uri']."?".$request->server['query_string'];
            }
            mlog(" Serve {$code} | {$request->server['remote_addr']} | {$request->server['server_protocol']} | {$url} | {$request->header['user-agent']};") ;
        });
        $server->handle('/download', function ($request, $response) {
            $downloadhash = str_replace('/download/', '', $request->server['request_uri']);
            if(isset($request->server['query_string'])){
                $filepath = $this->dir.'/'.substr($downloadhash, 0, 2).'/'.$downloadhash;
                if ($this->check_sign($downloadhash, $this->secret, $request->get['s'], $request->get['e'])){
                    if (!file_exists($filepath)) {
                        $download = new download();
                        $download->downloadnopoen($downloadhash);
                    }
                    if(isset($request->header['range'])){
                        preg_match('/bytes=(\d+)-(\d+)?/', $request->header['range'], $matches);
                        $start_byte = (int) $matches[1];
                        $end_byte = isset($matches[2]) ? intval($matches[2]) : null;
                        if ($end_byte === null) {
                            $end_byte = filesize($filepath) - 1;
                        }
                        $length = $end_byte - $start_byte + 1;
                        $code = 206;
                        $response->header('Content-Type', 'application/octet-stream');
                        if(isset($request->header['name'])){
                             $response->header('Content-Disposition', 'attachment; filename='.$request->get['name']);
                        }
                        $response->header('x-bmclapi-hash', $downloadhash);
                        $response->sendfile($filepath,$start_byte,$length);
                    }
                    else{
                        $length = filesize($filepath);
                        $code = 200;
                        $response->header('Content-Type', 'application/octet-stream');
                        if(isset($request->header['name'])){
                            $response->header('Content-Disposition', 'attachment; filename='.$request->get['name']);
                        }
                        $response->header('x-bmclapi-hash', $downloadhash);
                        $response->sendfile($filepath);
                    }
                    if (api::getinfo()['enable']){
                        global $kacounters;
                        $kacounters->incr('1','hits');
                        $kacounters->incr('1','bytes',$length);

                        global $dbcounters;
                        $dbcounters->incr('1','hits');
                        $dbcounters->incr('1','bytes',$length);
                    }
                }
                else{
                    $code = 403;
                    $response->status($code);
                    $response->header('Content-Type', 'text/html; charset=utf-8');
                    $response->end("<title>Error</title><pre>invalid sign</pre>");
                }
                }
                else{
                    $code = 404;
                    $response->status($code);
                    $response->header('Content-Type', 'text/html; charset=utf-8');
                    $response->end("<title>Error</title><pre>404 Not Found</pre>");
                }
            if(!isset($request->server['query_string'])){
                $url = $request->server['request_uri'];
            }
            else{
                $url = $request->server['request_uri']."?".$request->server['query_string'];
            }
            //真的会有启动器不带ua的啊，太神奇了
            $request->server['user-agent'] = $request->server['user-agent'] ?? "other";
            mlog(" Serve {$code} | {$request->server['remote_addr']} | {$request->server['server_protocol']} | {$url} | {$request->header['user-agent']};") ;
        });

        $server->handle('/measure', function ($request, $response) {
            $measuresize = str_replace('/measure/', '', $request->server['request_uri']);
            if (!file_exists($this->dir.'/measure')) {
                mkdir($this->dir.'/measure',0777,true);
            }
            if(isset($request->server['query_string'])){
            if(is_numeric($measuresize)){
                if ($this->check_sign($request->server['request_uri'], $this->secret, $request->get['s'], $request->get['e'])){
                    if (!file_exists($this->dir.'/measure/'.$measuresize)) {
                        $file = fopen($this->dir.'/measure/'.$measuresize, 'w+');
                        $bytesToWrite = $measuresize * 1048576;
                        $fillChar = str_repeat("\0", 1024);
                        while ($bytesToWrite > 0) {
                            $chunkSize = min(1024, $bytesToWrite);
                            fwrite($file, substr($fillChar, 0, $chunkSize));
                            $bytesToWrite -= $chunkSize;
                        }
                        fclose($file);
                    }
                    $code = 200;
                    $response->header('Content-Type', 'application/octet-stream');
                    $response->sendfile($this->dir.'/measure/'.$measuresize);
                }
                else{
                    $code = 403;
                    $response->status($code);
                    $response->header('Content-Type', 'text/html; charset=utf-8');
                    $response->end("<title>Error</title><pre>Forbidden</pre>");
                }
            }
            }
            else{
                $code = 404;
                $response->status($code);
                $response->header('Content-Type', 'text/html; charset=utf-8');
                $response->end("<title>Error</title><pre>404 Not Found</pre>");
            }
            if(!isset($request->server['query_string'])){
                $url = $request->server['request_uri'];
            }
            else{
                $url = $request->server['request_uri']."?".$request->server['query_string'];
            }
            mlog(" Serve {$code} | {$request->server['remote_addr']} | {$request->server['server_protocol']} | {$url} | {$request->header['user-agent']};") ;
        });

        $server->handle('/api/cluster', function ($request, $response) {
            $type = $request->server['request_uri'] ? substr($request->server['request_uri'], strlen('/api/cluster') + 1) : '';
            if($type === "type"){
                $code = 200;
                $response->header('Content-Type', 'application/json; charset=utf-8');
                $type = new webapi();
                $response->end($type->gettype());
            }
            elseif($type === "status"){
                $code = 200;
                $response->header('Content-Type', 'application/json; charset=utf-8');
                $type = new webapi();
                $response->end($type->getstatus());
            }
            elseif($type === "info"){
                $code = 200;
                $response->header('Content-Type', 'application/json; charset=utf-8');
                $type = new webapi();
                $response->end($type->getinfo());
            }
            else{
                $code = 403;
                $response->status($code);
                $response->header('Content-Type', 'text/html; charset=utf-8');
                $response->end("<title>Error</title><pre>Forbidden</pre>");
            }

            if(!isset($request->server['query_string'])){
                $url = $request->server['request_uri'];
            }
            else{
                $url = $request->server['request_uri']."?".$request->server['query_string'];
            }
            
            mlog(" Serve {$code} | {$request->server['remote_addr']} | {$request->server['server_protocol']} | {$url} | {$request->header['user-agent']};") ;
        });
        return $server;
    }

    public function startserver() {
        mlog("Start Http Server on {$this->host}:{$this->port}");
        $this->server->start();
    }
    public function stopserver() {
        mlog("Stop Http Server",1);
        $this->server->shutdown();
    }
    //你问我这段函数为什么要放在server里面? 因为只有server需要check_sign(
    public function check_sign(string $hash, string $secret, string $s=null, string $e=null): bool {
        try {
            $t = intval($e, 36);
        } catch (\Exception $ex) {
            return false;
        }
      
        $sha1 = hash_init('sha1');
        hash_update($sha1, $secret);
        hash_update($sha1, $hash);
        hash_update($sha1, $e);
        $computedSignature = rtrim(strtr(base64_encode(hash_final($sha1,true)), '+/', '-_'), '=');
        return ($computedSignature === $s && time() * 1000 <= $t);
      }

    public function setupserver302() {
        $server = $this->server;
        $server->handle('/download', function ($request, $response) {
            $downloadhash = str_replace('/download/', '', $request->server['request_uri']);
            if(isset($request->server['query_string'])){
                $filepath = '/'.substr($downloadhash, 0, 2).'/'.$downloadhash;
                if ($this->check_sign($downloadhash, $this->secret, $request->get['s'], $request->get['e'])){
                    //if (!file_exists($filepath)) {
                    //    $download = new download();
                    //    $download->downloadnopoen($downloadhash);
                    //}
                    $code = 302;
                    $url = $this->getfileurl($filepath);
                    //判断是否获取分片文件
                    if(isset($request->header['range'])){
                        preg_match('/bytes=(\d+)-(\d+)?/', $request->header['range'], $matches);
                        $start_byte = (int) $matches[1];
                        $end_byte = isset($matches[2]) ? intval($matches[2]) : null;
                        if ($end_byte === null) {
                            $end_byte = filesize($filepath) - 1;
                        }
                        $length = $end_byte - $start_byte + 1;
                    }
                    else{
                        $length = $url['size'];
                    }
                    //302
                    $response->redirect($url['raw_url'],302);
                    //记录请求
                    if (api::getinfo()['enable']){
                        global $kacounters;
                        $kacounters->incr('1','hits');
                        $kacounters->incr('1','bytes',$length);

                        global $dbcounters;
                        $dbcounters->incr('1','hits');
                        $dbcounters->incr('1','bytes',$length);
                    }
                }
                else{
                    $code = 403;
                    $response->status($code);
                    $response->header('Content-Type', 'text/html; charset=utf-8');
                    $response->end("<title>Error</title><pre>invalid sign</pre>");
                }
                }
                else{
                    $code = 404;
                    $response->status($code);
                    $response->header('Content-Type', 'text/html; charset=utf-8');
                    $response->end("<title>Error</title><pre>404 Not Found</pre>");
                }
            if(!isset($request->server['query_string'])){
                $url = $request->server['request_uri'];
            }
            else{
                $url = $request->server['request_uri']."?".$request->server['query_string'];
            }
            $request->server['user-agent'] = $request->server['user-agent'] ?? "other";
            mlog(" Serve {$code} | {$request->server['remote_addr']} | {$request->server['server_protocol']} | {$url} | {$request->header['user-agent']};") ;
        });
    }

    public function getfileurl($filepath) {
        $webdav = new webdav();
        return $webdav->getfileurl($filepath);
    }
}