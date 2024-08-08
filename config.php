<?php
$config=[
    "cluster"=> [
        "host"=> "0.0.0.0",//本地IP
        "port"=> 4000,//本地端口
        "public_host"=> "0.0.0.0",//服务地址
        "public_port"=> 4000,//服务端口
        "CLUSTER_ID"=> "",
        "CLUSTER_SECRET"=> "",
        "byoc"=> false,
        "certificates"=>[ //如果 byoc 关闭，以下设置默认禁用
            "use-cert"=> false, //是否使用自己的证书
            "cert"=> "/path/to/cert.crt",
            "key"=> "/path/to/key.key",
        ],
    ],
    "file"=> [
        "cache_dir"=> "./cache",//文件路径(如开启 webdav 为缓存目录)
        "check"=> "size",//检查文件策略(hash:检查文件hash size:检查文件大小 exists:检查文件是否存在)
        "database_dir"=> "./database",//访问数据数据库目录
        "webdav"=>[
            "support"=> false,//webdav 支持,开启后自动关闭本地模式
            "url"=> "http://114514.com:1145/",
            "endpoint"=> "/dav/download",
            "username"=> "114514",
            "password"=> "114514",
            "MaxConcurrent"=> 60,//同步使用的线程
        ]
    ],
    "advanced"=> [
        "Centerurl"=> "https://openbmclapi.staging.bangbang93.com",//主控链接(不建议调整)
        "keepalive"=> 60,//保活时间,秒为单位(不建议调整)
        "MaxConcurrent"=> 30,//同步/下载使用的线程
        "Debug"=> false,//调试开关
    ],
];