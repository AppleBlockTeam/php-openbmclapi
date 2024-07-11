<?php
$config=[
    "cluster"=> [
        "host"=> "0.0.0.0",//本地IP
        "port"=> 4000,//本地端口
        "public_host"=> "0.0.0.0",//服务地址
        "public_port"=> 4000,//服务端口
        "CLUSTER_ID"=> "",
        "CLUSTER_SECRET"=> "",
        "byoc"=>false,
    ],
    "file"=> [
        "cache_dir"=> "./cache",//缓存文件夹
        "check"=> "size",//检查文件策略(hash:检查文件hash size:检查文件大小 exists:检查文件是否存在)
        "database_dir"=> "./database",//访问数据数据库目录
    ],
    "advanced"=> [
        "CenterUrl"=> "https://openbmclapi.staging.bangbang93.com",//主控链接(不建议调整)
        "keepalive"=> 60,//keepalive时间,秒为单位(不建议调整)
        "MaxConcurrent"=> 30,//下载使用的线程
        "Debug"=> false,//Debug开关
    ],
];