<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/22
 * Time: 17:32
 */

namespace EShop\Controller;



class UploadController
{
    public function IndexAction() {
        sleep(1);
        if ($_GET['t'] == 0) {
            $lulo = "./uploads/report/";
            $ooxx = "/uploads/report/";
        }elseif($_GET['t'] == 21){
            $lulo = "./uploads/paying/product/";
            $ooxx = "/uploads/paying/product/";
        }elseif($_GET['t'] == 22){
            $lulo = "./uploads/paying/refund/";
            $ooxx = "/uploads/paying/refund/";
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

    /**
     * 上传文件 word excel pdf image text other
     * 留言
     * @date Jan 4,2018
     */

    public function	update_ask_filesAction(){
        sleep(1);
        if ($_GET['t'] == 0) {
            $lulo = "./uploads/asks/";
            $ooxx = "/uploads/asks/";
        } else {
            $lulo = "./uploads/";
            $ooxx = "/uploads/";
        }
        $exts	= '';
        $upload = new \Org\Net\UploadFile();
        //设置上传文件大小
        $upload->maxSize = 5242880;
        //设置上传文件类型
        $upload->allowExts = $exts;
        //设置附件上传目录
        $upload->savePath = $lulo;
        if (!$upload->upload()) {
            //捕获上传异常
            //$this->error($upload->getErrorMsg());
            echo json_encode(array('code'=>1,'message'=>'文件太大，请重新上传！，'.$upload->getErrorMsg()));
            exit();
        } else {
            //取得成功上传的文件信息
            $imgExts	=	explode(',', 'jpg,gif,png,jpeg,JPGE');
            $excelExts	=	explode(',', 'xlsx,xlsm,xltx,xltm,xls,xlsb,xlsm');
            $wordExts	=	explode(',', 'doc,docx,docm,dotx,dotm');
            $uploadList = $upload->getUploadFileInfo();
            $savename = $uploadList[0]['savename'];
            $extension= strtolower($uploadList[0]['extension']);
            $url   = $ooxx . $savename;
            $pic   = '';
            $type  = '';
            //如果是图片
            if(in_array($extension, $imgExts)){
                $pic   = $ooxx . $savename;
                $image = new \Think\Image();
                $image->open('./'.$pic);//打开原图片
                $image->thumb(640,480)->save('./'.$pic);
                $type  = 'image';
                $typeName = '图片';
            }
            //如果是word
            if(in_array($extension, $wordExts)){
                $type  = 'word';
                $typeName = '文档';
            }
            //如果是excel
            if(in_array($extension, $excelExts)){
                $type  = 'excel';
                $typeName = '表格';
            }
            //如果是text
            if($extension == 'txt'){
                $type  = 'text';
                $typeName = '文本';
            }
            //如果是pdf
            if($extension == 'pdf'){
                $type  = 'pdf';
                $typeName = 'pdf';
            }
            //如果都不是的话
            if($type == ''){
                $type  = 'other';
                $typeName = '其他';
            }
            $tagId = time().rand(1,100);
            $data  = [
                'file_url' => $url,
                'pic'      => $pic,
                'type' 	   => $type,
                'type_name'=> $typeName,
                'tag_id'   => $tagId
            ];
            echo json_encode(array('code' => 0, 'message' => '上传成功', 'record' => $data));
            exit();
        }
    }
    //scheduleUploads
    /**
     * 上传文件 word excel pdf image text other
     * 留言
     * @date Jan 4,2018
     */

    public function	scheduleUploadsAction(){
        sleep(1);
        if ($_GET['t'] == 0) {
            $lulo = "./uploads/schedule/";
            $ooxx = "/uploads/schedule/";
        } else {
            $lulo = "./uploads/";
            $ooxx = "/uploads/";
        }
        $exts	= '';
        $upload = new \Org\Net\UploadFile();
        //设置上传文件大小
        $upload->maxSize = 5242880;
        //设置上传文件类型
        $upload->allowExts = $exts;
        //设置附件上传目录
        $upload->savePath = $lulo;
        if (!$upload->upload()) {
            //捕获上传异常
            //$this->error($upload->getErrorMsg());
            echo json_encode(array('code'=>1,'message'=>'文件太大，请重新上传！，'.$upload->getErrorMsg()));
            exit();
        } else {
            //取得成功上传的文件信息
            $imgExts	=	explode(',', 'jpg,gif,png,jpeg,JPGE');
            $excelExts	=	explode(',', 'xlsx,xlsm,xltx,xltm,xls,xlsb,xlsm');
            $wordExts	=	explode(',', 'doc,docx,docm,dotx,dotm');
            $uploadList = $upload->getUploadFileInfo();
            $savename = $uploadList[0]['savename'];
            $extension= strtolower($uploadList[0]['extension']);
            $url   = $ooxx . $savename;
            $pic   = '';
            $type  = '';
            //如果是图片
            if(in_array($extension, $imgExts)){
                $pic   = $ooxx . $savename;
                $image = new \Think\Image();
                $image->open('./'.$pic);//打开原图片
                $image->thumb(640,480)->save('./'.$pic);
                $type  = 'image';
                $typeName = '图片';
            }
            //如果是word
            if(in_array($extension, $wordExts)){
                $type  = 'word';
                $typeName = '文档';
            }
            //如果是excel
            if(in_array($extension, $excelExts)){
                $type  = 'excel';
                $typeName = '表格';
            }
            //如果是text
            if($extension == 'txt'){
                $type  = 'text';
                $typeName = '文本';
            }
            //如果是pdf
            if($extension == 'pdf'){
                $type  = 'pdf';
                $typeName = 'pdf';
            }
            //如果都不是的话
            if($type == ''){
                $type  = 'other';
                $typeName = '其他';
            }
            $tagId = time().rand(1,100);
            $data  = [
                'file_url' => $url,
                'pic'      => $pic,
                'type' 	   => $type,
                'type_name'=> $typeName,
                'tag_id'   => $tagId
            ];
            echo json_encode(array('code' => 0, 'message' => '上传成功', 'record' => $data));
            exit();
        }
    }
}