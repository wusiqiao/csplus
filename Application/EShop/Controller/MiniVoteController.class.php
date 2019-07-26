<?php
namespace EShop\Controller;

use Think\Controller;
use ESAdmin\Model\MiniConfigModel;
use ESAdmin\Model\MiniUserModel;
use ESAdmin\Model\VoteActivityModel;
use ESAdmin\Model\VoteUserLogModel;
use ESAdmin\Model\VoteUserRecordModel;
use ESAdmin\Model\VoteParticipantModel;


/**
 * 小程序 投票活动管理
 */
class MiniVoteController extends Controller{
    public function _initialize(){
//        header('Content-type:text/json');
    }

    const CURL_METHOD_GET  = 'GET';
    const CURL_METHOD_POST = 'POST';
    /**
     * 文件上传存放位置
     * */
    const UPLOADS_DIR = DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'asks' . DIRECTORY_SEPARATOR;
    /**
     * 授权类型
     * */
    const GRANT_TYPE = 'authorization_code';
    /**
     * @var 小程序API基础地址
     * */
    const API_URL = 'https://api.weixin.qq.com/sns/';
    /**
     * @var 登录凭证校验
     *
     * */
    const JSCODE2SESSION_URL = 'jscode2session?';
    /**
     * @param string $code 小程序获取的授权票据
     * @param sting  $iv 小程序用户用户信息里面的 iv
     * @param string $encryptedData 小程序用户用户信息里面的 encryptedData
     * @param string $signType TOKEN 加密方式 safe 跟 base64 其他没做
     * */
    public function loginAction($code = null, $iv = null, $encryptedData = null, $signType = 'safe'){
        if(empty($code) || empty($iv) || empty($encryptedData)){
            return $this->responseError('code，iv，encryptedData是必要参数!');
        }

        $this->setApplatsConfig();
        $data = $this->code2Session($code);
        if(!($info = $this->decryptData($encryptedData, $data['session_key'], $iv))){
            return $this->responseError(' 获取用户信息失败!');
        }

        $branchId = 0;
        $token = MiniUserModel::switchThat($info, $this->_appid, $branchId, $signType);

        if(! $token){
            return $this->responseError('登录失败!');
        }

        return $this->responseSuccess('登录成功!', ['token' => $token , 'signType' => $signType]);
    }

