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

    public function dataToHours() {
        $base_dir = api::getconfig();
        $dirPath = $base_dir['file']['database_dir'];
        $currentHourTimestamp = strtotime(date('Y-m-d H:00:00'));
        $twelveHoursAgoTimestamp = $currentHourTimestamp - (12 * 3600);
        $formattedData = [];
    
        // 处理今天的数据
        $todayData = @file_get_contents($dirPath . '/' . date('Ymd'));
        $dataArrayToday = json_decode($todayData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            mlog("Error decoding today's JSON: " . json_last_error_msg(),2);
            return [];
        }
        
        // 如果需要包含昨天的数据
        $yesterdayKey = date('Ymd', strtotime('-1 day'));
        if (strtotime(date('Y-m-d')) !== strtotime($yesterdayKey)) {
            if (!file_exists($dirPath . '/' . $yesterdayKey)) {
                $database = new Database;
                $database->initializeDatabase(true);
            }
            $yesterdayData = @file_get_contents($dirPath . '/' . $yesterdayKey);
            $dataArrayYesterday = json_decode($yesterdayData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                mlog("Error decoding yesterday's JSON: " . json_last_error_msg(),2);
                return [];
            }
            $dataArray = array_replace($dataArrayYesterday, $dataArrayToday);
        } else {
            $dataArray = $dataArrayToday;
        }

        // 统一处理数据
        foreach ($dataArray as $key => $value) {
            $dateStr = substr($key, 0, 8);
            $hour = intval(substr($key, 8));
            $timestamp = mktime(0, 0, 0, substr($dateStr, 4, 2), substr($dateStr, 6, 2), substr($dateStr, 0, 4)) + ($hour * 3600);
            if ($timestamp >= $twelveHoursAgoTimestamp && $timestamp < $currentHourTimestamp) {
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