<?php
class webapi{
    public function gettype() {
        $array = [
            'type' => 'php-openbmclapi',
            'openbmclapiVersion' => VERSION,
            'version'=> 'v' . PHPOBAVERSION
        ];
        $type = json_encode($array);
        return $type;
    }
    public function getstatus() {
        $array = [
            'clusterStatus' => [
                "isEnabled" => api::getinfo()['enable'],
                "isSynchronized" => api::getinfo()['isSynchronized'],
                "isTrusted" => true,
                "uptime" => api::getinfo()['uptime'],
                "systemOccupancy" =>[
                    "memoryUsage" => memory_get_usage(),
                    "loadAverage" => sys_getloadavg()[0]
                ]
            ]
        ];
        $type = json_encode($array);
        return $type;
    }
}