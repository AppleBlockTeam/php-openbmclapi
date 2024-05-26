<?php
class api{
    private static $config = [];
    private static $info = ['enable'=>false,'isSynchronized'=>false,'uptime'=>0];
    
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
}