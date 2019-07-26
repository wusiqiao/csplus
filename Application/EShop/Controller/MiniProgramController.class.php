<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/26
 * Time: 14:36
 */

namespace EShop\Controller;

use Think\Controller;

class MiniProgramController extends Controller{
    /**
     * 微信小程序获取openid
     */
    public function getOpenidAction(){
        $wx_config=getWxConfigData();
        $appid=$wx_config['xcx_appid'];
        $secret=$wx_config['xcx_appsecret'];
        $js_code=I("post.code");
        $url="https://api.weixin.qq.com/sns/jscode2session?appid=".$appid."&secret=".$secret."&js_code=".$js_code."&grant_type=authorization_code";
        $json_obj=$this->curl_noticeAction($url);
        $rd3_str=$this->randomFromDevAction(16); //获取随机数为key 与openid和session_key为值关联
        $rd3_str = trim($rd3_str);
        S($rd3_str,$json_obj,24*3600*29);
        $json_obj['rd3_session']=$rd3_str;
        echo json_encode($json_obj);
    }

    /**
     * 通过此方法获取随机数，但需要mycrpt支持
     */
    function randomFromDevAction($len)
    {
        $fp = @fopen('/dev/urandom','rb');
        $result = '';
        if ($fp !== FALSE) {
            $result .= @fread($fp, $len);
            @fclose($fp);
        }
        else
        {
            trigger_error('Can not open /dev/urandom.');
        }
// convert from binary to string
        $result = base64_encode($result);
// remove none url chars
        $result = strtr($result, '+/', '-_');
// Remove = from the end
        $result = str_replace('=', ' ', $result);
        return $result;
    }

    /**
     * 检验是否过期
     */
    public function check_3rdsessionAction(){
        $rd3_session_str = I('post.rd3_session');
        $rd3_session=S($rd3_session_str);
        echo json_encode($rd3_session);
    }

    /**
     * 获取access_token
     */
    public function getAccessTokenAction(){
        $wx_config=getWxConfigData();
        $appid=$wx_config['xcx_appid'];
        $secret=$wx_config['xcx_appsecret'];
        /* 在有效期，直接返回access_token */
        if(S($appid.'_access_token')){
            return S($appid.'_access_token') ;
        }
        /* 不在有效期，重新发送请求，获取access_token */
        else{
            $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$appid.'&secret='.$secret;
            $result = $this->curl_noticeAction($url);
            if($result){
                S($appid.'_access_token',$result['access_token'],7100);
                return S($appid.'_access_token') ;
            }else{
                return 'api return error';
            }
        }
    }

    /**
     * 购买成功模板消息
     */
    public function sendTemplateMsgAction(){
        $wx_config=getWxConfigData();
        $template_id=$wx_config['xcx_order_payed'];
        $order_id=I('post.orderId');
        $order_data= D('ComOrder')->getOrderDetailData($order_id);
        $data['touser']=I('post.openid');
        $data['form_id']=I('post.form_id');
        $data['template_id']=$template_id;
        $data['page']='/pages/paySuccess/paySuccess?id='.$order_id;
        $data['data']=[
            "keyword1"=>array(
                "value"=>$order_data["product_title"],
                "color"=>"#173177"
            ),
            "keyword2"=>array(
                "value"=>I('post.orderSn'),
                "color"=>"#173177"
            ),
            "keyword3"=>array(
                "value"=>getMessageRemark(),
                "color"=>"#173177"
            ),
        ];
        $this->ajaxReturn($this->send_xcx_message($data));
    }

    /**
     * 发送小程序消息模板
     */
    public function send_xcx_message($data){
        $access_token = $this->getAccessTokenAction();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token='.$access_token;
        $result = $this->https_curl_jsonAction($url,$data,'json');
        return $result;
    }

    public function curl_noticeAction($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        $res = json_decode($output, true);
        return $res;
    }

    /* 发送json格式的数据，到api接口 -xzz0704  */
    public function https_curl_jsonAction($url,$data,$type){
        if($type=='json'){//json $_POST=json_decode(file_get_contents('php://input'), TRUE);
            $headers = array("Content-type: application/json;charset=UTF-8","Accept: application/json","Cache-Control: no-cache", "Pragma: no-cache");
            $data=json_encode($data);
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS,$data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers );
        $output = curl_exec($curl);
        if (curl_errno($curl)) {
            echo 'Errno'.curl_error($curl);//捕抓异常
        }
        curl_close($curl);
        return $output;
    }

    /**
     * 小程序分享获取数据
     */
    public function setWXShareDataAction(){
        $shopData = getComStoreData("all");
        $title=$shopData['name'];
        $this->ajaxReturn($title);
    }

    /**返回分享数据
     * @param null $branch_id
     * @param string $from_url 来源url
     */
    public function getShareDataAction($branch_id = null, $from_url = ''){
        $share_data = array();
        if ($from_url){
            //产品详情
            if (stripos($from_url, "productDetail") !== false){
                if (preg_match("/product_id[=\/](\d+)/i", $from_url, $match)){
                    $id = $match[1];
                    $product_data = M("ComProduct")->field("product_title,product_desc,product_pic")->where("id=".$id)->find();
                    $share_data['title']  = $product_data['product_title'];
                    $share_data['desc']	  = $product_data['product_desc'];
                    $share_data['imgUrl'] = $product_data["product_pic"];
                }
            }
            //如果没有设置，默认取店铺的
            if (empty($share_data)){
                $shopData =M('ComStore a')
                    ->field("a.name,a.share_desc,b.default_header_pic")
                    ->join("sys_routine b on a.branch_id=b.branch_id")
                    ->where('a.branch_id = '.$branch_id)->find();
                $share_data['title']  = $shopData['name'];
                $share_data['desc']	  = $shopData['share_desc'];
                $share_data['imgUrl'] = $shopData["default_header_pic"];
            }
        }
        $share_data["openid"] = session("openid"); //回传给小程序
        $this->ajaxReturn(array("code"=>0, "data"=>$share_data));
    }
}