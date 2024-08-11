<?php
require_once('./vendor/autoload.php');
use NyaDAV\NyaDAV;

class webdav{
    private $davp;
    private static $cache = [];
    public $dav;
    public $endpoint;

    public function __construct(){
        $location_url = parse_url(api::getconfig()['file']['webdav']['url']);
        $scheme = isset($location_url['scheme']) ? $location_url['scheme'] : '';
        $ssl = $scheme === 'https' ? true : false;
        $this->dav = new NyaDAV($location_url['host'], $location_url['port'], $ssl);
        $this->dav->set([
            'auth'=>[
                'username' => api::getconfig()['file']['webdav']['username'],
                'password' => api::getconfig()['file']['webdav']['password'],
            ],
            'depth'=> 1
        ]);
        $this->endpoint = api::getconfig()['file']['webdav']['endpoint'];
        $this->dav = $this->dav;
    }
    
    private function createClient() {
        $location_url = parse_url(api::getconfig()['file']['webdav']['url']);
        $scheme = isset($location_url['scheme']) ? $location_url['scheme'] : '';
        $ssl = $scheme === 'https' ? true : false;
        $dav = new NyaDAV($location_url['host'], $location_url['port'], $ssl);
        $dav->set([
            'auth'=>[
                'username' => api::getconfig()['file']['webdav']['username'],
                'password' => api::getconfig()['file']['webdav']['password'],
            ],
            'depth'=> 1
        ]);
        return $dav;
    }

    public function file_exists($path){
        $dav = $this->createClient();
        $t = $dav->file_exists($this->endpoint . $path);
        $dav->close();
        return $t;
    }

    public function getfilesize($path){
        $dav = $this->createClient();
        $result = $dav->getfilesize($this->endpoint . $path);
        $this->dav->close();
        return $result;
    }

    public function getfileurl($path, $hash, $retryCount = 3) {
        // 尝试从缓存中获取URL
        $url = $this->webdavcache($hash);
    
        if (!$url) {
            try {
                // 尝试从WebDAV获取文件
                $dav = $this->createClient();
                $result = $dav->getfile($this->endpoint . $path);
                $this->dav->close();
    
                // 缓存
                $this->webdavcache($hash, $result['raw_url'], $result['size']); // 假设缓存30分钟
    
                return $result;
            } catch (Exception $e) {
                if (strpos($e->getMessage(), "404") !== false) {
                    $download = new download();
                    $download->downloadnopoen($hash, true);
    
                    $url = $this->getfileurl($path, $hash);
                    if (isset($url['raw_url'])) {
                        // 更新缓存
                        $this->webdavcache($hash, $url['raw_url'], $url['size']); // 假设缓存30分钟
                    }
                    return $url;
                }
                mlog("[NyaDAV] " . $e->getMessage(), 2);
                if ($retryCount > 0) {
                    return $this->getfileurl($path, $hash, $retryCount - 1);
                } else {
                    mlog("All WebDAV storage has been downed", 2);
                }
            }
        } else {
            // 如果缓存命中，返回缓存的URL
            return $url;
        }
        exits();
        return false;
    }
    
    public function uploadfile($localpath,$remotepath){
        $dav = $this->createClient();
        $result = $dav->uploadfile($this->endpoint . $remotepath,$localpath);
        $this->dav->close();
        unlink($localpath);
        return $result;
    }

    public function webdavcache($hash, $url = null, $size = null) {
        $time = api::getconfig()['file']['webdav']['CacheTime'];
        $currentTime = time();

        if ($url !== null && $time !== null) {
            $expiryTime = $currentTime + ($time * 60);
            mlog($hash." 写入缓存",1);
            // 存储URL和过期时间戳
            self::$cache[$hash] = [
                'raw_url' => $url,
                'size'=> $size,
                'expiry' => $expiryTime
            ];
        } elseif (isset(self::$cache[$hash])) {
            $cacheEntry = self::$cache[$hash];

            // 检查是否过期
            if ($cacheEntry['expiry'] >= $currentTime) {
                mlog($hash." 缓存命中",1);
                return $cacheEntry;
            } else {
                mlog($hash." 缓存到期",1);
                unset(self::$cache[$hash]);
                return false;
            }
        }
        return false;
    }

}