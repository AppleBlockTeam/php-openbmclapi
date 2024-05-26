<?php

function mlog($content, $type = 0, $minimalFormat = false)
{
    $logTypes = [
        0 => 'INFO',
        1 => 'DEBUG',
        2 => 'ERROR'
    ];

    if (!isset($logTypes[$type])) {
        trigger_error("Type {$type} not found", E_USER_ERROR);
        return;
    }

    $logDir = 'logs/';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timePrefix = !$minimalFormat ? '[' . date('Y.n.j-H:i:s') . ']' : '';
    $levelPrefix = !$minimalFormat ? '[' . strtoupper($logTypes[$type]) . ']' : '';
    $logEntry = $timePrefix . $levelPrefix . $content . PHP_EOL;

    // 写入日志文件
    if ($type !== 1 || (isset($GLOBALS['config']['advanced']['Debug']) && $GLOBALS['config']['advanced']['Debug'])) {
        $logFile = $logDir . date('Y-n-j') . '.log';
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    // 输出到控制台
    switch ($type) {
        case 0: // INFO
            echo $logEntry;
            break;
        case 1: // DEBUG
            if (isDebugMode()) {
                echo $logEntry;
            }
            break;
        case 2: // ERROR
            echo $logEntry;
            break;
    }
}

function isDebugMode()
{
    return isset(api::getconfig()['advanced']['Debug']) && api::getconfig()['advanced']['Debug'];
}