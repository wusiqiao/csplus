<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\ControllerBase;

/**
 * 图片上传接口
 * */
class ComUploadsController extends ControllerBase {
    /**
     * 文件上传存放位置
     * */
    const UPLOADS_DIR = DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'asks' . DIRECTORY_SEPARATOR;
    /**
     * 图片上传
     * */
    public function uploadsImgAction(){
        if(IS_POST){
            $upload = new \Think\Upload($this->setUploadsImg());
            $info = $upload->upload();
            if(!$info){
                return $this->ajaxReturn(['code' => 1, 'message' => $upload->getError()]);
            }

            $paths = [];
            foreach($info as $file){
                array_push($paths,[
                    'url' =>  str_replace('\\', '/', self::UPLOADS_DIR . $file['savepath']) . $file['savename'],
                    'name' => $file['savename'],
                    'type' => $file['type'],
                ]);
            }

            return $this->ajaxReturn(['code' => 0, 'message' => '上传成功!', 'data' => ['images' => $paths]]);
        }
    }

    /**
     * 设置图片上传配置
     * */
    private function setUploadsImg(){
        return [
            'maxSize' => '',
            'exts' => ['jpg', 'png', 'jpeg'],
            'rootPath' => dirname(THINK_PATH) . self::UPLOADS_DIR,
        ];
    }
}
