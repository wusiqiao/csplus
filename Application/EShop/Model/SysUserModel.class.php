<?php

namespace EShop\Model;

use Think\Model;


class SysUserModel extends DataModel {
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
        $user["user_type"]  = USER_TYPE_PROSPECTIVE;
        $user["role_ids"]   = ROLE_ID_CUSTOMER;
        $user['is_valid']   = 1;
        $user["account"]    =  empty($account)?$openid:$account;
        $user["mobile"]    =  $account;
        $user['password']   = md5_plus($password);
        //添加关注和为关注
        $user['is_follow'] = session('is_follow');
        if (session('is_follow') == 1) {
            $user['followed_at'] = session('followed_at');
        }else{
            $user['is_follow'] = 0;  
        }
        $result = D('SysUser')->add($user);
        M("SysUserRole")->add(["user_id"=>$result,"role_id"=>ROLE_ID_CUSTOMER]);
        unset($_SESSION['is_follow']);
        unset($_SESSION['followed_at']);
        session("user_id",   $result);
        session('mobile', $account);
        session("user_name", $user['name']);
        session('head_pic',  $user['head_pic']);
        session('user_role', $user['user_type']);
        session('user_type', $user['user_type']);
        session('branch_id', $branch_id);
        session("inviter", $_GET['inviter']); //邀请人
        return $result;
    }
    public function isLoginFromOpenid($openid,$is_data = false){
        $condition['openid'] = $openid;
        $condition['branch_id'] = getBrowseBranchId();
        $result = $this->where($condition)->order('last_time desc')->find();
        if($result){
            $data['exiting'] = 0;
            $data['last_time'] = time();
            M("SysUser")->where("id=".$result['id'])->save($data);
        }
        if($is_data){
            return $result;
        }else{
            return $result ? true : false ;
        }
    }
    public function automationLoginFromOpenid($openid,$is_data = false){
        $condition['openid'] = $openid;
        $condition['branch_id'] = getBrowseBranchId();
        $condition['exiting'] = 0;
        $result = $this->where($condition)->order('last_time desc')->find();
        if($is_data){
            return $result;
        }else{
            return $result ? true : false ;
        }
    }
    public function getBranchManager($inc = 'id'){
        $branch_id    =  getBrowseBranchId();
        $manager      =  M('SysUser')->where('branch_id = '.$branch_id.' and user_type = '.USER_TYPE_COMPANY_MANAGER)->getField($inc,true);
        return $manager;
    }

    /**我的收益
     * 可提现（收入已确认），已提现（提现已确认），未解冻（收入确认中）
     * @return array|mixed
     */
    public function getMyProfit(){
        $branch_id = getBrowseBranchId();
        $user_id = session('user_id');
        $result = array();
        if ($branch_id && $user_id){
            $result = $this->getMyTotalCommisionIncome();
            $withdrawal_data = $this->getMyTotalWithdrawal();
            $result = array_merge($result, $withdrawal_data);
            /*//可提现金额=总确认佣金-已提现确认金额
            $result["commision_can_withdrawal"] = number_format($result["commision_confirm"] - $result["withdrawal_confirm"] - $result["withdrawal_frozen"],2);
            if($result["commision_can_withdrawal"]<0){
                $result["commision_can_withdrawal"]=0;
            }*/
            $result['total_income'] =  $result["commision_confirm"] + $result["commision_frozen"]+$result["commision_invalid"];
            $result["incomes"] = $this->getMyCommisionIncomeDetail();;
            $result["withdrawals"] = $this->getMyWithdrawalDetail();
        }
        return $result;
    }

    /**
     * 我的佣金收入统计(统计所有成员加总）
     */
    public function getMyTotalCommisionIncome(){
        $result["commision_frozen"] = "0.00";
        $result["commision_confirm"] = "0.00";
        $result["commision_invalid"] = "0.00";
        $branch_id = getBrowseBranchId();
        $inviter_id = session('user_id');
        $sql = "select income.status,sum(income.commision) as commision 
                from distribution_income income
                inner join sys_user user on user.id=income.user_id
                inner join distribution_relation relation on relation.openid=user.openid
                where relation.inviter_id=$inviter_id and income.branch_id=$branch_id group by income.status";
        $income_list = $this->query($sql);
        if ($income_list) {
            foreach ($income_list as $value) {
                switch ($value["status"]) {
                    case 0:
                        $result["commision_frozen"] = $value["commision"];//收入确认中，冻结中
                        break;
                    case 1:
                        $result["commision_confirm"] = $value["commision"]; //收入已确认总额
                        break;
                    case 2:
                        $result["commision_invalid"] = $value["commision"]; //无效
                        break;
                }
            }
        }
        return $result;
    }

    /**团队成员分组统计(包括冻结和解冻）,包括关注，关注注册
     * @return mixed
     */
    public function getTeamMember(){
        $branch_id = getBrowseBranchId();
        $inviter_id = session('user_id');
        $sql = "select user.id as member_id,
                IF(ISNULL(user.id),relation.nickname,user.name) as member_name,
                IF(ISNULL(income.user_id),0.00,sum(income.commision)) as commision,
                CASE relation.subscribe_time WHEN 0 THEN '未关注' ELSE FROM_UNIXTIME(relation.subscribe_time,'%Y-%m-%d %H:%i') END as subscribe_time,
                IF(ISNULL(income.user_id),'未注册',FROM_UNIXTIME(user.reg_time,'%Y-%m-%d %H:%i')) as reg_time,
                CASE relation.subscribe WHEN 0 THEN '未关注' ELSE '已关注' END as subscribe_state
                from distribution_relation relation 
                left join sys_user user on user.openid=relation.openid
                left join distribution_income income on user.id=income.user_id and income.status < 2                              
                where relation.inviter_id=$inviter_id and relation.branch_id=$branch_id
                group by user.id,user.name";
        $result = $this->query($sql);
        return $result;
    }

    /**我的佣金收入明细
     * @return mixed
     */
    public function getMyCommisionIncomeDetail(){
        $branch_id = getBrowseBranchId();
        $inviter_id = session('user_id');
        $sql = "select income.*,user.name as member_name,user.mobile,user.head_pic,
                    (case when income.status = 0 then '未解冻' when income.status = 1 then '已解冻' else '无效' end) as status_view 
                    from distribution_income income 
                    inner join sys_user user on user.id=income.user_id
                    inner join distribution_relation relation on relation.openid=user.openid
                    where relation.inviter_id=$inviter_id and income.branch_id=$branch_id";
        $income_list = $this->query($sql);
        return $income_list;
    }

    /**成员佣金收入明细
     * @return mixed
     */
    public function getTeamMemberCommisionIncomeDetail($member_id){
        $branch_id = getBrowseBranchId();
        $sql = "select income.*,user.name as member_name,user.mobile,user.head_pic,
                    FROM_UNIXTIME(income.update_time,'%Y-%m-%d %H:%i') as income_time
                    from distribution_income income 
                    inner join sys_user user on user.id=income.user_id
                    where income.user_id=$member_id and income.branch_id=$branch_id";
        $income_list = $this->query($sql);
        return $income_list;
    }


    /**我的提现统计
     * @return mixed
     */
    public function getMyTotalWithdrawal(){
        $branch_id = getBrowseBranchId();
        $user_id = session('user_id');
        $result["withdrawal_frozen"] = "0.00";
        $result["withdrawal_confirm"] = "0.00";
        $result["withdrawal_invalid"] = "0.00";
        $sql = "select status,sum(money) as withdrawal_amount from distribution_withdrawal where user_id=$user_id and branch_id=$branch_id group by status";
        $withdrawals_list = $this->query($sql);
        if($withdrawals_list){
            foreach ($withdrawals_list as $value){
                switch ($value["status"]){
                    case 0:
                        $result["withdrawal_frozen"] = $value["withdrawal_amount"];//审核中
                        break;
                    case 1:
                        $result["withdrawal_confirm"] = $value["withdrawal_amount"]; //已提现
                        break;
                    case 2:
                        $result["withdrawal_invalid"] = $value["withdrawal_amount"]; //无效
                        break;
                }
            }
        }
        return $result;
    }


    //提现明细
    public function getMyWithdrawalDetail() {
        $condition['user_id'] = session('user_id');
        $condition['branch_id'] = getBrowseBranchId();
        $withdrawals_list = M("DistributionWithdrawal")->field("if(status = 0,create_time,review_time) as time_view, money , (case when status = 0 then '审核中' when status = 1 then '已提现' else '已关闭' end) as status_view,status")->where($condition)->order('time_view desc')->select();
        return $withdrawals_list;
    }

    /**可提现金额(解冻金额-提现金额-未审核提现金额）
     * @return mixed
     */
    public function getCommisionCanWithdraw(){
        $result = $this->getMyTotalCommisionIncome();
        $withdrawal_data = $this->getMyTotalWithdrawal();
        $result = array_merge($result, $withdrawal_data);
        //可提现金额=总确认佣金-已提现确认金额
        return $result["commision_confirm"] - $result["withdrawal_confirm"] - $result["withdrawal_frozen"];
    }
