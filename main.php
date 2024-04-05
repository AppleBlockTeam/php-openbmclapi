<?php
use Swoole\Coroutine;
use function Swoole\Coroutine\run;
use function Swoole\Timer;
declare(ticks=1)
require './config.php';
const VERSION = '1.10.3';
const DOWNLOAD_DIR = "./cache";
if ($config['Staging'] == true){
    define('OPENBMCLAPI', 'openbmclapi.staging.bangbang93.com');
}
else{
    define('OPENBMCLAPI', 'openbmclapi.bangbang93.com');
}
global $tokendata;
$list = glob('./inc/*.php');
    foreach ($list as $file) {
        $file = explode('/', $file)['2'];
        require './inc/' . $file;
    }
echo"OpenBmclApionPHP v0.0.1-dev". PHP_EOL;
run(function()use ($config){
    //注册信号处理器
    function registerSigintHandler() {
        global $timerId;
        Swoole\Process::signal(SIGINT, function ($signo) use ($timerId) {
            try {
                Swoole\Timer::clear($timerId);
                mlog("主动退出...");
                exit;
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
    registerSigintHandler();
    mlog("Timer start on ID{$timerId}",1);
    
    //下载文件列表
    $cluster = new cluster($tokendata['token'],VERSION);
    $files = $cluster->getFileList();
    mlog("检查策略:hash");
    $FilesCheck = new FilesCheck($files);
    $Missfile = $FilesCheck->FilesCheckerhash();
    if (is_array($Missfile)){
        mlog("缺失/损坏".count($Missfile)."个文件");
        while(is_array($Missfile)){
            $download = new download($Missfile,10);
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
        mlog("检查文件完毕,没有缺失/损坏");
    }
});