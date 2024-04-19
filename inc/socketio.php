<?php
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;
class socketio {
    private $url;
    private $token;
    private $client;
    private $data;
    private $certdata;
    private $Connected = false;
    public function __construct($url,$token) {
        $this->url = $url;
        $this->token = $token;
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
                $client->close();
                return;
            }
            if ($code[0] == '430'){
                $jsondata = json_decode(substr($data, strlen($code[0])),true);
                $this->certdata = $jsondata;
                //mlog("[socket.io]Got MESSAGE {$data}",1);
            }
            //var_dump($data);
            Coroutine::sleep(0.1);
        }
        global $shouldExit;
            if ($shouldExit) {
                mlog("[socket.io]Close Connection");
                $client->close();
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
}