<?php

namespace EShop\Controller;

class UserManageController extends BaseDataController {
	public function IndexAction(){
		$this->display();
	}

    public function user_detailAction(){
        $id = I("get.id");
        $result = M("SysUser")->where("id = '$id'")->select();
        $this->_assign_base_data();
        if(isset($result[0]['province']) && $result[0]['province'] > 0) {
            $result[0]['region'] = region($result[0]['province']) .' '.region($result[0]['city']).' '.region($result[0]['district']);
        }
        if($result[0]['followed_at'] != ""){
            $result[0]['followed_at'] = date("Y-m-d H:i:s",$result[0]['followed_at']);
        }
        if($result[0]['binded_at'] != ""){
            $result[0]['binded_at'] = date("Y-m-d H:i:s",$result[0]['binded_at']);
        }

        //分组名称
        if($result[0]['group_id'] != ""){
            $group_id = $result[0]['group_id'];
            $result[0]['group'] = M("SysTargetGroup")->where("id = $group_id")->field('value')->find();
        }
        //标签
        if($result[0]['tag_ids'] != ""){
            $tag_ids = $result[0]['tag_ids'] ;
            $tag = M("SysTargetTag")->where("id in($tag_ids)")->select();
            $result[0]['tag'] = $tag ;
        }
        //已绑定公司信息
        $com_info =  $this->showBindCom($id);
        $this->assign("com_info",$com_info);
        //自定义类型
        $information = M("SysCustomerInformation")->where("user_id = $id")->select();
        //业务负责人、可见人
        $service_man_id = $result[0]['service_man'];
        $service_leader_id = $result[0]['service_leader'];
        if($service_man_id != ""){
            $result[0]['service_man_name'] = M("SysUser")->field('name')->where("id = $service_man_id")->find();
        }
        if($service_leader_id != ""){
            $result[0]['service_leader_name'] = M("SysUser")->field('name')->where("id = $service_leader_id")->find();
        }
        $this->title="用户详情";
        $this->assign("result",$result[0]);
        $this->assign("information",$information);
        $this->display();
    }

    public function user_editAction(){
        $id = I("get.id");
        $branch_id = getBrowseBranchId();
        //$_filter = ['is_follow' => 1,'mobile' =>array('eq',''),'id'=>$id];
        $result = M("SysUser")->where("id = '$id'")->select();
        $this->_assign_base_data();
        if(isset($result[0]['province']) && $result[0]['province'] > 0) {
            $result[0]['region'] = region($result[0]['province']) .' '.region($result[0]['city']).' '.region($result[0]['district']);
        } else {
            $result[0]['region'] = strtolower(ACTION_NAME) === 'user_edit' ? '点击选择地址' : '';
        }
        if($result[0]['followed_at'] != ""){
            $result[0]['followed_at'] = date("Y-m-d H:i:s",$result[0]['followed_at']);
        }
        if($result[0]['binded_at'] != ""){
            $result[0]['binded_at'] = date("Y-m-d H:i:s",$result[0]['binded_at']);
        }

        //分组名称
        if($result[0]['group_id'] != ""){
            $group_id = $result[0]['group_id'];
            $result[0]['group'] = M("SysTargetGroup")->where("id = $group_id")->field('value')->find();
        }
        //标签
        if($result[0]['tag_ids'] != ""){
            $tag_ids = $result[0]['tag_ids'] ;
            $tag = M("SysTargetTag")->where("id in($tag_ids)")->select();
            $result[0]['tag'] = $tag ;
        }
        //已绑定公司信息
        $com_info =  $this->showBindCom($id);
        $this->assign("com_info",$com_info);
        //自定义类型
        $information = M("SysCustomerInformation")->where("user_id = $id")->select();
        //处理负责人、可见人
        $service_man_id = $result[0]['service_man'];
        $service_leader_id = $result[0]['service_leader'];
        if($service_man_id != ""){
            $result[0]['service_man_name'] = M("SysUser")->field('name')->where("id = $service_man_id")->find();
        }
        if($service_leader_id != ""){
            $result[0]['service_leader_name'] = M("SysUser")->field('name')->where("id = $service_leader_id")->find();
        }
        //处理分组
        //$result[0]['groups'] = M("SysTargetGroup")->where("branch_id = $branch_id")->select();
        $this->service_man_list = json_encode(D('SysUser')->field('id as value,name as text')->where("user_type=3 and branch_id = $branch_id")->select());
        $this->service_leader_list = json_encode(D('SysUser')->field('id as value,name as text')->where("user_type=3 and branch_id = $branch_id")->select());
        $this->group_list = json_encode(M("SysTargetGroup")->field('id as value,value as text')->where("branch_id = $branch_id")->select());
        $this->title="用户编辑";
        $this->assign("result",$result[0]);
        $this->assign("information",$information);
        $this->display();
    }


