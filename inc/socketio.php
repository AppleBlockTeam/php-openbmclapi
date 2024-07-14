<?php
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;
class socketio {
    private $url;
    private $port;
    private $ssl;
    private $token;
    private $client;
    private $data;
    private $certdata;
    private $kattl;
    private $rekeepalive = 1;
    private $Connected = false;
    public function __construct($url=null,$token=null,$kattl=null) {
        $this->url = $url['host'];
        $this->port = $url['port'];
        $this->ssl = $url['ssl'];
        $this->token = $token;
        $this->kattl = $kattl;
        $katimeid = 0;
    }
    public function connect() {
        $this->client = $client = new Client($this->url, $this->port, $this->ssl);
        $ret = $client->upgrade('/socket.io/?EIO=4&transport=websocket');
        $auth = [
            'token' => $this->token
        ];
        if ($ret) {
            $client->push('40'.json_encode($auth));
        }
        while(true) {
            $alldata = $client->recv();
            if (empty($alldata)) {
                $client->close();
                mlog("与主控的连接断开");
                break;
            }
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
                $jsondata = json_decode($data);
                if(isset($jsondata[0])){
                    mlog("{$jsondata[1]}");
                }
                else{
                    mlog("[socket.io]{$data}");
                }
            }
            if ($code[0] == '2'){
                $client->push('3');
                mlog("[socket.io]Sending PONG",1);
            }
            if ($code[0] == '41'){
                mlog("[socket.io]Close Connection");
                $client->close();
                exits();
                break;
            }
            if ($code[0] == '430'){
                $jsondata = json_decode(substr($data, strlen($code[0])),true);
                if (isset($jsondata[0][1]['cert'])){
                    $this->certdata = $jsondata;
                }
                elseif (isset($jsondata[0][1]) && $jsondata[0][1] == "1"){
                    $enable = api::getinfo();
                    if(!$enable['enable']){
                        $enable = api::getinfo();
                        $enable['enable'] = true;
                        api::getinfo($enable);
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

                        global $dbcounters;
                        $dbcounters = new Swoole\Table(1024);
                        $dbcounters->column('hits', Swoole\Table::TYPE_FLOAT);
                        $dbcounters->column('bytes', Swoole\Table::TYPE_FLOAT);
                        $dbcounters->create();
                        $dbcounters->set('1', ['hits' => 0, 'bytes' => 0]);
                        $dbtimeid = Swoole\Timer::tick(3000, function () use ($dbcounters) {
                            $this->updatedatabase($dbcounters);
                        });
                    }
                    else{
                        $client->close();
                        break;
                        mlog("[socket.io]Close Connection");
                    }
                }
                elseif (isset($jsondata[0][1]) && $jsondata[0][1] == "0"){
                    if($this->rekeepalive <= 3){
                        mlog("Keep-Alive失败,正在重试({$this->rekeepalive}/3)");
                        global $kadata;
                        $this->ack("keep-alive",$kadata);
                        $this->rekeepalive++;
                    }
                    else{
                        exits();
                    }
                }
                elseif (isset($jsondata[0][1]) && $this->IsTime($jsondata[0][1])){
                    $this->rekeepalive = 1;
                    global $kadata;
                    mlog(" Keep-alive success: hits={$kadata['hits']} bytes={$kadata['bytes']} Time={$jsondata[0][1]}");
                }
                elseif (isset($jsondata[0][0]["message"])){
                    mlog("[socket.io]{$jsondata[0][0]["message"]}");
                    if (strpos($jsondata[0][0]["message"], "Error") !== false) {
                        mlog("节点启用失败",2);
                        exits();
                    }
                }
                else {
                    mlog("[socket.io]Got data {$data}");
                };
                //mlog("[socket.io]Got MESSAGE {$data}",1);
            }
            if ($code[0] == '423'){
                $data = substr($data, strlen($code[0]));
                if(isset($jsondata[0][0]["message"])){
                    mlog("[socket.io]Got data {$jsondata[0][0]["message"]}");
                }
                else{
                    mlog("[socket.io]Got data {$data}");
                }
            }
            //var_dump($data);
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
        if ($time == 30){
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
        if ($time == 30){
            mlog("[socket.io]ACK Connected Overtime",2);
            return(false);
        }
    }
    public function Getclient() {
        return $this->client;
    }
    public function enable($host,$port,$byoc) {
        if (in_array($host, ["0.0.0.0", "127.0.0.1"])){
            $host = preg_replace('/\R/', '', file_get_contents('http://ip.3322.net'));
        }
        mlog("正在enable节点");
        $data = [
            'host' => $host,
            'port' => $port,
            'version' => VERSION,
            'byoc' => $byoc,
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

    public function updatedatabase($dbcounters) {
        $database = new database();
        $database->writeDatabase($dbcounters->get('1','hits'),$dbcounters->get('1','bytes'));
        $dbcounters->set('1', ['hits' => 0, 'bytes' => 0]);
    }
    public function IsTime($inputString) {
        $pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{3})?Z$/';
        return preg_match($pattern, $inputString) === 1;
    }

    public function disable() {
        $enable = api::getinfo()['enable'];
        if ($enable){
            $this->ack("disable");
        }
        $this->client->close();
        $this->Connected = false;
    }
}