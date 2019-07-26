<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/29
 * Time: 8:45
 */

namespace EShop\Model;

use Think\Model;
class ComStoreModel extends Model{

    public function getQRcode(){
        $branch_id = getBrowseBranchId();
        //获取微信二维码
        $save_path = MODULE_UPLOAD_PATH. "storeqrcode";
        $file = $save_path."/".$branch_id.".png";
        if (is_file($file)){
            return get_head_pic($file)."?rand=".time();
        }else{
            $wechat = getWeChatInstance();
            $json = $wechat->getQRCode($branch_id, 1);
            if ($json['ticket']){
                $img_url = $wechat->getQRUrl($json['ticket']);
                $QRCodeImg = imagecreatefromstring(curl_get_contents($img_url));
                $store_data = M("ComStore")->where("branch_id=$branch_id")->find();
                if ($store_data && $store_data["default_header_pic"]){
                    if ($pic_data = file_get_contents(get_head_pic($store_data["default_header_pic"]))){
                        $logoImg = imagecreatefromstring($pic_data);
                        $QR_width = imagesx($QRCodeImg); //二维码图片宽度
                        $QR_height = imagesy($QRCodeImg); //二维码图片高度
                        $logo_width = imagesx($logoImg); //logo图片宽度
                        $logo_height = imagesy($logoImg); //logo图片高度
                        $logo_qr_width = $QR_width / 4;
                        $scale = $logo_width / $logo_qr_width;
                        $logo_qr_height = $logo_height / $scale;
                        $from_width = ($QR_width - $logo_qr_width) / 2;
                        //重新组合图片并调整大小
                        imagecopyresampled($QRCodeImg, $logoImg, $from_width, $from_width, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);
                    }
                }
                mkdir($save_path, 0777, true);
                imagepng($QRCodeImg, $file);
                return get_head_pic($file)."?rand=".time();
            }
        }
    }
}