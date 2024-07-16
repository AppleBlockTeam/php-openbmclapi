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
        $download_dir = api::getconfig()['file']['cache_dir'];
        $client = new Client(OPENBMCLAPIURL['host'],OPENBMCLAPIURL['port'],OPENBMCLAPIURL['ssl']);
        $client->set(['timeout' => -1]);
        $client->setHeaders([
            'User-Agent' => 'openbmclapi-cluster/'.$this->version,
            'Accept' => '*',
            'Authorization' => "Bearer {$this->token}"
        ]);
        mlog("Starting to download fileList");
        if (!$client->get('/openbmclapi/files')) {
            mlog("Failed to download fileList",2);
            $client->close();
        }
        else{
            $this->compressedData = zstd_uncompress($client->body);
            $client->close();
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
        $download_dir = api::getconfig()['file']['cache_dir'];
        $filePath = $download_dir . '/' . substr($file->hash, 0, 2) . '/';
        if (!file_exists($filePath)) {
            mkdir($filePath, 0777, true);
        }
        $savePath = $filePath . $file->hash;
        $file->path = $this->customUrlEncode($file->path);
        $downloader = $client->download($file->path,$savePath);
        if (!$downloader) {
            mlog("Error connecting to the main control:{$client->errMsg}",2);
            return false;
        } 
        elseif($client->statusCode == 200){
            $bar->progress();
            return true;
        }
        else {
            if(isset($client->getHeaders()['location'])){
            $client->close();

            //预处理下载连接
            $location_url = parse_url($client->getHeaders()['location']);
            $scheme = isset($location_url['scheme']) ? $location_url['scheme'] : '';
            $ssl = $scheme === 'https' ? true : false;

            $client = new Swoole\Coroutine\Http\Client($location_url['host'], $location_url['port'], $ssl);
                $client->set([
                    'timeout' => 60
                ]);
                $client->setHeaders([
                    'Host' => $location_url['host'],
                    'User-Agent' => USERAGENT,
                    'Accept' => '*/*',
                ]);
                $downloader = $client->download($location_url['path'].'?'.($location_url['query']??''),$savePath);
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
                    $downloader = $client->download($location_url['path'].'?'.($location_url['query']??''),$savePath);
                }
                if (!$downloader) {
                    echo PHP_EOL;
                    mlog("{$file->path} Download Failed: {$client->errMsg} | {$location_url['host']}:{$location_url['port']}",2);
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
                    mlog("{$file->path} Download Failed: {$client->errMsg} | {$location_url['host']}:{$location_url['port']}",2);
                    $bar->progress();
                    return false;
                }
                elseif($client->statusCode >= 400){
                    echo PHP_EOL;
                    mlog("{$file->path} Download Failed: {$client->statusCode} | {$location_url['host']}:{$location_url['port']}",2);
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
        $bar->setDetails("[Downloader][线程数:{$this->maxConcurrent}]");
        $bar->display();
        foreach ($this->filesList as $file) {
            global $shouldExit;
            if ($shouldExit) {
                break;
            }
            $this->semaphore->push(true);
            go(function () use ($file,$bar) {
                $client = new Swoole\Coroutine\Http\Client(OPENBMCLAPIURL['host'],OPENBMCLAPIURL['port'],OPENBMCLAPIURL['ssl']);
                $client->set([
                    'timeout' => -1
                ]);
                $client->setHeaders([
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

    public function downloadnopoen($hash) {
        $download_dir = api::getconfig()['file']['cache_dir'];
        $tokenapi = api::getinfo();
        $filePath = $download_dir . '/' . substr($hash, 0, 2) . '/';
        if (!file_exists($filePath)) {
            mkdir($filePath, 0777, true);
        }
        $filepath = "/openbmclapi/download/{$hash}?noopen=1";
        $client = new Swoole\Coroutine\Http\Client(OPENBMCLAPIURL['host'],OPENBMCLAPIURL['port'],OPENBMCLAPIURL['ssl']);
        $client->set([
            'timeout' => -1
        ]);
        $client->setHeaders([
            'User-Agent' => USERAGENT,
            'Accept' => '*/*',
            'Authorization' => "Bearer {$tokenapi['token']}"
        ]);
        $downloader = $client->download($filepath,$download_dir.'/'.substr($hash, 0, 2).'/'.$hash);
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

    public function customUrlEncode($url) {
        // 分割URL并保留斜杠
        $parts = preg_split('/(\/)/', $url, -1, PREG_SPLIT_DELIM_CAPTURE);
    
        foreach ($parts as &$part) {
            if ($part !== '/') {
                $part = rawurlencode($part);
            }
        }
    
        return implode('', $parts);
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
        $download_dir = api::getconfig()['file']['cache_dir'];
        foreach ($this->filesList as $file) {
            global $shouldExit;
            if ($shouldExit) {
                return;
                break;
            }
            if (!file_exists($download_dir.'/'.substr($file->hash, 0, 2).'/'.$file->hash)){
                $this->Missfile[] = new BMCLAPIFile(
                    $file->path,
                    $file->hash,
                    $file->size,
                    $file->mtime
                );
            }
            else{
                if (hash_file('sha1',$download_dir.'/'.substr($file->hash, 0, 2).'/'.$file->hash) != $file->hash) {
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
        $download_dir = api::getconfig()['file']['cache_dir'];
        foreach ($this->filesList as $file) {
            global $shouldExit;
            if ($shouldExit) {
                return;
                break;
            }
            if (!file_exists($download_dir.'/'.substr($file->hash, 0, 2).'/'.$file->hash)){
                $this->Missfile[] = new BMCLAPIFile(
                    $file->path,
                    $file->hash,
                    $file->size,
                    $file->mtime
                );
            }
            else{
                if (filesize($download_dir.'/'.substr($file->hash, 0, 2).'/'.$file->hash) != $file->size) {
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
        $download_dir = api::getconfig()['file']['cache_dir'];
        foreach ($this->filesList as $file) {
            global $shouldExit;
            if ($shouldExit) {
                return;
                break;
            }
            if (!file_exists($download_dir.'/'.substr($file->hash, 0, 2).'/'.$file->hash)){
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