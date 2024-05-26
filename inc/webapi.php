<?php
class webapi{
    public function gettype() {
        $array = [
            'code' => 200,
            'msg' => 'success',
            'type' => 'php-openbmclapi',
            'version'=> PHPOBAVERSION . "-" . VERSION
        ];
        $type = json_encode($array);
        return $type;
    }
    public function getstatus() {
        $array = [
            'code' => 200,
            'msg' => 'success',
            'data' => [
                "isEnabled" => api::getinfo()['enable'],
                "isSynchronized" => api::getinfo()['isSynchronized'],
                "isTrusted" => true,
                "uptime" => api::getinfo()['uptime']
            ]
        ];
        $type = json_encode($array);
        return $type;
    }
}