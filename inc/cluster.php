<?php
require_once('./vendor/autoload.php');
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;
use Dariuszp\CliProgressBar;

class BMCLAPIFile {
    public $path;
    public $hash;
    public $size;
    public $mtime;

    public function __construct($path, $hash, $size, $mtime = 0) {
        $this->path = $path;
        $this->hash = $hash;
        $this->size = $size;
        $this->mtime = $mtime;
    }
}

class ParseFileList {
    private $data;
    private $files = [];

    public function __construct($data) {
        $this->data = $data;
    }

    public function parse() {
        $memoryStream = fopen('php://memory', 'r+');
        fwrite($memoryStream, $this->data);
        rewind($memoryStream);

        $totalFiles = $this->readLong($memoryStream);
        $bar = new CliProgressBar($totalFiles);
        $bar->setDetails("[ParseFileList]");
        $bar->display();

        for ($i = 0; $i < $totalFiles; $i++) {
            global $shouldExit;
            if ($shouldExit) {
                break;
            }
            $this->files[] = new BMCLAPIFile(
                $this->readString($memoryStream),
                $this->readString($memoryStream),
                $this->readLong($memoryStream),
                $this->readLong($memoryStream)
            );
            $bar->progress();
        }
        fclose($memoryStream);
        $bar->display();
        $bar->end();
        return $this->files;
    }

    private function readLong($memoryStream) {
        $b = ord(fread($memoryStream, 1));
        $n = $b & 0x7F;
        $shift = 7;
        while (($b & 0x80) != 0) {
            $b = ord(fread($memoryStream, 1));
            $n |= ($b & 0x7F) << $shift;
            $shift += 7;
        }
        return ($n >> 1) ^ -($n & 1);
    }

    private function readString($memoryStream) {
        $length = $this->readLong($memoryStream);
        $test = fread($memoryStream, $length);
        return $test;
    }
}

class cluster{
    private $token;
    private $version;
    private $compressedData;
    public function __construct($token,$version){
        $this->token = $token;
        $this->version = $version;
    }
    public function getFileList() {
        global $DOWNLOAD_DIR;
        if (!file_exists($DOWNLOAD_DIR."/filecache")) {
            mkdir($DOWNLOAD_DIR."/filecache",0777,true);
        }
        $client = new Client(OPENBMCLAPIURL,443,true);
        $client->set(['timeout' => -1]);
        $client->setHeaders([
            'Host' => OPENBMCLAPIURL,
            'User-Agent' => 'openbmclapi-cluster/'.$this->version,
            'Accept' => '*',
            'Authorization' => "Bearer {$this->token}"
        ]);
        mlog("Start FileList Download");
        if (!$client->download('/openbmclapi/files',$DOWNLOAD_DIR.'/filecache/filelist.zstd')) {
            mlog("FileList Download Failed",2);
            $client->close();
        }
        else{
            mlog("FileList Download Success");
            $client->close();
            $this->compressedData = file_get_contents("compress.zstd://".$DOWNLOAD_DIR."/filecache/filelist.zstd");
        }
        $parser = new ParseFileList($this->compressedData);
        $files = $parser->parse();
        return $files;
    }
}

class download {
    private $filesList;
    private $maxConcurrent;
    private $semaphore;

    public function __construct($filesList = [], $maxConcurrent = 1) {
        $this->filesList = $filesList;
        $this->maxConcurrent = $maxConcurrent;
        $this->semaphore = new Swoole\Coroutine\Channel($maxConcurrent);
    }

