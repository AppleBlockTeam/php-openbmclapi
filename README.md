<div align="center">

<picture>
  <img alt="logo" src="https://img.picgo.net/2024/04/04/logobg-2b73c6d349a3ad9f7.png">
</picture>

# OpenBMCLAPI for PHP

✨ 一个基于PHP以及 [Swoole](https://www.swoole.com/) 的 [OpenBMCLAPI](https://github.com/bangbang93/openbmclapi) 节点端 ✨

</div>

## 警告:此版本并不适合在正式环境使用,建议使用其他语言版本节点端

## ⚙️部署
### 环境要求

  建议 PHP 版本 >= 8.0.0
  
  以及对应版本的 [Swoole](https://www.swoole.com/) 和 [Zstd](https://github.com/kjdev/php-ext-zstd) 扩展库

### 源码部署
1. 克隆此仓库到本地:

    ```sh
    git clone https://github.com/Mxmilu666/php-openbmclapi.git
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


## 📃配置文件

```php
待写
```

## 📍Todo
- [ ] 可以正常上线使用(主要)
- [ ] 添加注释
- [ ] Web界面
- [ ] 统一的管理API
- [ ] 插件系统

## 📖许可证
项目采用`Apache-2.0 license`协议开源

## 🫂感谢
[bangbang93/openbmclapi](https://github.com/bangbang93/openbmclapi)

[TTB-Network/python-openbmclapi](https://github.com/TTB-Network/python-openbmclapi/)

[LiterMC/go-openbmclapi](https://github.com/LiterMC/go-openbmclapi)

[SALTWOOD/CSharp-OpenBMCLAPI](https://github.com/SALTWOOD/CSharp-OpenBMCLAPI)
