<?php
use Swoole\Coroutine\Lock;
class Database {
    private $dirPath;

    public function __construct() {
        if (!$this->dirPath) {
            $base_dir = api::getconfig();
            $this->dirPath = $base_dir['file']['database_dir'];
        }
    }

    public function initializeDatabase($useYesterday = false) {
        $date = $useYesterday ? date('Ymd', strtotime('-1 day')) : date('Ymd');
        $filename = $this->dirPath . '/' . $date;
        
        if (!is_dir($this->dirPath)) {
            mkdir($this->dirPath, 0777, true);
        }
        
        if (!file_exists($filename)) {
            $initialData = [];
            for ($hour = 0; $hour < 24; ++$hour) {
                $timeKey = $date . str_pad($hour, 2, '0', STR_PAD_LEFT);
                $initialData[$timeKey] = ['hits' => 0, 'bytes' => 0];
            }
            file_put_contents($filename, json_encode($initialData, JSON_PRETTY_PRINT));
        }
    }
    

    public function writeDatabase($hits, $bytes) {
        $filename = $this->dirPath . '/' . date('Ymd');
        if (!file_exists($filename)) {
            $this->initializeDatabase();
        }
        $data = json_decode(Swoole\Coroutine\System::readFile($filename), true);
        $timeKey = date('Ymd') . str_pad(date('G'), 2, '0', STR_PAD_LEFT);
        if (!isset($data[$timeKey])) {
            $data[$timeKey] = ['hits' => 0, 'bytes' => 0];
        }
        $data[$timeKey]['hits'] += $hits;
        $data[$timeKey]['bytes'] += $bytes;
        $w = Swoole\Coroutine\System::writeFile($filename, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function getDaysData(): array {
    $dailyTraffic = [];
    $endDate = date('Ymd');
    $startDate = date('Ymd', strtotime('-14 days'));
    
    for ($i = 0; $i <= 14; ++$i) {
        $date = date('Ymd', strtotime("-$i days", strtotime($endDate)));
        $filename = $this->dirPath . '/' . $date;
        $dailySummary = ['hits' => 0, 'bytes' => 0];
        if (file_exists($filename)) {
            $data = json_decode(Swoole\Coroutine\System::readFile($filename), true);
            foreach ($data as $hourlyRecord) {
                if (isset($hourlyRecord['hits']) && isset($hourlyRecord['bytes'])) {
                    $dailySummary['hits'] += $hourlyRecord['hits'];
                    $dailySummary['bytes'] += $hourlyRecord['bytes'];
                }
            }
        }
        $dailyTraffic[$date] = $dailySummary;
    }
    ksort($dailyTraffic);
    return $dailyTraffic;
    }

    public function getMonthsData(): array {
    $monthlyTraffic = [];
    $endDate = new DateTime(); // 获取今天日期
    $startDate = clone $endDate;
    $startDate->modify('-11 months'); // 回溯11个月，以包含完整的12个月数据

    while ($startDate <= $endDate) {
        $monthStr = $startDate->format('Ym'); // 格式化为YYYYMM格式
        $monthlySummary = ['hits' => 0, 'bytes' => 0];
        
        for ($day = 1; $day <= $startDate->format('t'); ++$day) { // 遍历当月的所有天
            $dateStr = $startDate->format('Ymd');
            $filename = $this->dirPath . '/' . $dateStr;
            
            if (file_exists($filename)) {
                $data = json_decode(file_get_contents($filename), true);
                
                foreach ($data as $hourlyRecord) {
                    if (isset($hourlyRecord['hits']) && isset($hourlyRecord['bytes'])) {
                        $monthlySummary['hits'] += $hourlyRecord['hits'];
                        $monthlySummary['bytes'] += $hourlyRecord['bytes'];
                    }
                }
            }
            $startDate->modify('+1 day'); // 移动到下一天
        }
        
        // 累加完一个月的数据后存入结果数组
        $monthlyTraffic[$monthStr] = $monthlySummary;
    }

    return $monthlyTraffic;
    }

    public function getYearsData(): array {
    $annualTraffic = [];
    $currentYear = (int)date('Y');
    $startYear = $currentYear - 5;

    for ($year = $startYear; $year <= $currentYear; ++$year) {
        $yearHits = 0;
        $yearBytes = 0;

        for ($month = 1; $month <= 12; ++$month) {
            for ($day = 1; $day <= 31; ++$day) { // 假定每月最多31天，实际应用需按月份调整
                $date = sprintf('%04d%02d%02d', $year, $month, $day);
                $filename = $this->dirPath . '/' . $date;
                
                if (file_exists($filename)) {
                    $data = json_decode(Swoole\Coroutine\System::readFile($filename), true);
                    foreach ($data as $hourlyRecord) {
                        if (isset($hourlyRecord['hits']) && isset($hourlyRecord['bytes'])) {
                            $yearHits += $hourlyRecord['hits'];
                            $yearBytes += $hourlyRecord['bytes'];
                        }
                    }
                }
            }
        }

        // 累计完一年的数据后存入结果数组
        $annualTraffic[$year] = [
            'hits' => $yearHits,
            'bytes' => $yearBytes
        ];
    }

    return $annualTraffic;
    }
}