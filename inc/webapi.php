<?php
use Swoole\Lock;
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
    public function getinfo() {

        $array = [
            'data' => [
                "hours" => $this->DataTohours(),
                "days" => $this->DataTodays(),
                "months" => $this->DataTomonths()
            ]
        ];
        $type = json_encode($array);
        return $type;
    }

    public function DataTohours() {
    $base_dir = api::getconfig();
    $dirPath = $base_dir['file']['database_dir'];
    $data = file_get_contents($dirPath . '/' . date('Ymd')); // 读取文件内容
    $dataArray = json_decode($data, true); // 解码JSON数据为关联数组
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Error decoding JSON: " . json_last_error_msg();
        return [];
    }
    
    $formattedData = [];
    $currentHourTimestamp = strtotime(date('Y-m-d H:00:00', time())); // 当前小时的时间戳
    $twelveHoursAgoTimestamp = $currentHourTimestamp - (12 * 3600); // 12小时前的时间戳
    foreach ($dataArray as $key => $value) {
        // 提取日期时间字符串的前8位为日期（如20240610），后两位为小时
        $dateStr = substr($key, 0, 8);
        $hour = intval(substr($key, 8));
        // 假设日期格式为YYYYMMDD，转换为Unix时间戳（这里未考虑时区，实际应用中可能需要调整）
        $timestamp = mktime(0, 0, 0, substr($dateStr, 4, 2), substr($dateStr, 6, 2), substr($dateStr, 0, 4)) + ($hour * 3600);
        if ($timestamp >= $twelveHoursAgoTimestamp && $timestamp < $currentHourTimestamp){
            $formattedData[] = [
                'timestamp' => $timestamp,
                'hits' => $value['hits'],
                'bytes' => $value['bytes']
            ];
        }
    }
    return $formattedData;
    }

    public function DataTodays() {
    $database = new Database();
    $data = $database->getDaysData();
    $formattedData = [];
    foreach ($data as $date => $stats) {
        $timestamp = strtotime($date);
        $formattedData[] = [
            'timestamp' => $timestamp,
            'hits' => $stats['hits'],
            'bytes' => $stats['bytes']
        ];
    }

    return $formattedData;
    }

    public function DataTomonths() {
    $database = new Database();
    $data = $database->getMonthsData();
    $formattedData = [];
    foreach ($data as $date => $stats) {
        $timestamp = strtotime($date . 00);
        $formattedData[] = [
            'timestamp' => $timestamp,
            'hits' => $stats['hits'],
            'bytes' => $stats['bytes']
        ];
    }
    return $formattedData;
    }
}