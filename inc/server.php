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
    public function __construct($host,$port,$cert,$key,$dir,$secret) {
        $this->host = $host;
        $this->port = $port;
        $this->cert = $cert;
        $this->key = $key;
        $this->dir = $dir;
        $this->secret = $secret;
    }

    public function startserver() {
        $this->server = $server = new Server($this->host, $this->port, SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);
        $server->set([
            'ssl_cert_file' => './cert/'.$this->cert,
            'ssl_key_file' => './cert/'.$this->key,
            'open_http2_protocol' => true,
        ]);
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
            $code = 200;
            $response->end("Test");
            echo "download";
            if(!isset($request->server['query_string'])){
                $url = $request->server['request_uri'];
            }
            else{
                $url = $request->server['request_uri']."?".$request->server['query_string'];
            }
            mlog(" Serve {$code} | {$request->server['remote_addr']} | {$request->server['server_protocol']} | {$url} | {$request->header['user-agent']};") ;
        });
        $server->handle('/measure', function ($request, $response) {
            $measuresize = str_replace('/measure/', '', $request->server['request_uri']);
            if (!file_exists($this->dir.'/measure')) {
                mkdir($this->dir.'/measure',0777,true);
            }
            if(isset($request->server['query_string'])){
            if(is_numeric($measuresize)){
                parse_str($request->server['query_string'], $allurl);
                if ($this->check_sign($request->server['request_uri'], $this->secret, $allurl['s'], $allurl['e'])){
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
        mlog("Start Http Server on {$this->host}:{$this->port}");
        $server->start();
    }
    public function stopserver() {
        mlog("Stop Http Server");
        $this->server->shutdown();
    }
    //你问我这段函数为什么要放在server里面? 因为只有server需要check_sign(
    public function check_sign(string $hash, string $secret, string $s, string $e): bool {
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
}