    /**
     * 刷新用户的 昵称 跟 头像
     * @param $nickName
     * @param $avatarUrl
     * @param $token
     * @param string $signType
     */
    public function refreshAction($nickName, $avatarUrl, $token, $signType = 'safe'){
        $user = $this->getMiniUser($token, $signType);
        $userModel = new MiniUserModel();
        if(!$userModel->where(['id' => $user['id']])->save(['nickname' => $nickName, 'avatar_url' => $avatarUrl])){
            return $this->responseError('更新失败!');
        }

        return $this->responseSuccess('更新成功!');
    }
    /**
     * 获取活动详情
     * */
    public function activityDetailAction($id, $token = null, $signType = 'safe'){
        $vote = $this->getActivityDetail($id);
        if(!empty($token)){
            $user = $this->getMiniUser($token, $signType, false);
            if($user) VoteUserRecordModel::switchActivity($user['id'], $id);
        }

        return $this->responseSuccess('获取成功!', $vote);
    }
    /**
     * 获取活动列表
     * */
    public function activityListAction(){
        $list = VoteActivityModel::getProcessingListByStatus();

        return $this->responseSuccess('获取成功!', $list);
    }
    /**
     * 活动浏览记录
     * */
    public function recordActivityAction($id, $token, $signType = 'safe'){
        $user = $this->getMiniUser($token, $signType);
        $vote = $this->getActivityDetail($id);
        $res = VoteUserRecordModel::switchActivity($user['id'], $id);
        if($res === 0){
            return $this->responseError('记录失败!');
        }

        if($res === 2){
            return $this->responseError('已记录!');
        }

        return $this->responseSuccess('记录成功!');
    }
    /**
     * 参赛人信息
     * */
    public function partDetailAction($id, $token = '', $signType = 'safe'){
        $participant = VoteParticipantModel::findById($id);
        if(empty($participant)){
            return $this->responseError('不存在!');
        }

        $participant['ranking'] = VoteParticipantModel::getRanking($participant['activity_id'], $participant['vote_taotal']);
        if($token && ($user = $this->getMiniUser($token, $signType, false))){
           if( VoteUserRecordModel::switchParticipant($user['id'], $id) === 1){
               $participant['sacn_total'] += 1;
           }
        }

        return $this->responseSuccess('获取成功!', $participant);
    }
    /**
     * 投票记录列表
     * */
    public function partVoteAction($participant_id, $activity_id){
        $list = (new VoteUserLogModel)
            ->alias('a')
            ->join('mini_user b ON a.mini_user_id = b.id')
            ->where([
            'a.activity_id' => $activity_id,
            'a.participant_id' => $participant_id])
            ->field('a.vote_time, b.nickname, b.avatar_url')
            ->order(['a.vote_time' => 'DESC'])
            ->page(I('page', 1),20)
            ->select();

        foreach($list as &$value){
            $value['nickname'] = base64_decode($value['nickname']);
            $value['voteTime'] = date('Y-m-d H:i:s', $value['vote_time']);
        }

        return $this->responseSuccess('OK', $list);
    }
    /**
     * 获取活动参与人列表
     * */
    public function  partListAction($id){
        $list = VoteParticipantModel::getList($id);
        return $this->responseSuccess('获取成功!', $list);
    }
    /**
     * 获取活动排名列表
     * */
    public function  partRankingAction($id){
        $list = VoteParticipantModel::getRankingList($id);
        return $this->responseSuccess('获取成功!', $list);
    }
    /**
     * 活动报名
     * */
    public function signUpAction($token, $signType = 'safe'){
        if(IS_POST){
            $data = I('post.');
            if(empty($data)){
                $data = file_get_contents('php://input');
                $data = is_array($data) ? $data : (array)json_decode($data);
            }

            if(empty($data['activity_id'])){
                return $this->responseError('获取报名活动失败!');
            }

            if(empty($data['name'])){
                return $this->responseError('请输入名称!');
            }
            if(empty($data['cover_pic'])){
                return $this->responseError('请上传封面!');
            }
            if(empty($data['contact_name'])){
                return $this->responseError('请输入联系人!');
            }
            if(empty($data['contact_tel'])){
                return $this->responseError('请输入联系电话!');
            }

            $user = $this->getMiniUser($token, $signType);
            $participantModel = new  VoteParticipantModel();
            if($participantModel->where(['mini_user_id' => $user['id'], 'activity_id' => $data['activity_id']])->find()){
                return $this->responseError('您已报名改活动!');
            }

            $vote = VoteActivityModel::addParticipantTotal($data['activity_id']);
            if(!is_array($vote)){
                return $this->responseError($vote);
            }

            $data['branch_id'] = $vote['branch_id'];
            $data['status'] = $vote['review'];
            $data['mini_user_id'] = $user['id'];
            $data['create_date'] = date('Y-m-d H:i:s');
            $data['update_time'] = time();
            if(!M('VoteParticipant')->add($data)){
                (new  VoteActivityModel)->where(['id' => $data['activity_id']])->setDec('participant_total');
                return $this->responseError('报名失败!');
            }

            return $this->responseSuccess('报名成功！');
        }

        return $this->responseError('请求方式错误!');
    }

