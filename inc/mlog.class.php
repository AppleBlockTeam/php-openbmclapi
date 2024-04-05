<?php
function mlog($content, $type = 0,$type2 = 1)
    {
        global $config;
        switch ($type) {
            case 0:
                echo "[".date('Y.n.j-H:i:s')."]"."[INFO]".$content.PHP_EOL;
                break;
            case 1:
                if ($config['Debug'] == true){
                    echo "[".date('Y.n.j-H:i:s')."]"."[Debug]".$content.PHP_EOL;
                    break;
                }
                else{
                    break;
                }
            case 2:
                echo "[".date('Y.n.j-H:i:s')."]"."[Error]".$content.PHP_EOL;
                break;
            default:
                trigger_error("Type {$type} No Found", E_USER_ERROR);
                break;
        }
    }