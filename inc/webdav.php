<?php
require_once('./vendor/autoload.php');
use NyaDAV\NyaDAV;

class webdav{
    private $davp;
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

    public function getfileurl($path){
        $dav = $this->createClient();
        $result = $dav->getfile($this->endpoint . $path);
        $this->dav->close();
        print_r($result);
        return $result;
    }
    public function uploadfile($localpath,$remotepath){
        $dav = $this->createClient();
        $result = $dav->uploadfile($this->endpoint . $remotepath,$localpath);
        $this->dav->close();
        return $result;
    }
}