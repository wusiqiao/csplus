<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;


class SysUserModel extends DataModel {
    
     protected $_link = array(
        "Company" => array(
            "join_name" => "LEFT",
            'class_name' => "SysBranch",
            'foreign_key' => 'branch_id',
            'mapping_name' => 'company',
            'mapping_fields' => 'name',
            "mapping_key" => "id"
        )
    );
 
    protected $_auto = array(
        array("birthday", "strtotime", 3, "function"),
        array("password", "md5_plus", 1, "callback")
    );
    protected $_validate = array(
        array('account', '', '登录账号已经存在！', 0, 'unique', 3), // 在新增的时候验证name字段是否唯一
//        array('mobile', '', '手机号码已经存在！', 0, 'unique', 3)
    );

    public function md5_plus($source) {
        return md5_plus($source);
    }

    protected function _after_insert($data, $options) {
        $this->insert_roles($data);
        $this->insert_tags($data);
        $this->insert_customs($data);
        $this->update_company($data);
        //添加未分组客户，-未分组资料
//        $this->insert_default($data);
        parent::_after_insert($data, $options);
    }

    protected function _after_update($data, $options) {

        if (empty(I('post._tip_target')) || I('post._tip_target') != 1) {
            M("SysCustomerInformation")->where(array("user_id" => $data["id"]))->delete();
            $this->insert_customs($data);
        }

        M("SysUserRole")->where(array("user_id" => $data["id"]))->delete();
        $this->insert_roles($data);

        if (I('post.deptment_id') != null){
            $condition = [];
            $condition['user_id'] = $data['id'];
            $condition['type'] = 2;
            M('SysUserBranch')->where($condition)->delete();
            if (I('post.deptment_id') != getBrowseBranchId()) {
                $sys_user_branch_data = [];
                $sys_user_branch_data['user_id'] = $data['id'];
                $sys_user_branch_data['type'] = 2;
                $sys_user_branch_data['branch_id'] = I('post.deptment_id');
                M('SysUserBranch')->add($sys_user_branch_data);
            }
        }

        M("SysUserRelationTag")->where(array("user_id" => $data["id"]))->delete();
        $this->insert_tags($data);

    }

    protected function _after_delete($data, $options) {
        M("SysUserBranch")->where(array("user_id" => $data["id"]))->delete();
        M("SysUserRole")->where(array("user_id" => $data["id"]))->delete();
        M("SysUserRelationTag")->where(array("user_id" => $data["id"]))->delete();
        M("SysCustomerInformation")->where(array("user_id" => $data["id"]))->delete();
    }

    private function insert_roles($data) {
        if($data['user_type'] >= USER_TYPE_CUSTOMER or $data['user_type']==""){
            $role_id = ROLE_ID_CUSTOMER;
            $role_data = array("user_id" => $data["id"], "role_id" => $role_id);
            D("SysUserRole")->add($role_data);
        }else{
            if(is_array($data["role_ids"])){
                $role_id = $data["role_ids"];
            }else{
                $role_id = explode(",",$data["role_ids"]);
            }
            $role_data = [];
            foreach($role_id as $k=>$v){
                $role_data[] = array("user_id" => $data["id"], "role_id" => $v);
            }
            D("SysUserRole")->addAll($role_data);
        }

    }

    private function insert_tags($data) {
        $tag_ids = explode(",", $data["tag_ids"]);
        $tag_datas = array();
        foreach ($tag_ids as $value) {
            $tag_datas[] = array("user_id" => $data["id"], "tag" => $value,"branch_id" =>getBrowseBranchId(),"created_at"=> time());
        }
        D("SysUserRelationTag")->addAll($tag_datas, null, true);
    }

    private function insert_customs($data) {
        $data_title = I('custom-title');
        $data_value = I('custom-value');
        $data_type = I('custom-type');
        $branch_id = $data["branch_id"];
        if (empty($branch_id)){
            $branch_id = getBrowseBranchId();
        }
        foreach ($data_title as $k => $v) {
            $custom_datas[] = array("user_id" => $data["id"],"title" => $v, "value" => $data_value[$k],"branch_id" =>$branch_id,
                "type"=>$data_type[$k],"created_at"=> time(),"updated_at"=> time());
        }
        D("SysCustomerInformation")->addAll($custom_datas, null, true);
    }
    private function update_company($data)
    {
        $role_ids = I('post.companys');
        $role_datas = array();
        if (!empty($role_ids)) {
            foreach ($role_ids as $value) {
                $role_datas[] = array("user_id" => $data["id"], "branch_id" => $value,"type" =>1);
            }
            D("SysUserBranch")->addAll($role_datas, null, true);
        }
    }

