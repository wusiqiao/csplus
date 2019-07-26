<?php

namespace EShop\Model;

use Think\Model;


class SysUserModel extends Model {
    const LEVEL_TITLES = "一级会员,二级会员,三级会员";
    const FORMER_MEMBER = "其他";
    const LEVEL_VIEWS = "一级,二级,三级";
    protected $_MODEL = 'SysUser';

    /**手机注册，先检查是否有微信注册过
     * @param $openid
     * @param $nickname
     * @param $headimgurl
     * @param $mobile
     * @param $password
     * @return mixed
     */
    public function userRegisterByMobile($openid, $nickname, $headimgurl, $mobile, $password) {
        $branch_id =  getBrowseBranchId();
        $condition["openid"] = $openid;
        $condition['branch_id'] = $branch_id;
        $user_data = $this->where($condition)->find();
        if ($user_data){
            $data["account"] = $mobile;
            $data["mobile"] = $mobile;
            $data["password"] = md5_plus($password);
            $this->where("id=".$user_data["id"])->save($data);
            $user_data = array_merge($user_data, $data);
            setUserSession($user_data);
            return $user_data["id"];
        }else{
            unset($condition);
            $condition["mobile"] = $mobile;
            $condition['branch_id'] = $branch_id;
            $user_data = $this->where($condition)->find();
            if (!$user_data) {
                return $this->userRegister($openid, $nickname, $headimgurl, $mobile, $password);
            } else {
                die(json_encode(array("error" => "1", "msg" => "手机号码已存在")));
            }
        }
    }

    /** 静默注册（微信登录注册）
     * @param $wxUserInfo
     */
    public function userRegisterSilence($openid, $nickname, $headimgurl){
        return $this->userRegister($openid, $nickname, $headimgurl);
    }

    /**
     * 注册函数
     */
    private function userRegister($openid, $nickname, $headimgurl, $account = "", $password = "123456"){
        $branch_id  = getBrowseBranchId();
        if (empty($openid)) {
            die(json_encode(array("error" => "1", "msg" => "注册失败，请允许平台获取您的微信用户信息！")));
        }
        if (!$branch_id){
            die(json_encode(array("error" => "1", "msg" => "注册失败,数据出错！")));
        }
        $user['branch_id']  = $branch_id;
        $user['openid']     = $openid;
        $user['head_pic']   = empty($headimgurl) ? getDefalutHeadPic(): $headimgurl;
        $user['name']       =  removeEmoji($nickname);
//        $user["unionid"]  =  $wxUserInfo['unionid'];
        $user["reg_time"]   = time();
        $user["last_time"]  = $user["reg_time"];
        $user["last_ip"]    = get_client_ip(0, 1);
        $user["user_type"]  = USER_TYPE_CUSTOMER;
        $user["role_ids"]   = USER_TYPE_CUSTOMER;
        $user['is_valid']   = 1;
        $user["account"]    =  empty($account)?$openid:$account;
        $user["mobile"]    =  $account;
        $user['password']   = md5_plus($password);
        $result = D('SysUser')->add($user);
        session("user_id",   $result);
        session("user_name", $user['name']);
        session('head_pic',  $user['head_pic']);
        session('user_role', $user['user_type']);
        session('user_type', $user['user_type']);
        session('branch_id', $branch_id);
        return $result;
    }

    public function isLoginFromOpenid($openid,$is_data = false){
        $condition['openid'] = $openid;
        $this->default_where($condition);
        $result = D($this->_MODEL)->where($condition)->find();
        if($is_data){
            return $result;
        }else{
            return $result ? true : false ;
        }

    }
    public function getBranchManager(){
        $branch_id    =  getBrowseBranchId();
        $manager      =  M('SysUser')->where('branch_id = '.$branch_id.' and user_type = '.USER_TYPE_COMPANY_MANAGER)->getField('id');
        return $manager;
    }

    protected function default_where(&$data,$alias = ''){
        return $alias === '' ? $data['branch_id'] = getBrowseBranchId() : $data[$alias.'.branch_id'] = getBrowseBranchId();
    }

    //已经提现的金额
    public function getAlreadyWithdrawalsProfitCash() {
        $condition['user_id'] = session('user_id');
        $condition['withdrawal_type'] = FIN_PROTIF_WITHDRAW;
        $condition['status'] = 1;
        $this->default_where($condition);
        $already_cash = M("ComWithdrawals")->field("sum(money) as total")->where($condition)->find();
        return $already_cash['total'] ? $already_cash['total'] : '0.00';
    }

    //提现明细
    public function getWithdrawalsProfitLists() {
        $condition['user_id'] = session('user_id');
        $condition['withdrawal_type'] = FIN_PROTIF_WITHDRAW;
        $this->default_where($condition);
        $withdrawals_list = M("ComWithdrawals")->field("FROM_UNIXTIME(if(status = 0,create_time,handle_time),'%Y.%m.%d') as view_time , money , fee , (case when status = 0 then '审核中' when status = 1 then '已提现' else '已关闭' end) as view_status,status")->where($condition)->order('view_time desc')->select();
        return $withdrawals_list;
    }
    
}
