<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019-04-08
 * Time: 13:38
 */

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class MaterialCenterModel extends DataModel
{
    /**
     * 来源 -- 爬虫抓取
     * */
    const SOURCE_10 = 10;
    /**
     * 来源 -- 后台上传
     * */
    const SOURCE_20 = 20;

    /**
     * 状态 -- 等待上架；
     * */
    const STATUS_10 = 10;
    /**
     * 状态 -- 已上架；
     * */
    const STATUS_20 = 20;

    /**
     * 是否收费 -- 不需要
     * */
    const IS_TOLL_10 = 10;
    /**
     * 状态 -- 需要；
     * */
    const IS_TOLL_20 = 20;

    /**
     * @var 图片文件存放地址!
     * */
    const FILE_PATH = './uploads/asks/huateng/';

    /**
     * 上架操作
     * */
    public function shelf($id)
    {
        $data['shelf_time'] = time();
        $data['shelf_status'] = self::STATUS_20;
//        $sql = $this->fetchSql(true)->where(['id' => ':id'])->bind(':id', $id)->save($data);
        if ($this->where(['id' => ':id'])->bind(':id', $id)->save($data)) {
            return true;
        }

        return false;
    }

    public function batchShelfOrlower($ids = [], $type){
        $data['shelf_time'] = time();
        if($type == 'lower'){
            $data['shelf_status'] = self::STATUS_10;
        }else{
            $data['shelf_status'] = self::STATUS_20;
        }

        if($this->where(['id' => ['in', $ids]])->save($data)){
            return true;
        }

        return false;
    }
    /**
     * 下架操作
     * */
    public function lowerShelf($id)
    {
        $data['shelf_status'] = self::STATUS_10;
        if ($this->where(['id' => ':id'])->bind(':id', $id)->save($data)) {
            return true;
        }

        return false;
    }

    /**
     * 去除因本地显示，而修改的图片路径
     * */
    public function cleanlocalImgShow($content)
    {
        return str_replace($this->getShowImgUrl(), '', $content, $count);
    }

    /**
     * 处理非微信图片上传到微信服务器
     * */
    public function localUploadServiceImg($content){
        $instance = getWeChatInstance();
        $content = $this->cleanShowImgUrl($content);
        //匹配 img
        $preg = '/<img((?!src).)*src[\s]*=[\s]*[\'"](?<src>[^\'"]*)[\'"]/i';
        preg_match_all($preg, $content, $imgs);
        $srcImg = isset($imgs['src']) ? $imgs['src'] : [];
        $srcImg = array_unique($srcImg);
        $dirPath = self::FILE_PATH;
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755);
        }

        $error = '';
        foreach ($srcImg as $img) {
            //图片是 mmbiz.qpic.cn网的 不进下载处理
            if (strpos($img, 'mmbiz.qpic.cn') !== false) {
                continue;
            }

            $imgInfo = pathinfo($img);
            $fileSuffix = isset($imgInfo['extension']) ? $imgInfo['extension'] : 'png';
            $filePath = $dirPath . md5($img) . '.' . $fileSuffix;
            //文件不存在 或者是个空的文件，进行解析下载
            if (!file_exists($filePath) || empty(file_get_contents($filePath))) {
                $waitImg = $img;
                try {
                    if (strpos($img, '/Application') === 0) {
                        $path = realpath('./' . $img);
                        $imgContent = file_get_contents($path);
                    } else {
                        $imgContent = $this->downloadImg($img);
                    }

                    //保存为0的图片不进行上传下载!
                    if (!file_put_contents($filePath, $imgContent)) {
                        continue;
                    }
                } catch (\ErrorException $e) {
                    $error .= $imgInfo['basename'] . '图片不存在!';
                    continue;
                }

                //对下载到本地的图片进行上传操作
                $url = $this->uploadLocalhostImg($filePath, $instance);
                if ($url){
                    $newFilePath = $dirPath . md5($url) . '.' . $fileSuffix;
                    rename($filePath, $newFilePath);
                    $content = str_replace($img, $url, $content, $count);
                }else{
                    $content = str_replace($img, '', $content, $count);
                }
            }
        }

        return $content;
    }

    /**
     * 处理微信图片本地显示的问题,将其图片地址转换为SRC参数
     * */
    public function localImgShow($content){
        $pregImg = '/http(s)?:\/\/mmbiz.qpic.cn\/mmbiz_[(gif)|(jpg)|(png)|(jpeg)]+[\s\S]*?(wx_fmt=[(gif)|(jpg)|(png)|(jpeg)])?/';
        preg_match_all($pregImg, $content, $result);
        $rubbishHuatengImg = isset($result[0]) ? $result[0] : [];
        $rubbishHuatengImg = array_unique($rubbishHuatengImg);
        $showImgUrl = $this->getShowImgUrl();
        $filePath = self::FILE_PATH;
        foreach ($rubbishHuatengImg as $src) {
            $fileSuffix = substr(strrchr($src, '='), 1);
            if (!is_dir($filePath)) {
                mkdir($filePath, 0755);
            }

            $filePath .= md5($src) . '.' . $fileSuffix;
            if (!file_exists($filePath)) {
                $img = $this->downloadImg($src);
                file_put_contents($filePath, $img);
            }

            $content = str_replace($src, $showImgUrl . $src, $content);
        }

        return $content;
    }

    /**
     * 上传本地图片
     * @param string $path 本地路径
     * @param WeChat $instance 微信SDK
     * */
    public function uploadLocalhostImg($path, $instance){
        $info = pathinfo($path);
        $data['media'] = new \CURLFile(realpath($path), null, $info['basename']);
        $res = $instance->uploadImg($data);
        return isset($res['url']) ? $res['url'] : false;
    }

    /**
     * 获取图片解析显示地址
     * */
    private function getShowImgUrl(){
        return 'http' . '://' . $_SERVER["SERVER_NAME"] . '/MaterialCenter/img?src=';
    }
    /**
     * 清楚为本地显示做的兼容
     * */
    public function cleanShowImgUrl($content){
        $content = html_entity_decode($content);
        $content = str_replace($this->getShowImgUrl(), '', $content, $count);

        return $content;
    }

    private function downloadImg($url){
        $ch = curl_init($url);
        if (strpos($url, 'https') === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $content = curl_exec($ch);
        curl_close($ch);
        return ($content);
    }
}