    /**
     * 活动投票
     * @param $id
     * @param $token
     * @param string $signType
     */
    public function voteAction($id, $token, $signType = 'safe'){
        $participant = $this->getVoteParticipant($id);
        $activity = $this->getActivityDetail($participant['activity_id']);
        $user = $this->getMiniUser($token, $signType);
        $bool = VoteUserLogModel::participant($user['id'], $id, $activity);
        if($bool === 0){
            return $this->responseError('投票失败!');
        }

        if($bool !== 1){
            return $this->responseError($bool);
        }

        return $this->responseSuccess('投票成功!');
    }
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
                array_push($paths, $this->imgSrcTo(self::UPLOADS_DIR . $file['savepath'] . $file['savename']));
            }

            return $this->responseSuccess('上传成功', $paths);
        }
    }

    private function imgSrcTo($src){
        return str_replace('\\', '/', $src);
    }
    /**
     * 设置图片上传配置
     * */
    private function setUploadsImg(){
        //设置上传最大为 2M 2 * 1024 * 1024
        return [
            'maxSize' => 2 * 1024 * 1024,
            'exts' => ['jpg', 'png', 'jpeg'],
            'rootPath' => dirname(THINK_PATH) . self::UPLOADS_DIR,
        ];
    }

    private function getActivityDetail($id){
        $activity = VoteActivityModel::findById($id);
        if(empty($activity)){
            return $this->responseError('活动不存在!!');
        }

        return $activity;
    }
    private function getVoteParticipant($id){
        $participant = VoteParticipantModel::findById($id);
        if(empty($participant)){
            return $this->responseError('选手不知道跑哪去了!');
        }

        return $participant;
    }

    private function getMiniUser($token, $signType = 'safe', $bool = true){
        $token = MiniUserModel::decodeToken($signType, $token);
        if(!($user = MiniUserModel::findByToken($token)) && $bool){
            return $this->responseError('令牌失效,获取身份信息失败!', 600);
        }

        return $user;
    }

    private function setApplatsConfig(){
        $config = MiniConfigModel::getMiniConfig();
        if(empty($config)){
            return $this->responseError('后台配置未完善！');
        }

        $this->_appid  = $config['appid'];
        $this->_secret = $config['secret'];
    }

    private function code2Session($jsCode){
        $data['appid']  = $this->_appid;
        $data['secret'] = $this->_secret;
        $data['js_code'] = $jsCode;
        $data['grant_type'] = self::GRANT_TYPE;

        $resule = $this->Curl(self::JSCODE2SESSION_URL, $data);
        if(empty($resule['openid'])){
            return $this->responseError('小程序配置错误!');
        }

        return $resule;
    }

    private static function Curl($url, $data = null, $method = self::CURL_METHOD_GET){
        $url = self::API_URL . $url;
        if($method == 'GET' && is_array($data)){
            $url .= http_build_query($data);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); //严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        if ($method != 'GET') {
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        //设置超时
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $output = curl_exec($ch);
        curl_close($ch);
        if ($output !== false) {
            return json_decode($output, true);
        }

        return true;
    }
    /**
     * 检验数据的真实性，并且获取解密后的明文.
     * @param $encryptedData string 加密的用户数据
     * @param $iv string 与用户数据一同返回的初始向量
     */
    private function decryptData($encryptedData, $sessionKey, $iv){
        if (strlen($sessionKey) != 24) {
            return false;
        }
        $aesKey=base64_decode($sessionKey);
        if (strlen($iv) != 24) {
            return false;
        }

        $aesIV=base64_decode($iv);
        $aesCipher=base64_decode($encryptedData);
        $result= openssl_decrypt($aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);
        $dataObj=json_decode( $result );
        if( $dataObj  == NULL ) {
           return false;
        }
        if( $dataObj->watermark->appid != $this->_appid) {
            return false;
        }

        return json_decode($result, true);
    }

    private function responseError($error, $code = 1){
        exit(json_encode(['code' => $code, 'message' => $error], true));
    }

    private function responseSuccess($message = null, $data = null){
        exit(json_encode(['code' => 0, 'message' => $message , 'data' => $data], true));
    }

    private $_appid = '';
    private $_secret = '';
}