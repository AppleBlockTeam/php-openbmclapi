<?php
class PluginsManager{
    private $pluginsPath = './plugins';
    
    public function __construct() {
        if (!is_dir($this->pluginsPath)) {
            mkdir($this->pluginsPath, 0777, true);
        }
    }
    
    public function loadPlugins(&$server) {
        $pluginsInfo = [];
        $files = scandir($this->pluginsPath);
        
        foreach ($files as $file) {
            if ($file == '.' || $file == '..' || pathinfo($file, PATHINFO_EXTENSION) != 'php') {
                continue;
            }
            
            $className = "Plugin\\" . basename($file, '.plugin.php'); // 确保类名包含命名空间
            require_once $this->pluginsPath . '/' . $file;
            if (class_exists($className) && in_array('Plugin\PluginInfoInterface', class_implements($className))) {
                try {
                    $pluginInstance = new $className();
                    $pluginsInfo[$className] = $pluginInstance->getInfo();
                    mlog("已加载插件: " . $pluginsInfo[$className]['Name'] . ", 作者: " . $pluginsInfo[$className]['Author'] . ", 版本: " . $pluginsInfo[$className]['Version']);
                    if($pluginsInfo[$className]['ServerSupport']){
                        $pluginInstance->main($server);
                    }
                    else{
                        $pluginInstance->main();
                    }
                } catch (Exception $e) {
                    error_log("Error instantiating plugin class {$className}: " . $e->getMessage());
                }
            }
        }
        
        return $pluginsInfo;
    }
    
}