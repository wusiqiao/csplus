<?php
namespace EShop\Model;
/**
 * @author kcg
 * 微信 ESA 加解密
 */
class  WechatNoticeModel{
    const ERROR_AES_KEY = 'EncodingAESKey错误!';
    const ERROR_PARSE_XML = 'XML解析失败!';
    const ERROR_SUGNATURE = '签名错误';

    public $isCheckSignature = true;
    /**
     * 构造函数
     * @param $token string 公众平台上，开发者设置的token
     * @param $encodingAesKey string 公众平台上，开发者设置的EncodingAESKey
     * @param $appId string 公众平台的appId
     */
    public function __construct($token, $encodingAesKey, $appId){
        $this->token = $token;
        $this->encodingAesKey = $encodingAesKey;
        $this->appId = $appId;
        $this->key = base64_decode($encodingAesKey . "=");
    }

    /**
     * @param $replyMsg string 公众平台待回复用户的消息，xml格式的字符串
	 * @param $timeStamp string 时间戳，可以自己生成，也可以用URL参数的timestamp
	 * @param $nonce string 随机串，可以自己生成，也可以用URL参数的nonce
     * */
    public function encryptMsg($replyMsg, $timeStamp = null, $nonce = null){
        if(! $encrypt =  $this->encrypt($replyMsg, $this->appId)){
            return false;
        }

        $timeStamp = $timeStamp ? $timeStamp : time();
        $nonce = $nonce ? $nonce : $this->getRandomStr();
        if(! $signature = $this->getSHA1($this->token, $timeStamp, $nonce, $encrypt)){
            return false;
        }

        return $this->generate($encrypt, $signature, $timeStamp, $nonce);
    }
    /**
     * 消息验证并且解密
     * */
    public function decryptMsg($msgSignature, $timestamp = null, $nonce, $postData){
        if (strlen($this->encodingAesKey) != 43) {
            return $this->setError(self::ERROR_AES_KEY);
        }

        if (!$array = $this->extract($postData)) {
            return false;
        }

        $encrypt = $array[0];
        $timestamp = $timestamp ? $timestamp : time();
        //验证签名
        if(!$this->checkSignature($msgSignature, $timestamp, $nonce, $encrypt)){
            return false;
        }

        if (!$xml = $this->decrypt($encrypt, $this->appId)) {
            return false;
        }

        return (array)simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
    }

    public function getError(){
        return $this->_errors;
    }

    private function generate($encrypt, $signature, $timestamp, $nonce){
        $format = "<xml><Encrypt><![CDATA[%s]]></Encrypt><MsgSignature><![CDATA[%s]]></MsgSignature><TimeStamp>%s</TimeStamp><Nonce><![CDATA[%s]]></Nonce></xml>";
        return sprintf($format, $encrypt, $signature, $timestamp, $nonce);
    }

    private function checkSignature($msgSignature, $timestamp, $nonce, $encrypt){
        if($msgSignature != $this->getSHA1($this->token, $timestamp, $nonce, $encrypt)){
            return $this->setError('签名错误!');
        }

        return true;
    }

    /**
     * 用SHA1算法生成安全签名
     * @param string $token 票据
     * @param string $timestamp 时间戳
     * @param string $nonce 随机字符串
     * @param string $encrypt 密文消息
     */
    private function getSHA1($token, $timestamp, $nonce, $encrypt_msg){
        //排序
        try {
            $array = [$encrypt_msg, $token, $timestamp, $nonce];
            sort($array, SORT_STRING);
            $str = implode($array);
            return sha1($str);
        } catch (Exception $e) {
            return $this->setError('sha1签名获取失败!' . $e->getMessage());
        }
    }
    /**
     * 提取出xml数据包中的加密消息
     * @param string $xmltext 待提取的xml字符串
     * @return string 提取出的加密消息字符串
     */
    private function extract($xmltext){
        try {
            $xml = new \DOMDocument();
            $xml->loadXML($xmltext);
            $array_e = $xml->getElementsByTagName('Encrypt');
            $array_a = $xml->getElementsByTagName('ToUserName');
            $encrypt = $array_e->item(0)->nodeValue;
            $tousername = $array_a->item(0)->nodeValue;
            return [$encrypt, $tousername];
        } catch (Exception $e) {
            return $this->setError('XML解析失败!' . $e->getMessage());
        }
    }

