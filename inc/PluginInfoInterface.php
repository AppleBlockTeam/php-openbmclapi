<?php
namespace Plugin;

interface PluginInfoInterface {
    public function getInfo();
    public function main();
}