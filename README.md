<div align="center">

<picture>
  <img alt="logo" src="https://img.picgo.net/2024/04/04/logobg-2b73c6d349a3ad9f7.png">
</picture>

# OpenBMCLAPI for PHP

✨ 一个基于PHP以及 [Swoole](https://www.swoole.com/) 的 [OpenBMCLAPI](https://github.com/bangbang93/openbmclapi) 节点端 ✨

<a href="https://gitmoji.dev">
  <img
    src="https://img.shields.io/badge/gitmoji-%20😜%20😍-FFDD67.svg?style=flat-square"
    alt="Gitmoji"
  />
</a>

</div>

## 警告:此版本并不适合在正式环境使用,建议使用其他语言版本节点端

## ⚙️ 部署

### 源码部署

#### 环境要求

  建议 PHP 版本 >= 8.0.0
  
  以及对应版本的 [Swoole](https://www.swoole.com/) 和 [Zstd](https://github.com/kjdev/php-ext-zstd) 扩展库

#### 开始部署

1. 克隆此仓库到本地:

    ```sh
    git clone https://github.com/AppleBlockTeam/php-openbmclapi
    cd php-openbmclapi
    ```

2. 安装依赖：

    ```sh
    composer install
    ```

3. 填写 `config.php` 配置文件

4. 运行主程序：

    ```sh
    php main.php
    ```


## 📃 配置文件

```php
<?php
$config=[
    "cluster"=> [
        "host"=> "0.0.0.0",//本地IP
        "port"=> 4000,//本地端口
        "public_host"=> "0.0.0.0",//服务地址
        "public_port"=> 4000,//服务端口
        "CLUSTER_ID"=> "",
        "CLUSTER_SECRET"=> "",
    ],
    "file"=> [
        "cache_dir"=> "./cache",//缓存文件夹
        "check"=> "size",//检查文件策略(hash:检查文件hash size:检查文件大小 exists:检查文件是否存在)
    ],
    "advanced"=> [
        "keepalive"=> 60,//keepalive时间,秒为单位(不建议调整)
        "MaxConcurrent"=> 30,//下载使用的线程
        "Debug"=> false,//Debug开关
    ],
];
```

## 📍 Todo
- [x] 可以正常上线使用(主要)
- [ ] 添加注释
- [ ] Web界面
- [ ] 统一的管理API
- [ ] 插件系统

## 🔖 版本号
PHPOpenBmclApi 采用独立版本号+官方版本号的形式

如:v**0.0.1**-**1.10.3**-**dev**

|  | 0.0.1 | 1.10.3 | dev |
|--|-------|--------|--------|
| 名称 | **版本号** | **兼容版本号** | **版本说明** |
| 解释 | 表示PHPOpenBmclApi的独立版本号 | 表示该版本等效于 [OpenBMCLAPI](https://github.com/bangbang93/openbmclapi) 的哪个版本 | 如dev是开发版,发布版不会有版本说明 |

## 📖 许可证
项目采用`Apache-2.0 license`协议开源

## 🫂 鸣谢

**Swoole**
- [Swoole](https://www.swoole.com) - 提供高性能协程和 Https 服务器，让 PHP 不再局限于 Web 领域

**kjdev**
- [php-ext-zstd](https://github.com/kjdev/php-ext-zstd) - 提供 PHP 中的 Zstd 解压，使得可以获得文件列表

**[TTB-Network](https://github.com/TTB-Network)**
- [Python-OpenBmclApi](https://github.com/TTB-Network/python-openbmclapi) - 提供了原生实现 Avro 解析的逻辑，部分解析逻辑和 README 文件的参考
- 为我解决了一堆 ~~小白~~ 问题，对项目的实现起到了很大的作用

**[SALTWOOD](https://github.com/SALTWOOD)**
- [CSharp-OpenBMCLAPI](https://github.com/SALTWOOD/CSharp-OpenBMCLAPI) - 提供了 README 文件的参考

**[LiterMC](https://github.com/LiterMC)**
- [socket.io](https://github.com/LiterMC/socket.io) - 提供了自写 socket.io 的思路

**crazywhalecc**
- [static-php-cli](https://github.com/crazywhalecc/static-php-cli) - 提供了构建独立的 PHP 二进制文件，并含流行的扩展的方案


## ❤ 友情链接
[bangbang93/openbmclapi](https://github.com/bangbang93/openbmclapi)

[TTB-Network/python-openbmclapi](https://github.com/TTB-Network/python-openbmclapi)

[LiterMC/go-openbmclapi](https://github.com/LiterMC/go-openbmclapi)

[SALTWOOD/CSharp-OpenBMCLAPI](https://github.com/SALTWOOD/CSharp-OpenBMCLAPI)