    private function downloader(Swoole\Coroutine\Http\Client $client, $file,$bar) {
        global $DOWNLOAD_DIR;
        $filePath = $DOWNLOAD_DIR . '/' . substr($file->hash, 0, 2) . '/';
        if (!file_exists($filePath)) {
            mkdir($filePath, 0777, true);
        }
        $savePath = $filePath . $file->hash;
        $file->path = str_replace(' ', '%20', $file->path);
        $downloader = $client->download($file->path,$DOWNLOAD_DIR.'/'.substr($file->hash, 0, 2).'/'.$file->hash);
        if (!$downloader) {
            mlog("Error connecting to the main control:{$client->errMsg}",2);
            return false;
        } 
        elseif($client->statusCode == "200"){
            $bar->progress();
            return true;
        }
        else {
            if(isset($client->getHeaders()['location'])){
            $location_url = parse_url($client->getHeaders()['location']);
            $client->close();
            $client = new Swoole\Coroutine\Http\Client($location_url['host'], $location_url['port'], true);
                $client->set([
                    'timeout' => 60
                ]);
                $client->setHeaders([
                    'Host' => $location_url['host'],
                    'User-Agent' => USERAGENT,
                    'Accept' => '*/*',
                ]);
                $downloader = $client->download($location_url['path'].'?'.($location_url['query']??''),$DOWNLOAD_DIR.'/'.substr($file->hash, 0, 2).'/'.$file->hash);
            if (in_array($client->statusCode, [301, 302])) {
                while(in_array($client->statusCode, [301, 302])){
                    $location_url = parse_url($client->getHeaders()['location']);
                    $client->close();
                    if (!isset($array['port'])){
                        $location_url['port'] = 443;
                    }
                    $client = new Swoole\Coroutine\Http\Client($location_url['host'], $location_url['port'], true);
                    $client->set([
                        'timeout' => 60
                    ]);
                    $client->setHeaders([
                        'Host' => $location_url['host'],
                        'User-Agent' => USERAGENT,
                        'Accept' => '*/*',
                    ]);
                    $downloader = $client->download($location_url['path'].'?'.($location_url['query']??''),$DOWNLOAD_DIR.'/'.substr($file->hash, 0, 2).'/'.$file->hash);
                }
                if (!$downloader) {
                    echo PHP_EOL;
                    mlog("{$file->path} Download Failed: {$client->errMsg} From Node: {$location_url['host']}:{$location_url['port']}",2);
                    $bar->progress();
                    return false;
                }
                else{
                    //mlog("Download Success");
                    $bar->progress();
                    return true;
                }
            }
            else{
                if (!$downloader) {
                    echo PHP_EOL;
                    mlog("{$file->path} Download Failed: {$client->errMsg} From Node: {$location_url['host']}:{$location_url['port']}",2);
                    $bar->progress();
                    return false;
                }
                else{
                    //mlog("Download Success");
                    $bar->progress();
                    return true;
                }
            }
        }
        else{
            $bar->progress();
            return false;
        }
        }
    }

    public function downloadFiles() {
        $bar = new CliProgressBar(count($this->filesList));
        $bar->setDetails("[Downloader]");
        $bar->display();
        foreach ($this->filesList as $file) {
            global $shouldExit;
            if ($shouldExit) {
                break;
            }
            $this->semaphore->push(true);
            go(function () use ($file,$bar) {
                $client = new Swoole\Coroutine\Http\Client('openbmclapi.bangbang93.com', 443, true);
                $client->set([
                    'timeout' => -1
                ]);
                $client->setHeaders([
                    'Host' => 'openbmclapi.bangbang93.com',
                    'User-Agent' => USERAGENT,
                    'Accept' => '*/*',
                ]);
                if ($this->downloader($client, $file,$bar)) {
                    $client->close();
                }
                else{
                    $client->close();
                }
                $this->semaphore->pop();
            });
        }
        for ($i = 0; $i < $this->maxConcurrent; $i++) {
            $this->semaphore->push(true);
        }
        $bar->display();
        $bar->end();
    }