    /**
     * 对明文进行加密
     * @param string $text 需要加密的明文
     * @return string 加密后的密文
     */
    private function encrypt($text, $appid){
        try {
            //获得16位随机字符串，填充到明文之前
            $random = $this->getRandomStr();
            $text = $random . pack("N", strlen($text)) . $text . $appid;
            // 网络字节序
            $size = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
            $module = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
            $iv = substr($this->key, 0, 16);
            //使用自定义的填充方式对明文进行补位填充
            $text = $this->encode($text);
            mcrypt_generic_init($module, $this->key, $iv);
            //加密
            $encrypted = mcrypt_generic($module, $text);
            mcrypt_generic_deinit($module);
            mcrypt_module_close($module);
            //print(base64_encode($encrypted));
            //使用BASE64对加密后的字符串进行编码
            return base64_encode($encrypted);
        } catch (Exception $e) {
            //print $e;
            return $this->setError($e->getMessage());
        }
    }

    /**
     * 对密文进行解密
     * @param string $encrypted 需要解密的密文
     * @return string 解密得到的明文
     */
    private function decrypt($encrypted, $appid){
        try {
            //使用BASE64对需要解密的字符串进行解码
            $ciphertext_dec = base64_decode($encrypted);
            $module = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
            $iv = substr($this->key, 0, 16);
            mcrypt_generic_init($module, $this->key, $iv);
            //解密
            $decrypted = mdecrypt_generic($module, $ciphertext_dec);
            mcrypt_generic_deinit($module);
            mcrypt_module_close($module);
        } catch (Exception $e) {
            return $this->setError($e->getMessage());
        }

        try {
            //去除补位字符
            $result = $this->decode($decrypted);
            //去除16位随机字符串,网络字节序和AppId
            if (strlen($result) < 16) return "";
            $content = substr($result, 16, strlen($result));
            $len_list = unpack("N", substr($content, 0, 4));
            $xml_len = $len_list[1];
            $xml_content = substr($content, 4, $xml_len);
            $from_appid = substr($content, $xml_len + 4);
        } catch (Exception $e) {
            return $this->setError($e->getMessage());
        }

        if ($from_appid != $appid) {
            return $this->setError('消息来源APPID 与配置不一致!');
        }

        return $xml_content;
    }

    /**
     * 对解密后的明文进行补位删除
     * @param decrypted 解密后的明文
     * @return 删除填充补位后的明文
     */
    private function decode($text){
        $pad = ord(substr($text, -1));
        if ($pad < 1 || $pad > 32) {
            $pad = 0;
        }

        return substr($text, 0, (strlen($text) - $pad));
    }

    private function encode($text){
        $block_size = self::$block_size;
        $text_length = strlen($text);
        //计算需要填充的位数
        $amount_to_pad = self::$block_size - ($text_length % self::$block_size);
        if ($amount_to_pad == 0) {
            $amount_to_pad = self::$block_size;
        }
        //获得补位所用的字符
        $pad_chr = chr($amount_to_pad);
        $tmp = "";
        for ($index = 0; $index < $amount_to_pad; $index++) {
            $tmp .= $pad_chr;
        }

        return $text . $tmp;
    }

    private function getRandomStr(){
        $str = "";
        $str_pol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($str_pol) - 1;
        for ($i = 0; $i < 16; $i++) {
            $str .= $str_pol[mt_rand(0, $max)];
        }

        return $str;
    }
    private function setError($error){
        $this->_errors = $error;
        return false;
    }

    private $key;
    private $token;
    private $encodingAesKey;
    private $appId;
    private $_errors;
    private static $block_size = 32;
}
