<?php
use Swoole\Coroutine;
use function Swoole\Coroutine\run;
use function Swoole\Timer;
declare(ticks=1)
require './config.php';
const PHPOBAVERSION = '0.0.1';
const VERSION = '1.10.4';
global $DOWNLOAD_DIR;
$DOWNLOAD_DIR = $config['file']['cache_dir'];
const USERAGENT = 'openbmclapi-cluster/' . VERSION . '  ' . 'PHP-OpenBmclApi/'.PHPOBAVERSION;
const OPENBMCLAPIURL = 'openbmclapi.bangbang93.com';
global $tokendata;
$list = glob('inc/*.php');
foreach ($list as $file) {
    require $file;
}
global $pid;
$pid = getmypid();
global $enable;
$enable = false;
echo"OpenBmclApionPHP v". PHPOBAVERSION . "-" . VERSION . "-dev" . PHP_EOL;
run(function()use ($config){
    //注册信号处理器
    function registerSigintHandler() {
        global $tokentimeid;
        $shouldExit = false; // 初始化为false
        Swoole\Process::signal(SIGINT, function ($signo) use ($tokentimeid) {
            try {
                global $shouldExit;
                $shouldExit = true; // 设置退出标志
                Swoole\Timer::clear($tokentimeid);
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
    $token = new token($config['cluster']['CLUSTER_ID'],$config['cluster']['CLUSTER_SECRET'],VERSION);
    $tokendata = $token->gettoken();
    mlog("GetToken:".$tokendata['token'],1);
    mlog("TokenTTL:".$tokendata['upttl'],1);
    //启动更新TokenTimer
    global $tokentimeid;
    $tokentimeid = Swoole\Timer::tick($tokendata['upttl'], function () use ($token) {
        $tokendata = $token->gettoken();
        mlog("GetNewToken:".$tokendata['token'],1);
    });
    global $socketio;
    registerSigintHandler();
    mlog("Timer start on ID{$tokentimeid}",1);
    
    //下载文件列表
    $cluster = new cluster($tokendata['token'],VERSION);
    $files = $cluster->getFileList();
    $FilesCheck = new FilesCheck($files);
    if ($config['file']['check'] == "hash"){
        $Missfile = $FilesCheck->FilesCheckerhash();
    }
    elseif($config['file']['check'] == "size"){
        $Missfile = $FilesCheck->FilesCheckersize();
    }
    elseif($config['file']['check'] == "exists"){
        $Missfile = $FilesCheck->FilesCheckerexists();
    }
    if (is_array($Missfile)){
        mlog("缺失/损坏".count($Missfile)."个文件");
        while(is_array($Missfile)){
            $download = new download($Missfile,$config['advanced']['MaxConcurrent']);
            $download->downloadFiles();
            $FilesCheck = new FilesCheck($Missfile);
            if ($config['file']['check'] == "hash"){
                $Missfile = $FilesCheck->FilesCheckerhash();
            }
            elseif($config['file']['check'] == "size"){
                $Missfile = $FilesCheck->FilesCheckersize();
            }
            elseif($config['file']['check'] == "exists"){
                $Missfile = $FilesCheck->FilesCheckerexists();
            }
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
        $socketio = new socketio(OPENBMCLAPIURL,$tokendata['token'],$config['advanced']['keepalive']);
        mlog("正在连接主控");
        Coroutine::create(function () use ($socketio){
            $socketio->connect();
        });
        Coroutine::sleep(1);
        $socketio->ack("request-cert");
        Coroutine::sleep(1);
        $allcert = $socketio->Getcert();
        if (!file_exists('./cert/'.$config['cluster']['CLUSTER_ID'].'.crt') && !file_exists('./cert/'.$config['cluster']['CLUSTER_ID'].'.key')) {
            mlog("正在获取证书");
            if (!file_exists("./cert")) {
                mkdir("./cert",0777,true);
            }
            mlog("已获取证书,到期时间{$allcert['0']['1']['expires']}");
            $cert = fopen('./cert/'.$config['cluster']['CLUSTER_ID'].'.crt', 'w');
            $Writtencert = fwrite($cert, $allcert['0']['1']['cert']);
            fclose($cert);
            $cert = fopen('./cert/'.$config['cluster']['CLUSTER_ID'].'.key', 'w');
            $Writtencert = fwrite($cert, $allcert['0']['1']['key']);
            fclose($cert);
        }
        $crt = file_get_contents('./cert/'.$config['cluster']['CLUSTER_ID'].'.crt');
        if ($crt!== $allcert['0']['1']['cert']) {
            mlog("证书损坏/过期");
            mlog("已获取新的证书,到期时间{$allcert['0']['1']['expires']}");
            $cert = fopen('./cert/'.$config['cluster']['CLUSTER_ID'].'.crt', 'w');
            $Writtencert = fwrite($cert, $allcert['0']['1']['cert']);
            fclose($cert);
            $cert = fopen('./cert/'.$config['cluster']['CLUSTER_ID'].'.key', 'w');
            $Writtencert = fwrite($cert, $allcert['0']['1']['key']);
            fclose($cert);
        }
        global $httpserver;
        global $DOWNLOAD_DIR;
        $httpserver = new fileserver($config['cluster']['host'],$config['cluster']['port'],$config['cluster']['CLUSTER_ID'].'.crt',$config['cluster']['CLUSTER_ID'].'.key',$config['cluster']['CLUSTER_SECRET']);
        Coroutine::create(function () use ($config,$httpserver){
            $httpserver->startserver();
        });
        $socketio->enable($config['cluster']['public_host'],$config['cluster']['public_port']);
    }
});