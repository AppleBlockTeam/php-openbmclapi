<div align="center">

![](https://img.picgo.net/2024/04/04/logobg-2b73c6d349a3ad9f7.png)

# OpenBMCLAPI for PHP

✨ 一个基于PHP以及 [Swoole](https://www.swoole.com/) 的 [OpenBMCLAPI](https://github.com/bangbang93/openbmclapi) 节点端 ✨

![PHP](https://img.shields.io/badge/PHP-%3E=8.0.0-blue?logo=PHP&style=flat-square)
![Swoole](https://img.shields.io/badge/Swoole-%3E=5.1.0-blue?style=flat-square)
![gitmoji](https://img.shields.io/badge/gitmoji-%20😜%20😍-FFDD67.svg?style=flat-square)


![GitHub top language](https://img.shields.io/github/languages/top/AppleBlockTeam/php-openbmclapi?style=flat-square)
![GitHub License](https://img.shields.io/github/license/AppleBlockTeam/php-openbmclapi?style=flat-square)
![GitHub Release](https://img.shields.io/github/v/release/AppleBlockTeam/php-openbmclapi?style=flat-square)
![GitHub Repo stars](https://img.shields.io/github/stars/AppleBlockTeam/php-openbmclapi?style=flat-square)

</div>

## ⚙️ 部署

### 源码部署

#### 环境要求

  PHP 版本 >= 8.0.0

  [Swoole](https://www.swoole.com/) 版本 >= 5.1.
  
  以及对应版本的 [Zstd](https://github.com/kjdev/php-ext-zstd)

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
- [ ] Web仪表盘(主要)
- [ ] 支持WebDAV
- [ ] 打包二进制文件
- [ ] 完善Log系统
- [ ] 添加注释
- [x] 可以正常上线使用

## ❓ FAQ

### 🔖 版本号
PHPOpenBmclApi 采用独立版本号+官方版本号的形式

如:v**0.0.1**-**1.10.3**-**dev**

|  | 0.0.1 | 1.10.3 | dev |
|--|-------|--------|--------|
| 名称 | **版本号** | **兼容版本号** | **版本说明** |
| 解释 | 表示PHPOpenBmclApi的独立版本号 | 表示该版本等效于 [OpenBMCLAPI](https://github.com/bangbang93/openbmclapi) 的哪个版本 | 如dev是开发版,发布版不会有版本说明 |

### 🎉 贡献说明
如果你想为本项目做出贡献，请遵守以下规则：
* 所有请求请提交到dev分支，提交到main分支将会被关闭
* 每条 commit 请认真填写信息，最好使用 [gitmoji](https://gitmoji.dev) 规范

### ❔️ 常见问题
1. 为什么我到1000左右连接数就无法继续提供服务？
* Swoole默认Http服务器连接数是根据 `ulimit -n` 来设定的，如果连接数过小建议自行调整 ulimit

2. 我不想安装PHP环境怎么办？
* 你可以使用 [static-php-cli](https://github.com/crazywhalecc/static-php-cli) 或者等我打包出 phar 文件后封装成二进制程序（TODO）

3. 为什么不支持多 Webdav /存储？
* 因为我跟随 Node 版，以 Node 版为规范，并且 BangBang93 一向反对单节点多存储，所以不考虑支持

4. 为什么会出现一些奇奇怪怪的故障？
* 请提交 issues 进一步解决

## 📖 许可证
项目采用 `Apache-2.0 license` 协议开源

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