    private function downloadnopoen($hash) {
        global $DOWNLOAD_DIR;
        global $tokendata;
        $filePath = $DOWNLOAD_DIR . '/' . substr($hash, 0, 2) . '/';
        if (!file_exists($filePath)) {
            mkdir($filePath, 0777, true);
        }
        $filepath = "/openbmclapi/download/{$hash}?noopen=1";
        $client = new Swoole\Coroutine\Http\Client('openbmclapi.bangbang93.com', 443, true);
        $client->set([
            'timeout' => -1
        ]);
        $client->setHeaders([
            'Host' => 'openbmclapi.bangbang93.com',
            'User-Agent' => USERAGENT,
            'Accept' => '*/*',
            'Authorization' => "Bearer {$tokendata['token']}"
        ]);
        $downloader = $client->download($filepath,$DOWNLOAD_DIR.'/'.substr($hash, 0, 2).'/'.$hash);
        if (!$downloader) {
            mlog("Error download to the main control:{$client->errMsg}",2);
            return false;
        } 
        elseif($client->statusCode == "200"){
            return true;
        }
        else{
            return false;
        }
    }
}

class FilesCheck {
    private $filesList;
    private $Missfile;

    public function __construct($filesList) {
        $this->filesList = $filesList;
    }

    public function FilesCheckerhash() {
        mlog("检查策略:hash");
        $bar = new CliProgressBar(count($this->filesList));
        $bar->setDetails("[FileCheck]");
        $bar->display();
        foreach ($this->filesList as $file) {
            global $shouldExit;
            global $DOWNLOAD_DIR;
            if ($shouldExit) {
                return;
                break;
            }
            if (!file_exists($DOWNLOAD_DIR.'/'.substr($file->hash, 0, 2).'/'.$file->hash)){
                $this->Missfile[] = new BMCLAPIFile(
                    $file->path,
                    $file->hash,
                    $file->size,
                    $file->mtime
                );
            }
            else{
                if (hash_file('sha1',$DOWNLOAD_DIR.'/'.substr($file->hash, 0, 2).'/'.$file->hash) != $file->hash) {
                    $this->Missfile[] = new BMCLAPIFile(
                        $file->path,
                        $file->hash,
                        $file->size,
                        $file->mtime
                    );
                }
            }
        $bar->progress();
        }
        $bar->display();
        $bar->end();
        return $this->Missfile;
    }

    public function FilesCheckersize() {
        mlog("检查策略:size");
        $bar = new CliProgressBar(count($this->filesList));
        $bar->setDetails("[FileCheck]");
        $bar->display();
        foreach ($this->filesList as $file) {
            global $shouldExit;
            global $DOWNLOAD_DIR;
            if ($shouldExit) {
                return;
                break;
            }
            if (!file_exists($DOWNLOAD_DIR.'/'.substr($file->hash, 0, 2).'/'.$file->hash)){
                $this->Missfile[] = new BMCLAPIFile(
                    $file->path,
                    $file->hash,
                    $file->size,
                    $file->mtime
                );
            }
            else{
                if (filesize($DOWNLOAD_DIR.'/'.substr($file->hash, 0, 2).'/'.$file->hash) != $file->size) {
                    $this->Missfile[] = new BMCLAPIFile(
                        $file->path,
                        $file->hash,
                        $file->size,
                        $file->mtime
                    );
                }
            }
        $bar->progress();
        }
        $bar->display();
        $bar->end();
        return $this->Missfile;
    }

    public function FilesCheckerexists() {
        mlog("检查策略:exists");
        $bar = new CliProgressBar(count($this->filesList));
        $bar->setDetails("[FileCheck]");
        $bar->display();
        foreach ($this->filesList as $file) {
            global $shouldExit;
            global $DOWNLOAD_DIR;
            if ($shouldExit) {
                return;
                break;
            }
            if (!file_exists($DOWNLOAD_DIR.'/'.substr($file->hash, 0, 2).'/'.$file->hash)){
                $this->Missfile[] = new BMCLAPIFile(
                    $file->path,
                    $file->hash,
                    $file->size,
                    $file->mtime
                );
            }
        $bar->progress();
        }
        $bar->display();
        $bar->end();
        return $this->Missfile;
    }
}