<?php

namespace ESAdmin\Controller;
use Common\Lib\Controller\ControllerBase;

class SystemController extends ControllerBase {
    public function clearCacheAction(){
        $this->delFile("./Runtime/");
        $this->ajaxReturn(buildMessage("清除完成"));
    }

    function delFile($dir, $file_type = '') {
        if (is_dir($dir)) {
            $files = scandir($dir);
            //打开目录 //列出目录中的所有文件并去掉 . 和 ..
            foreach ($files as $filename) {
                if ($filename != '.' && $filename != '..') {
                    if (!is_dir($dir . '/' . $filename)) {
                        if (empty($file_type)) {
                            unlink($dir . '/' . $filename);
                        } else {
                            if (is_array($file_type)) {
                                //正则匹配指定文件
                                if (preg_match($file_type[0], $filename)) {
                                    unlink($dir . '/' . $filename);
                                }
                            } else {
                                //指定包含某些字符串的文件
                                if (false != stristr($filename, $file_type)) {
                                    unlink($dir . '/' . $filename);
                                }
                            }
                        }
                    } else {
                        $this->delFile($dir . '/' . $filename);
                        rmdir($dir . '/' . $filename);
                    }
                }
            }
        } else {
            if (file_exists($dir))
                unlink($dir);
        }
    }
}
