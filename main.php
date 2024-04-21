<?php
use Swoole\Coroutine;
use function Swoole\Coroutine\run;
use function Swoole\Timer;
declare(ticks=1)
require './config.php';
const PHPOBAVERSION = '0.0.1-dev';
const VERSION = '1.10.3';
global $DOWNLOAD_DIR;
$DOWNLOAD_DIR = $config['cache_dir'];
const USERAGENT = 'openbmclapi-cluster/' . VERSION . '  ' . 'PHP-OpenBmclApi/'.PHPOBAVERSION;
const OPENBMCLAPIURL = 'openbmclapi.bangbang93.com';
global $tokendata;
$list = glob('./inc/*.php');
foreach ($list as $file) {
    $file = explode('/', $file)['2'];
    require './inc/' . $file;
}
global $pid;
$pid = getmypid();
echo"OpenBmclApionPHP v0.0.1-dev". PHP_EOL;
run(function()use ($config){
    //注册信号处理器
    function registerSigintHandler() {
        global $timerId;
        $shouldExit = false; // 初始化为false
        Swoole\Process::signal(SIGINT, function ($signo) use ($timerId) {
            try {
                global $shouldExit;
                $shouldExit = true; // 设置退出标志
                Swoole\Timer::clear($timerId);
                echo PHP_EOL;
                mlog("正在退出...");
                exit();
            } catch (\Swoole\ExitException $e) {
                //var_dump($e->getMessage());
                //var_dump($e->getStatus() === 1);
                //var_dump($e->getFlags() === SWOOLE_EXIT_IN_COROUTINE);
            }
        });
    }
    //获取初次Token
    $token = new token($config['CLUSTER_ID'],$config['CLUSTER_SECRET'],VERSION);
    $tokendata = $token->gettoken();
    mlog("GetToken:".$tokendata['token'],1);
    mlog("TokenTTL:".$tokendata['upttl'],1);
    //启动更新TokenTimer
    global $timerId;
    $timerId = Swoole\Timer::tick($tokendata['upttl'], function () use ($token) {
        $tokendata = $token->gettoken();
        mlog("GetNewToken:".$tokendata['token'],1);
    });
    global $socketio;
    registerSigintHandler();
    mlog("Timer start on ID{$timerId}",1);
    
    //下载文件列表
    $cluster = new cluster($tokendata['token'],VERSION);
    $files = $cluster->getFileList();
    $FilesCheck = new FilesCheck($files);
    $Missfile = $FilesCheck->FilesCheckerhash();
    if (is_array($Missfile)){
        mlog("缺失/损坏".count($Missfile)."个文件");
        while(is_array($Missfile)){
            $download = new download($Missfile,$config['MaxConcurrent']);
            $download->downloadFiles();
            $FilesCheck = new FilesCheck($Missfile);
            $Missfile = $FilesCheck->FilesCheckerhash();
            if (is_array($Missfile)){
                //mlog("缺失/损坏".count($Missfile)."个文件");
            }
            else{
                mlog("检查文件完毕,没有缺失/损坏");
            }
        }
    }
    else{
        global $shouldExit;
        if (!$shouldExit){
            mlog("检查文件完毕,没有缺失/损坏");
        }
    }
    global $shouldExit;
    if (!is_array($Missfile) && !$shouldExit){//判断Missfile是否为空和是否是主动退出
        $socketio = new socketio(OPENBMCLAPIURL,$tokendata['token']);
        mlog("正在连接主控");
        Coroutine::create(function () use ($socketio){
            $socketio->connect();
        });
        Coroutine::sleep(1);
        if (file_exists('./cert/'.$config['CLUSTER_ID'].'.crt') && file_exists('./cert/'.$config['CLUSTER_ID'].'.key')) {
            
        } else {
            mlog("正在获取证书");
            if (!file_exists("./cert")) {
                mkdir("./cert",0777,true);
            }
            $socketio->ack("request-cert");
            Coroutine::sleep(1);
            $allcert = $socketio->Getcert();
            mlog("已获取证书,到期时间{$allcert['0']['1']['expires']}");
            $cert = fopen('./cert/'.$config['CLUSTER_ID'].'.crt', 'w');
            $Writtencert = fwrite($cert, $allcert['0']['1']['cert']);
            fclose($cert);
            $cert = fopen('./cert/'.$config['CLUSTER_ID'].'.key', 'w');
            $Writtencert = fwrite($cert, $allcert['0']['1']['key']);
            fclose($cert);
        }
    }
});