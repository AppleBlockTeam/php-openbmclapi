<?php
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;
class socketio {
    private $url;
    private $token;
    private $client;
    private $data;
    private $certdata;
    private $kattl;
    private $Connected = false;
    public function __construct($url,$token,$kattl) {
        $this->url = $url;
        $this->token = $token;
        $this->kattl = $kattl;
        $katimeid = 0;
    }
    public function connect() {
        $this->client = $client = new Client($this->url, 443, true);
        $ret = $client->upgrade('/socket.io/?EIO=4&transport=websocket');
        $auth = [
            'token' => $this->token
        ];
        if ($ret) {
            $client->push('40'.json_encode($auth));
        }

        while(true) {
            $alldata = $client->recv(1.5);
            if (!is_bool($alldata)){
            $this->data = $data = $alldata->data;
            preg_match('/^\d+/', $data, $code);
            if ($code[0] == '0'){
                $jsondata = json_decode(substr($data, strlen($code[0])),true);
                mlog("[socket.io]Connected sid:{$jsondata['sid']} pingInterval:{$jsondata['pingInterval']} pingTimeout:{$jsondata['pingTimeout']}",1);
            }
            if ($code[0] == '40'){
                $jsondata = json_decode(substr($data, strlen($code[0])),true);
                mlog("[socket.io]Connected sid:{$jsondata['sid']}",1);
                mlog("已连接主控");
                $this->Connected = true;
            }
            if ($code[0] == '42'){
                $data = substr($data, strlen($code[0]));
                mlog("[socket.io]Got data {$data}");
            }
            if ($code[0] == '2'){
                $client->push('3');
                mlog("[socket.io]Sending PONG",1);
            }
            if ($code[0] == '41'){
                mlog("[socket.io]Close Connection");
                global $pid;
                posix_kill($pid, SIGINT);
                $client->close();
                return;
            }
            if ($code[0] == '430'){
                $jsondata = json_decode(substr($data, strlen($code[0])),true);
                if (isset($jsondata[0][1]['cert'])){
                    $this->certdata = $jsondata;
                }
                elseif (isset($jsondata[0][1]) && $jsondata[0][1] == "1"){
                    global $enable;
                    $enable = true;
                    mlog("节点已启用 Let's Goooooo!");
                    global $kacounters;
                    $kacounters = new Swoole\Table(1024);
                    $kacounters->column('hits', Swoole\Table::TYPE_FLOAT);
                    $kacounters->column('bytes', Swoole\Table::TYPE_FLOAT);
                    $kacounters->create();
                    $kacounters->set('1', ['hits' => 0, 'bytes' => 0]);
                    global $katimeid;
                    $katimeid = Swoole\Timer::tick($this->kattl*1000, function () use ($kacounters) {
                        $this->keepalive($kacounters);
                    });
                }
                elseif (isset($jsondata[0][1]) && $this->IsTime($jsondata[0][1])){
                    global $kadata;
                    mlog("Keep-alive success: hits={$kadata['hits']} bytes={$kadata['bytes']} Time={$jsondata[0][1]}");
                }
                elseif (isset($jsondata[0][0]["message"])){
                    mlog("[socket.io]Got data {$jsondata[0][0]["message"]}");
                }
                else {
                   mlog("[socket.io]Got data {$data}");
                };
                //mlog("[socket.io]Got MESSAGE {$data}",1);
            }
            if ($code[0] == '423'){
                $data = substr($data, strlen($code[0]));
                mlog("[socket.io]Got data {$data}");
            }
            //var_dump($data);
            Coroutine::sleep(0.1);
        }
        global $shouldExit;
        global $httpserver;
            if ($shouldExit) {
                Swoole\Timer::clear($katimeid);
                mlog("[socket.io]Close Connection");
                $client->close();
                $httpserver->stopserver();
                return;
            }
    }
    }
    public function Getcert() {
        $time = 0;
        while ($time !== 30){
            if(isset($this->certdata)){
                return $this->certdata;
            }
            Swoole\Coroutine\System::sleep(1);
            $time++;
        }
        if ($time = 30){
            mlog("Getcert Connected Overtime",2);
            return(false);
        }

    }
    public function ack($event,$data = "") {
        $time = 0;
        while ($time !== 30){
            Coroutine::sleep(1);
            $time++;
            if ($this->Connected){
                $senddata = json_encode([$event,$data]);
                return($this->client->push('420'.$senddata));
            }
        }
        if ($time = 30){
            mlog("[socket.io]ACK Connected Overtime",2);
            return(false);
        }
    }
    public function Getclient() {
        return $this->client;
    }
    public function enable($host,$port) {
        if (in_array($host, ["0.0.0.0", "127.0.0.1"])){
            $host = preg_replace('/\R/', '', file_get_contents('http://ip.3322.net'));
        }
        mlog("正在enable节点");
        $data = [
            'host' => $host,
            'port' => $port,
            'version' => VERSION,
            'byoc' => false,
            'noFastEnable' => false,
            'flavor' =>[
                'runtime' => 'PHP-'.substr(PHP_VERSION,0,3).'/'.php_uname('s'),
                'storage' => 'file'
            ]
        ];
        $this->ack("enable",$data);
    }
    public function keepalive($kacounters) {
        global $kadata;
        $kadata = [
            'time' => intval(microtime(true) * 1000),
            'hits' => $kacounters->get('1','hits'),
            'bytes' => $kacounters->get('1','bytes'),
        ];
        $this->ack("keep-alive",$kadata);
        $kacounters->set('1', ['hits' => 0, 'bytes' => 0]);
    }

    public function IsTime($inputString) {
        $pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{3})?Z$/';
        return preg_match($pattern, $inputString) === 1;
    }
}