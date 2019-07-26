<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;


class SysUserController extends DataController {
  
 
    //获取解析前端传入的条件
    protected function _parseFilter(&$filter){
        parent::_parsefilter($filter);
        $filter["a.id"] = array("neq", $this->_user_session->userId);
        if(I("post.role_ids")){
            $role_ids = I("post.role_ids");
            $filter["_string"] = "FIND_IN_SET('$role_ids', role_ids)";
        }
    }

    protected function _before_add(&$data) {
        $data["birthday"] = time();
        $data["sex"] = 0;
        $data["dac_type"] = 0;        
        $data["no"] = $this->getMaxNoByUserBranch();
        $data["is_include_all"] = 1;
        $data["user_type"] = USER_TYPE_COMPANY_MANAGER;//默认新增是商家员工类型
        if ($this->_user_session->isPlatformUser){
            $data["is_leader"] = 1;
            $data["role_ids"] = ROLE_ID_COMPANY_MANAGER;
        }else{
//            $data["role_ids"] = ROLE_ID_COMPANY_SALES; //商户员工权限商户自己定义
            $data["role_ids"] = 0;
        }
        $data["branch_id"] = $this->_user_session->currBranchId;
        parent::_before_add($data);
    }  

   protected function _before_detail(&$data) {

        parent::_before_detail($data);
        $condition = [];
        $condition["user_id"] = $data['id'];
        $list = M("SysCustomerInformation")->field("id,title,type,value")->where($condition)->order('id asc')->select();
        $data["custom"] = $list;
        $condition = [];
        $condition["a.user_id"] = $data['id'];
        $condition["b.type"] = 2;
        $deptment = M("SysUserBranch")
            ->alias("a")
            ->join('LEFT JOIN sys_branch b ON b.id = a.branch_id')
            ->where($condition)->find();
        $data["deptment_id"] = isset($deptment['id'])?$deptment['id']:$this->_user_session->currBranchId;
        
   }

//    protected function _before_detail(&$data) {
//        parent::_before_detail($data);
//        if ($data["dac_branchs"] && $data["dac_type"] == DAC_BRANCH_SPECIFY){
//            $condition["id"] = array("in", $data["dac_branchs"]);
//            $list = M("SysBranch")->field("id,name")->where($condition)->select();
//            $data["dac_branchs_data"] = json_encode($list);
//        }
//    }
    protected function _before_copy(&$data) {
       $data["password"] = "123456"; 
    }
    
    public function loginAction($id = 0, $password = "", $repassword = "", $verify = "") {
        $this->redirect("Index");
    }

    public function logoutAction() {
        session_destroy();
        $this->redirect("Index/login");
    }
    
