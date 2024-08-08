<?php
use Swoole\Coroutine\Http\Client;
class token{
    private $clusterId;
    private $clusterSecret;
    private $version;
    public function __construct($clusterId,$clusterSecret,$version){
        $this->clusterId = $clusterId;
        $this->clusterSecret = $clusterSecret;
        $this->version = $version;
    }
    public function gettoken() {
        //获取challenge
        $client = new Client(OPENBMCLAPIURL['host'],OPENBMCLAPIURL['port'],OPENBMCLAPIURL['ssl']);
        $client->setHeaders([
            'User-Agent' => USERAGENT,
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
        $client->set(['timeout' => 20]);
        $client->get('/openbmclapi-agent/challenge?clusterId='.$this->clusterId);
        $client->close();
        $challenge = json_decode($client->body, true);
        $signature = hash_hmac('sha256', $challenge['challenge'], $this->clusterSecret);
        //获取token和ttl
        $client = new Client(OPENBMCLAPIURL['host'],OPENBMCLAPIURL['port'],OPENBMCLAPIURL['ssl']);
        $client->post(
            '/openbmclapi-agent/token',
            array(
                'clusterId' => $this->clusterId,
                'challenge' => $challenge['challenge'],
                'signature' => $signature,
            )
        );
        $client->close();
        $responseData = json_decode($client->body, true);
        return array(
            'token' => $responseData["token"],
            'upttl' => $responseData['ttl'] - 600000,//前十分钟更新
        );
    }

    public function refreshToken($token) {
        //刷新token
        $client = new Client(OPENBMCLAPIURL['host'],OPENBMCLAPIURL['port'],OPENBMCLAPIURL['ssl']);
        $client->post(
            '/openbmclapi-agent/token',
            array(
                'clusterId' => $this->clusterId,
                'token' => $token,
            )
        );
        $client->close();
        $responseData = json_decode($client->body, true);
        return array(
            'token' => $responseData["token"],
            'upttl' => $responseData['ttl'] - 600000,//前十分钟更新
        );
    }
}