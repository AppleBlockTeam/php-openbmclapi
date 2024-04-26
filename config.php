<?php
$config=[
    "cluster"=> [
        "host"=> "0.0.0.0",//本地IP
        "port"=> 4000,//本地端口
        "public_host"=> "0.0.0.0",//服务地址
        "public_port"=> 4000,//服务端口
        "CLUSTER_ID"=> "",
        "CLUSTER_SECRET"=> "",
        "cache_dir"=> "./cache",//缓存文件夹
    ],
    "advanced"=> [
        "MaxConcurrent"=> 10,//下载使用的线程
        "Debug"=> false,//Debug开关
    ],
];