    protected function _before_write($type, &$data) {
        parent::_before_write($type, $data);
        //$data["role_ids"] = implode(",", $data["role_ids"]);
        /*if(count($data["role_ids"])>1){
            $data["role_ids"] = implode(",", I("post.role_ids"));
        }*/
        $data["tag_ids"] = implode(",", I("post.tag_ids"));
        if (self::ACTION_ADD == $type){
            if (empty($data["password"])){
                $data["password"] = $data["mobile"];
            }
            if (empty($data["account"])){
                $data["account"] = $data["mobile"];
            }
            if (!empty($data["mobile"])){
                $data["binded_at"] = time();
            }
        } elseif(self::ACTION_DETAIL == $type) {
            if (!empty($data["mobile"])){
                $mobile_old = D(CONTROLLER_NAME)->where('id = '.$data['id'])->getField('mobile');
                if ($mobile_old != $data["mobile"]) {
                    $data["binded_at"] = time();
                }
            }
        }
    }
    public function targetUpdatesAction() {
        if (IS_POST) {
            $model = D(CONTROLLER_NAME);
            $post_data = I('post.');
            $data = [];
            $_POST['_tip_target'] = 1;
            $sysUser = $model->where(["id"=>array('in',$post_data['users'])])->select();
            foreach($sysUser as $key => $val){
                    $tmp['id'] = $val['id'];
                if(!empty(I('post.tag_ids'))){
                    if (!empty($val['tag_ids'])) {
                        $tmp['tag_ids'] = explode(",", $val['tag_ids']);
                        $tmp['tag_ids'] = implode(",", array_merge($tmp['tag_ids'],I('post.tag_ids')));
                    }else{
                        $tmp['tag_ids'] = implode(",",I('post.tag_ids'));
                    }
                }
                if(!empty(I('post.group_id'))){
                    if (I('post.group_id') == 'other') {
                        $tmp['group_id'] = null;
                    }else{
                        $tmp['group_id'] = I('post.group_id');
                    }
                }
                
                $tmp['branch_id'] = getBrowseBranchId();
                $data[] = $tmp;
                // if (!$model->checkDataPermission($val)) {
                //     $this->responseJSON(buildMessage("保存失败：您没有权限更新此记录！", 1));
                // }
            }

            try {
                $model->startTrans();
                foreach($data as $key => $val) {
                    $this->_before_target_write(self::ACTION_DETAIL, $val);

                    if ($user_data = $model->create($val)) {
                        $updated = false;
                        $updated = $model->where("id=".$val['id'])->save($user_data);
                        $model->commit();

                        if ($updated !== false) {
                            
                            if(!empty(I('post.tag_ids'))){
                                $tag_ids = I('post.tag_ids');
                                $tag_data = [];
                                foreach ($tag_ids as $k1 => $v1) {
                                    $sysUserRelationTag = D('SysUserRelationTag')->where([
                                        'user_id'=>$val['id'],
                                        'tag'=>$v1,
                                        'branch_id'=>getBrowseBranchId()
                                    ])->select();

                                    if (empty($sysUserRelationTag)) {
                                        $tag_data[] = array(
                                            'user_id'=>$val['id'],
                                            'tag'=>$v1,
                                            'branch_id'=>getBrowseBranchId(),
                                            'created_at'=>time()
                                        );
                                    }
                                }
                                D('SysUserRelationTag')->addAll($tag_data);
                            }
                            $this->addLog($val['id']);
                            $result_data = $this->_getLastData($val['id']);
                            if ((count($post_data['users']) - 1) == $key){
                                $this->responseJSON(buildMessage($result_data));
                                break;
                            }
                        } else {
                            $this->responseJSON(buildMessage("保存失败：" . $model->getError(), 1));
                            break;
                        }
                    } else {
                        $this->responseJSON(buildMessage("保存失败：" . $model->getError(), 1));
                        break;
                    }
                }
            } catch (\Think\Exception $e) {
                $model->rollback();
                $this->responseJSON(buildMessage("保存失败：" . $model->getError(), 1));
            }
        } else {
            $this->display();
        }
    }
    protected function _before_target_write($type, &$data){
        parent::_before_write($type, $data);
        $data["tag_ids"] = implode(",", $data['tag_ids']);
    }
    public function changePasswordAction($id = null, $password = null, $repassword = null){
        if (IS_POST){
            if ($password != $repassword){
                $this->ajaxReturn(buildMessage("确认密码输入不一致",1));
            }
            $condition["id"] = intval($id);
            $user_record = D("SysUser")->where($condition)->field("password")->find();
            if ($user_record){
                $new_password = md5_plus($password);
                $result = M("SysUser")->where($condition)->setField("password", $new_password);    
                if ($result !== false){
                   $this->ajaxReturn(buildMessage("修改成功！"));  
                }else{
                   $this->ajaxReturn(buildMessage("未知错误，请联系管理员",1));
                }
            }else{
              $this->ajaxReturn(buildMessage("用户不存在或您没有此用户权限",1)); 
            }
        }else{
            $condition["id"] = intval($this->_user_session->userId);
            $user = M("SysUser")->where($condition)->find();
            $this->assign("user", $user);
            $this->display("changepwd");
        }
    }
    
    public function resetUserPwdAction($id){
        $new_password = md5_plus("123456");
        $result = M("SysUser")->where("id=$id")->setField("password", $new_password);    
        if ($result !== false){
           $this->ajaxReturn(buildMessage("重置成功！"));  
        }else{
           $this->ajaxReturn(buildMessage("未知错误，请联系管理员",1));
        }
    }
    
    public function getSalesAction(){
        $list = D(CONTROLLER_NAME)->setDacFilter()->field("a.id as value,a.name as text")->select();
        $this->responseJSON($list);
    }

    protected function _before_display_dataview(&$data)
    {
        parent::_before_display_dataview($data); // TODO: Change the autogenerated stub
        $data['companys'] = D(CONTROLLER_NAME)->getCompanyIds($data);
    }


}
