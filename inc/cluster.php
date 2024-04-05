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
        if (!file_exists("./cache/filecache")) {
            mkdir("./cache/filecache",0777,true);
        }
        $client = new Client(OPENBMCLAPI,443,true);
        $client->set(['timeout' => -1]);
        $client->setHeaders([
            'Host' => OPENBMCLAPI,
            'User-Agent' => 'openbmclapi-cluster/'.$this->version,
            'Accept' => '*',
            'Authorization' => "Bearer {$this->token}"
        ]);
        mlog("Start FileList Download");
        if (!$client->download('/openbmclapi/files',DOWNLOAD_DIR.'/filecache/filelist.zstd')) {
            mlog("FileList Download Failed",2);
            $client->close();
        }
        else{
            mlog("FileList Download Success");
            $client->close();
            $this->compressedData = file_get_contents("compress.zstd://".DOWNLOAD_DIR."/filecache/filelist.zstd");
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

    public function __construct($filesList, $maxConcurrent) {
        $this->filesList = $filesList;
        $this->maxConcurrent = $maxConcurrent;
        $this->semaphore = new Swoole\Coroutine\Channel($maxConcurrent);
    }

    private function downloader(Swoole\Coroutine\Http\Client $client, $file,$bar) {
        $filePath = DOWNLOAD_DIR . '/' . substr($file->hash, 0, 2) . '/';
        if (!file_exists($filePath)) {
            mkdir($filePath, 0777, true);
        }
        $savePath = $filePath . $file->hash;
        if (!$client->get($file->path)) {
            mlog("Error connecting to the main control:{$client->errMsg}",2);
            return false;
        } else {
            $location_url = parse_url($client->getHeaders()['location']);
            $client->close();
            $client = new Swoole\Coroutine\Http\Client($location_url['host'], $location_url['port'], true);
                $client->set([
                    'timeout' => 60
                ]);
                $client->setHeaders([
                    'Host' => $location_url['host'],
                    'User-Agent' => 'openbmclapi-cluster/' . VERSION . '    ' . 'PHP-OpenBmclApi/0.0.1-dev',
                    'Accept' => '*/*',
                ]);
                $downloadr = $client->download($location_url['path'].'?'.($location_url['query']??''),DOWNLOAD_DIR.'/'.substr($file->hash, 0, 2).'/'.$file->hash);
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
                        'User-Agent' => 'openbmclapi-cluster/' . VERSION . '    ' . 'PHP-OpenBmclApi/0.0.1-dev',
                        'Accept' => '*/*',
                    ]);
                    $downloadr = $client->download($location_url['path'].'?'.($location_url['query']??''),DOWNLOAD_DIR.'/'.substr($file->hash, 0, 2).'/'.$file->hash);
                }
                if (!$downloadr) {
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
                if (!$downloadr) {
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
    }

    public function downloadFiles() {
        $bar = new CliProgressBar(count($this->filesList));
        $bar->setDetails("[Downloader]");
        $bar->display();
        foreach ($this->filesList as $file) {
            $this->semaphore->push(true);
            go(function () use ($file,$bar) {
                $client = new Swoole\Coroutine\Http\Client('openbmclapi.bangbang93.com', 443, true);
                $client->set([
                    'timeout' => -1
                ]);
                $client->setHeaders([
                    'Host' => 'openbmclapi.bangbang93.com',
                    'User-Agent' => 'openbmclapi-cluster/' . VERSION . ' ' . 'PHP-OpenBmclApi/0.0.1-dev',
                    'Accept' => '*/*',
                ]);
                if ($this->downloader($client, $file,$bar)) {
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
}

class FilesCheck {
    private $filesList;
    private $Missfile;

    public function __construct($filesList) {
        $this->filesList = $filesList;
    }

    public function FilesCheckerhash() {
        $bar = new CliProgressBar(count($this->filesList));
        $bar->setDetails("[FileCheck]");
        $bar->display();
        foreach ($this->filesList as $file) {
            if (!file_exists(DOWNLOAD_DIR.'/'.substr($file->hash, 0, 2).'/'.$file->hash)){
                $this->Missfile[] = new BMCLAPIFile(
                    $file->path,
                    $file->hash,
                    $file->size,
                    $file->mtime
                );
            }
            else{
                if (hash_file('sha1',DOWNLOAD_DIR.'/'.substr($file->hash, 0, 2).'/'.$file->hash) != $file->hash) {
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

}