    public function saveAction(){
        $data = I("post.");
        $id = $data['id'];
        $branch_id = $data['branch_id'];
        //处理解除匹配公司
        if($data['unbindCom'] != ""){
            $condition['user_id'] = $data['id'];
            $condition['branch_id'] = array("in",$data['unbindCom']);
            $condition['type'] = 1;
            $result = M('sys_user_branch')->where($condition)->delete();
            if($result === false){
                $this->ajaxReturn(array("error"=>1,"message"=>"解绑失败"));
            }
        }
        //判断已有自定义属性是否和表单提交的旧属性数量相等，不相等则删除不匹配的
        $ids = M("SysCustomerInformation")->where("branch_id=$branch_id and user_id =$id")->field("id")->select();
        if(count($ids) != count($data['information']['old']) ){
            $delIds = explode(";",$data['delInfo']);
            for($i=0;$i<count($delIds);$i++){
                M("SysCustomerInformation")->where("id=$delIds[$i]")->delete();
            }
        }
        //修改旧属性
        for($i=0;$i<count($data['information']['old']);$i++) {
            $info = explode("||",$data['information']['old'][$i]);
            //$information['id'] = $info[0];
            $information['title'] = $info[1];
            $information['value'] = $info[2];
            $information['updated_at'] = time();
            M("SysCustomerInformation")->where("id =$info[0] ")->update($information);
        }
        //新增属性
        for($i=0;$i<count($data['information']['new']);$i++){
            $info = explode("||",$data['information']['new'][$i]);
            $information['title'] = $info[0];
            $information['value'] = $info[1];
            $information['user_id'] = $id;
            $information['branch_id'] = $branch_id;
            $information['created_at'] = time();
            M("SysCustomerInformation")->add($information);
        }
        //处理标签
        if(count($data['tags'] )<= 0){
            M("SysUser")->where("id = $id")->setField('tag_ids',"");
            M("SysUserRelationTag")->where("user_id = $id and branch_id = $branch_id")->delete();
        }else{
            $tag = "";
            //M("SysUserRelationTag")->where("user_id = $id and branch_id = $branch_id")->delete();
            for($i=0;$i<count($data['tags']);$i++){
                $tag = $tag == "" ?  $data['tags'][$i] : $tag .",".$data['tags'][$i];
                $tmp['user_id'] = $id;
                $tmp['branch_id'] = $branch_id;
                $tmp['tag'] = $data['tags'][$i];
                $tmp['created_at'] = time();
                $tag_id = $data['tags'][$i];
                $result = M("SysUserRelationTag")->where("user_id = $id and branch_id = $branch_id and tag =$tag_id")->find();
                if($result == null){
                    M("SysUserRelationTag")->add($tmp);
                }
            }
            M("SysUser")->where("id = $id")->setField('tag_ids',$tag);
            M("SysUserRelationTag")->where("user_id = $id and branch_id = $branch_id and tag not in ($tag)")->delete();
        }
        $result = M("SysUser")->where("id=".$data['id'])->save($data);
        if($result !== false){
            $this->ajaxReturn(array("error"=>0,"message"=>"保存成功"));
        }else{
            $this->ajaxReturn(array("error"=>1,"message"=>"保存失败"));
        }
    }

    protected function _assign_base_data() {
        $region_list = M('sysRegion') -> field("id as value,name as text,id,parent_id") -> cache(true) -> order("level asc,sort desc") -> select();
        $region = list_to_tree($region_list, 0 ,"id", "parent_id", "children");
        $this -> assign('region', json_encode($region));
    }

    protected function showBindCom($id){
        $com_ids = M("SysUserBranch")->where("user_id = $id")->field("branch_id")->select();
        if(count($com_ids)>0){
            $com_id ="";
            for($i=0;$i<count($com_ids);$i++){
                $com_id = $com_id =="" ? $com_ids[$i]['branch_id'] :$com_id .",".$com_ids[$i]['branch_id'];
            }
            $com_info = M("SysBranch")->where("id in($com_id)")->select();
            if(isset($com_info[0]['province']) && $com_info[0]['province'] > 0) {
                $com_info[0]['region'] = region($com_info[0]['province']) .' '.region($com_info[0]['city']).' '.region($com_info[0]['district']);
            }
            if(isset($com_info[0]['reg_province']) && $com_info[0]['reg_province'] > 0) {
                $com_info[0]['reg_region'] = region($com_info[0]['reg_province']) .' '.region($com_info[0]['reg_city']).' '.region($com_info[0]['reg_district']);
            }
            return $com_info;
        }
    }


    public function select_service_manAction($init = 0,$name = null){
        $condition = [];
        if (!empty($name)) {
            $condition['name'] = array('LIKE','%'.$name.'%');
        }
        $condition['branch_id'] = $this->user_branch;
        $condition['user_type'] = 3;

        $this->service_man = M('SysUser')
            ->field('id as value,name as text')
            ->where($condition)->select();
        $this->init = $init;
        $this->display('select_service_man');
    }
    public function select_service_leaderAction($init = 0,$name = null){
        $condition = [];
        if (!empty($name)) {
            $condition['name'] = array('LIKE','%'.$name.'%');
        }
        $condition['branch_id'] = $this->user_branch;
        $condition['user_type'] = 3;

        $this->service_leader = M('SysUser')
            ->field('id as value,name as text')
            ->where($condition)->select();
        $this->init = $init;
        $this->display('select_service_leader');
    }
 }