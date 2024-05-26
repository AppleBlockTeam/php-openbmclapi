<?php
class api{
    private static $config = [];
    private static $info = ['enable'=>false,'isSynchronized'=>true];
    private static $server;
    
    public static function getconfig($newConfig = null) {
        if (!is_null($newConfig)) {
            self::$config = $newConfig;
        }
        return self::$config;
    }

    public static function getinfo($newinfo = null) {
        if (!is_null($newinfo)) {
            self::$info = $newinfo;
        }
        return self::$info;
    }
    public static function getserver($newserver = null) {
        if (!is_null($newserver)) {
            self::$server = $newserver;
        }
        return self::$server;
    }
}