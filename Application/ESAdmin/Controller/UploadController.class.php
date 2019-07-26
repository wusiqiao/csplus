<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/22
 * Time: 17:32
 */

namespace ESAdmin\Controller;



use Think\Controller;
use Think\Upload;

class UploadController extends Controller
{
    protected $storage;
    public function __construct()
    {
        $this->handlerDriverLoadData();
    }

    public function IndexAction() {
        sleep(1);
        if ($_GET['t'] == 0) {
            $lulo = "./uploads/routine/";
            $ooxx = "/uploads/routine/";
        }elseif($_GET['t'] == 1){
            $lulo = "./uploads/product/";
            $ooxx = "/uploads/product/";
        }elseif ($_GET['t'] == 2){
            $lulo = "./uploads/banner/";
            $ooxx = "/uploads/banner/";
        }
        $upload = new \Org\Net\UploadFile();
        //设置上传文件大小
        $upload->maxSize = 5242880;
        //设置上传文件类型
        $upload->allowExts = explode(',', 'jpg,gif,png,jpeg,JPEG');
        //设置附件上传目录
        $upload->savePath = $lulo;
        if (!$upload->upload()) {
            //捕获上传异常

            echo json_encode(array('code'=>1,'message'=>'图片太大，请重新上传！，'.$upload->getErrorMsg()));
            exit();
        } else {
            //取得成功上传的文件信息
            $uploadList = $upload->getUploadFileInfo();
            $savename = $uploadList[0]['savename'];
            $pic   = $ooxx . $savename;
            $image = new \Think\Image();
            $image->open('./'.$pic);//打开原图片
            $image->thumb(1280,960)->save('./'.$pic);
            echo json_encode(array('code' => 0, 'message' => '上传成功', 'file_url' => $savename, 'pic' => $pic));
            exit();
        }
    }
    public function documentUpload(){
        $info = $this->QiNiuUpload();
        return $info ?? false;
    }
    //七牛上传配置
    protected function QiNiuUpload($file = null,$options = null)
    {
        $file = $file ?? $_FILES;
        $config = [
            'maxSize' => 5368709120,
            'rootPath' => './',
            'saveName' => array('uniqid', ''),
            'allowExts' => explode(',', 'jpg,gif,png,jpeg,JPEG'),
            'driver' => $this->storage->driver['QiNiu']['driver'],
            'driverConfig' => $this->storage->driver['QiNiu']['driverConfig']
        ];
        if (!empty($options)) {
            $config = array_merge($config,$options);
        }
        $upload = new \Think\Upload($config);

        $info = $upload->upload($file);
        return $info;
    }
    //七牛删除文件处理
    public  function QiNiuDelete($file = []){
        $file = $file ?? $_FILES;
        $upload = new \Think\Upload\Driver\Qiniu\QiniuStorage($this->storage->driver['QiNiu']['driverConfig']);
        $info = $upload->del($file);
        return $info;
    }
    //上传配置
    private function handlerDriverLoadData(){
        $this->storage->driver =[
            'QiNiu' =>[
                'driver' => 'Qiniu',
                'driverConfig' => [
                    'secrectKey'     => '6PI73ne547lpifajU9oVNTZaH8cIrLS8OWtdSV34', //七牛服务器
                    'accessKey'      => 'FtRPedCaXlUpK39HhesffLrOtZHgJToZYDAa8jMe', //七牛用户
                    'domain'         => 'qiniu.caisuikx.com', //七牛密码
                    'bucket'         => 'documents', //空间名称
//                    'timeout'        => 300, //超时时间
                ]
            ]
        ];
    }
}