    /**根据用户返回能访问的菜单和对应菜单的功能
     * @param $user
     * @return array，返回系统所有菜单，用allow标示能否访问
     */
    private function getAccessList($user) {
        $result = array();
        $authId = $user["id"];
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

        $condition["is_valid"] = 1;
        if ($user["account"] == ADMIN_ACCOUNT){
            $condition["is_system"] = 1;
            $menuList = M("SysMenu")->where($condition)->field("id,parent_id,url,is_dac")->select();
        }else{
            //获取最大权限（商户或客户）
            if ($user["user_type"] != USER_TYPE_COMPANY_MANAGER){
                $condition["b.role_id"] = ROLE_ID_CUSTOMER;
            }else {
                $condition["b.role_id"] = ROLE_ID_COMPANY_MANAGER;
            }
            $menuList = M("SysMenu a")->join("sys_role_operation b on a.id=b.menu_id")
                ->where($condition)->field("a.id,a.parent_id,a.url,a.is_dac")->select();
        }
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

    //计算用户角色，1表示Leader管理员，2表示为管理员的角色,Leader是平台指定的，角色为ROLE_COMPANY_MANAGER
    private function getUserIdentity($user){
        $result = USR_IDENTITY_NORMAL;
        if ($user["role_ids"]) {
            $where['id'] = $user['branch_id'];
            $where['leader_id'] = $user['id'];
            $where['parent_id'] = 1;
            if (M("SysBranch")->where($where)->count() > 0) {
                $result = USR_IDENTITY_SUPPER;
            } else {
                $condition["id"] = array("in", $user["role_ids"]);
                $condition["is_admin"] = 1;
                if (M("SysRole")->where($condition)->count() > 0) {
                    $result = USR_IDENTITY_MANAGER;
                }
            }
        }
        return $result;
    }
    //private function getVisi

    //如果访问范围是所属部门，自动登入，指定部门的话，需要出现选择部门框
    public function getLoginUserInfo($user) {
        $userSession = new UserSessionModel();
        $userSession->isAdmin = ($user["account"] == ADMIN_ACCOUNT); //是否超级用户
        $userSession->isPlatformUser = ($user["branch_id"] == 1) || ($userSession->isAdmin);//平台用户
        $userSession->userId = intval($user["id"]);
        $userSession->userName = $user["name"];
        $userSession->account = $user["account"];
        $userSession->userType = $user["user_type"];
        $userSession->dacType = intval($user["dac_type"]);
        $userSession->is_leader = intval($user["is_leader"]);
        $userSession->permissionList = $this->getAccessList($user); //功能权限
        $userSession->userIdentity = $this->getUserIdentity($user);
        $userSession->staffName = $user['staff_name'];
        $userSession->branchRole = intval(M("SysBranch")->where("id=".$user["branch_id"])->getField("branch_role"));
        //获取可见人列表
        if (DAC_SCOPE_DEPARTMENT == $userSession->dacType && $user["dac_branchs"]) {
            $condition["branch_id"] = array("in", $user["dac_branchs"]);
            $visiblers = M("SysUserBranch")->where($condition)->getField("user_id", true);
            $userSession->visiblers = strval(implode(",", $visiblers));
        }else{
            $userSession->visiblers = $user["dac_users"];
        }
        //判断员工是否有权限
        if($userSession->userType == USER_TYPE_COMPANY_MANAGER){
            $result = $this->hasMenuPermit($userSession->permissionList["menus"]);
            if($result == false){
                return false;
            }
        }
        $this->setUserDataAccess($userSession, $user["id"]);
        $this->setUserBranch($userSession, $user["branch_id"]);
        return $userSession;
    }

    //是否有功能权限，如果没有则不允许登陆
    public function hasMenuPermit($menus){
        $allow = false;
        foreach ($menus as $value){
            if($value['allow'] == 1){
                $allow = true;
                break;
            }
        }
        if(!$allow){
            return false;
        }else{
            return true;
        }
    }
    
    //指定部门的用户，根据传入的部门ID取访问权限
    public function setUserBranch($userSession, $branch_id){
        $userSession->branchList = null;
        if (!$userSession->isPlatformUser) {
            $userBranchs = array();
            $branch_data_list = $this->getUserBranchs($userSession->userId, $branch_id);
            if ($branch_data_list) {
                foreach ($branch_data_list as $branch_data){
                    if ($branch_data["id"] == $branch_id){
                        $userSession->currBranchId = intval($branch_data["id"]);
                        $userSession->currBranchName = $branch_data["name"];
                        $userSession->currBranchCode = $branch_data["code"];
                        $userSession->currBranchType = intval($branch_data["type"]);
                        $userSession->parentBranchCode = $branch_data["parent_code"];
                        $userSession->parentBranchId = intval($branch_data["parent_id"]);
                        $userSession->entScale = $branch_data["ent_scale"];
                        $userSession->entType = $branch_data["ent_type_id"];
                    }
                    $userBranchs[] = array("id"=>$branch_data["id"], "name"=>$branch_data["name"]);
                }
            }
            $userSession->branchList = $userBranchs;
        }
    }

    //获取员工或客户所在的公司（非部门）,如果是员工，只会返回对应的商户，客户的话如果有多公司，会返回多个公司
    public function getUserBranchs($user_id, $branch_id){
        $user_data = $this->field("id,user_type")->where("id=$user_id")->find();
        if ($user_data["user_type"] == USER_TYPE_COMPANY_MANAGER){ //公司员工，类型必须是商户
            $sql = "select a.id,a.name,a.code,a.parent_id,p.code as parent_code,a.ent_scale,a.ent_type_id 
                  from sys_branch a 
                  left join sys_branch p on p.id=a.parent_id 
                  where a.type=". ORG_BRANCH ." and a.id=$branch_id";
        }else{ //客户的话，归属公司类型必须是公司
            $sql = "select a.id,a.name,a.code,a.parent_id,p.code as parent_code,a.ent_scale,a.ent_type_id                      
                    from sys_user_branch c 
                    inner join sys_branch a  on a.id=c.branch_id 
                    inner join sys_branch p on p.id=a.parent_id
                    where a.type=". ORG_COMPANY ." and c.user_id=$user_id";
        }
        $branch_data_list = $this->query($sql);
        return $branch_data_list;
    }
    public function setUserDataAccess($userSession,$user_id){
        $condition['user_id'] = $user_id;
        $target_id_branchs = M('sysUserDataAccess') ->field('target_id,type')-> where($condition) ->select();
        $target_value = [
            '0' => '_branchs',
            '1' => '_users'
        ];
        $target = [];
        if ($target_id_branchs) {
            foreach($target_id_branchs as $key => $value) {
                $target[$target_value[$value['type']]][] = $value['target_id'];
            }
        }
        $userSession->userDataAccess = $target;
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
    function getUsersRole($user_id, $getField = '') {
        $where['id'] = $user_id;
        $result = M('SysUser')->where($where)->find();
        return ($getField != '') ? $result[$getField] : $result;
    }

    /////客户
    public function getCompanyData($data)
    {
        $condition['users.id'] = $data['id'];
        // $condition['branch.type'] = 1;
        $condition['branch.type'] = array('neq',2);
        $condition['branch.parent_id'] = getBrowseBranchId();
        $branchs = $this->alias('users')
            ->field('branch.*')
            ->join('sys_user_branch as urt on urt.user_id = users.id')
            ->join('sys_branch as branch on branch.id = urt.branch_id')
            ->where($condition)
            ->select();
        return $branchs;
    }
    public function getCompanyNames($data)
    {
        $branchs = $this->getCompanyData($data);
        if ($branchs){
            $temp = [];
            foreach($branchs as $key => $val)
            {
                $temp[] = $val['name'];
            }
            return implode(',',$temp);
        } else {
            return '';
        }
    }
    public function getCompanyIds($data)
    {
        $branchs = $this->getCompanyData($data);
        if ($branchs){
            $temp = [];
            foreach($branchs as $key => $val)
            {
                $temp[] = $val['id'];
            }
            return implode(',',$temp);
        } else {
            return '';
        }
    }
    //获取业务负责人
    public function getServiceMan($data)
    {
        $condition['id'] = $data['service_man'];
        $condition['branch_id'] = getBrowseBranchId();
        $SysUser = M('SysUser')
            ->field('name')
            ->where($condition)
            ->find();
        return $SysUser['name'];
    }
        //获取业标签id
    public function getTagIds($data)
    {
        $sysUserRelationTag = D('SysUserRelationTag')
        ->where(['user_id'=>$data['id'],'branch_id'=>getBrowseBranchId()])
        ->field('id','tag')
        ->select();
        $tag_ids = [];
        foreach ($sysUserRelationTag as $k => $v) {
             $tag_ids[] = $v['tag'];
        }
        $tag_ids = implode(",", $tag_ids);
        return $tag_ids;
    }

    //获取商户员工管辖的客户(需要重新获取用户信息，因为session当前已经变成客户身份）
    public function getUserCompanys($user_id, $keyword) {
        $userData = $this->where("id=$user_id")->find();
        $userIdentity = $this->getUserIdentity($userData);
        if ($userIdentity > USR_IDENTITY_MANAGER ){
            $sql = sprintf("select a.id,a.name,a.querykey from sys_branch a 
              where (a.querykey like '%%%s%%' or a.name like '%%%s%%') and a.type=%d and a.parent_id=%d order by a.name", $keyword, $keyword, ORG_COMPANY, $userData["branch_id"]);
            $result = $this->query($sql);
        }else {
            /*$sql = sprintf("select a.id,a.name,a.querykey from sys_branch a inner join sys_user_data_access  b on a.id=b.target_id
               where (a.querykey like '%%%s%%' or a.name like '%%%s%%') and b.type=0 and b.user_id=%d order by a.name", $keyword, $keyword, $user_id);*/
            //$result = $this->query($sql);
            $user_session = session(USER_SESSION_KEY);
            //凭证模式获取可进入公司列表，session变回正常员工，获取完之后再变回凭证模式
            if (session("environment") == "voucher" && $userData) {
                $user = $this->getLoginUserInfo($userData);
                session(USER_SESSION_KEY, $user);
            }
            $filter = [];
            $filter['_string'] = "a.name like '%$keyword%' or a.querykey like '%$keyword%'";
            $result = D("ComCompany")->setDacFilter("a")->field("DISTINCT a.id,a.name,a.querykey")->where($filter)->select();
            $this->setUserCompany($user_session, $user_session->currBranchId);
        }
        return $result;
    }

    //指定部门的用户，根据传入的部门ID取访问权限（财务公司员工选择公司后，身份切换成客户管理员）
    public function setUserCompany(UserSessionModel $userSession, $branch_id){
        $userSession->branchList = null;
        if (!$userSession->isPlatformUser) {
            $sql = "select a.id,a.name,a.code,a.parent_id,p.code as parent_code,a.ent_scale,a.ent_type_id 
                    from sys_branch a 
                    left join sys_branch p on p.id=a.parent_id where a.id=$branch_id";
            $branch_data_list = $this->query($sql);
            if ($branch_data_list) {
                $userSession->currBranchId = intval($branch_data_list[0]["id"]);
                $userSession->currBranchName = $branch_data_list[0]["name"];
                $userSession->currBranchCode = $branch_data_list[0]["code"];
                $userSession->currBranchType = intval($branch_data_list[0]["type"]);
                $userSession->parentBranchCode = $branch_data_list[0]["parent_code"];
                $userSession->parentBranchId = intval($branch_data_list[0]["parent_id"]);
                $userSession->entScale = $branch_data_list[0]["ent_scale"];
                $userSession->entType = $branch_data_list[0]["ent_type_id"];
                $userSession->dacType = DAC_SCOPE_BRANCH; //最大权限
                $userSession->userIdentity = USR_IDENTITY_SUPPER;//最大权限
            }
        }
    }

}