//取出用户归属的上级(扫谁的进来,向上取两级)
    public function getUserBelongData($user_id)
    {
        $where['user_id'] = $user_id; //下级id,即当前用户id
        $belong_level_one = M("SysSalesmanSubordinate")->where($where)->find(); //取出归属user_id 即一级会员
        if ($belong_level_one) {
            //储存一级会员
            $belong['one']['salesman_id'] = $belong_level_one['salesman_id'];
            $belong['one']['level'] = 1;
            //判断一级会员的salesman_id类型,如果不是用户的话,就求出他的parent_id
//            $parent_id = $this->getUsersRole($belong_level_one['salesman_id'], 'parent_id');
//            $user_role = $this->getUsersRole($belong_level_one['salesman_id'], 'user_type');
//            if ($parent_id > 0 && $user_role == 2) {
//                $belong['one']['parent_id'] = $parent_id;
//            } elseif ($parent_id == 0 && $user_role == 1) {
//                $belong['one']['parent_id'] = $belong_level_one['salesman_id'];
//            }
            //如果有一级会员的话,向上查找是否有二级会员
            $condition['user_id'] = $belong_level_one['salesman_id'];
            $belong_level_two = M("SysSalesmanSubordinate")->where($condition)->find();
            //判断是否有二级会员
            if ($belong_level_two) {
                $belong['two']['salesman_id'] = $belong_level_two['salesman_id'];
                $belong['two']['level'] = 2;
            }
            return $belong;
        } else {
            //如果没有一级会员的话,结束
            return false;
        }
    }
    //获取当前服务商下面所有的用户
    public function getBelongsToBranch($page){
        $condition['sysuser.branch_id'] = getBrowseBranchId();
        $condition['sysuser.user_type'] = array('neq',USER_TYPE_COMPANY_MANAGER);
        $page_size  = $page['rows'];
        $paging     = $page['page'];
        $users = $this ->alias('sysuser')
                       ->where($condition)
                       ->field('sysuser.*,(select sum(payment_money)  from com_order where user_id = sysuser.id and ( order_state = '.ORDER_STATE_WAITING_JUDGE.' or order_state = '.ORDER_STATE_HAS_JUDGE.') ) as turnover ')
                       ->page($paging, $page_size)
                       ->order('sysuser.reg_time desc')
                       ->select();
        foreach($users as $key=>$value){
            $users[$key]['mobile'] = $value['mobile'] ?? '';
            $users[$key]['name'] = trim($value['name']) != '' && $value['name'] != null  ? trim($value['name']) : '无名';
            $users[$key]['date'] = date('Y年m月d日',$value['reg_time']);
            $users[$key]['turnover'] = $value['turnover'] > 0 ? $value['turnover'].'元' : '暂无金额';
        }
        return ['total'=>count($users),'rows'=>$users];
    }
    /**取消关注，如果在关系表中有存在，把取消关注时间设置为当前
     * @param $openid 取消关注用户openid
     */
    public function unSubscribe($openid){
        S($openid, 0);
        $data["unfollowed_at"] = time();
        $data["is_follow"] = 0;
        $this->where(array("openid"=>$openid))->save($data);
    }
    /**关注
     * @param $openid
     */
    public function subscribe($openid){
        S($openid, 1);
        $data["followed_at"] = time();
        $data["is_follow"] = 1;
        $this->where(array("openid"=>$openid))->save($data);
    }
    //获取员工或客户所在的公司（非部门）,如果是员工，只会返回对应的商户，客户的话如果有多公司，会返回多个公司
    public function getUserBranchs($user_id, $branch_id){
        $user_data = $this->field("id,user_type")->where("id=$user_id")->find();
        if ($user_data["user_type"] == USER_TYPE_COMPANY_MANAGER){ //公司员工，类型必须是商户
            $sql = "select a.id,a.name,a.code,a.parent_id,p.code as parent_code 
                  from sys_branch a 
                  left join sys_branch p on p.id=a.parent_id 
                  where a.type=". ORG_BRANCH ." and a.id=$branch_id";
        }else{ //客户的话，归属公司类型必须是公司
            $sql = "select a.id,a.name,a.code,a.parent_id,p.code as parent_code                       
                    from sys_user_branch c 
                    inner join sys_branch a  on a.id=c.branch_id 
                    inner join sys_branch p on p.id=a.parent_id
                    where a.type=". ORG_COMPANY ." and c.user_id=$user_id";
        }
        $branch_data_list = $this->query($sql);
        return $branch_data_list;
    }
    public function setUserRoleAccess($authId) {
        $result = array();
        $sql = "select m.id,m.parent_id,m.url,m.is_dac,o.action  ".
            " from sys_user_role sur".
            " left join sys_role sr on sur.role_id=sr.id" .
            " left join sys_role_operation ro on sr.id=ro.role_id " .
            " left join sys_menu m on m.id=ro.menu_id  " .
            " left join sys_operation o on ro.operation_id=o.id " .
            " where sur.user_id=$authId and m.is_valid=1" .
            " GROUP BY m.id,m.parent_id,m.url,m.is_dac,o.action order by m.sort desc";
        $accessList = $this->query($sql);
        foreach ($accessList as $value) {
            $result[ACCESS_MENUS_KEY][$value[ACCESS_URL_KEY]]["allow"] = 1;
            $result[ACCESS_MENUS_KEY][$value[ACCESS_URL_KEY]]["id"] = $value["id"];
            $result[ACCESS_MENUS_KEY][$value[ACCESS_URL_KEY]]["parent_id"] = $value["parent_id"];
            $result[ACCESS_MENUS_KEY][$value[ACCESS_URL_KEY]]["is_dac"] = $value["is_dac"];
            $result[ACCESS_MENUS_KEY][$value[ACCESS_URL_KEY]][ACCESS_MENU_ACTIONS_KEY][$value["action"]] = 1;
        }
        //获取每个功能的可用action
        $sql = "select menu.url,opt.action from sys_menu_operation mo 
                inner join sys_menu menu on mo.menu_id=menu.id
                inner join sys_operation opt on opt.id=mo.operation_id where menu.is_valid=1";
        $list = $this->query($sql);
        foreach ($list as $key=>$mo){
            $menuOptions[$mo["url"]][$mo["action"]] = 1;
        }
        $menuList = M("SysMenu")->where("is_valid=1")->field("id,parent_id,url,is_dac")->select();
        foreach ($menuList as $value) {
            if (!$result[ACCESS_MENUS_KEY][$value[ACCESS_URL_KEY]]) {
                $result[ACCESS_MENUS_KEY][$value[ACCESS_URL_KEY]]["allow"] = 0;
                $result[ACCESS_MENUS_KEY][$value[ACCESS_URL_KEY]]["id"] = $value["id"];
                $result[ACCESS_MENUS_KEY][$value[ACCESS_URL_KEY]]["parent_id"] = $value["parent_id"];
                $result[ACCESS_MENUS_KEY][$value[ACCESS_URL_KEY]]["is_dac"] = $value["is_dac"];
                $result[ACCESS_MENUS_KEY][$value[ACCESS_URL_KEY]][ACCESS_MENU_ACTIONS_KEY] = $menuOptions[$value[ACCESS_URL_KEY]];
            }
        }
        $actionList = M("SysOperation")->field("action")->select();
        foreach ($actionList as $value) {
            $result[ACCESS_SYS_ACTIONS_KEY].= $value["action"] . "|";
        }
        return $result;
    }

    public function getTagIds($id)
    {
        $sysUserRelationTag = D('SysUserRelationTag')
        ->where(['user_id'=>$id,'branch_id'=>getBrowseBranchId()])
        ->field('id','tag')
        ->select();
        $tag_ids = [];
        foreach ($sysUserRelationTag as $k => $v) {
             $tag_ids[] = $v['tag'];
        }
        $tag_ids = implode(",", $tag_ids);
        return $tag_ids;
    }

}
