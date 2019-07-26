<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/27
 * Time: 14:00
 */

namespace EShop\Controller;
use Common\Lib\Controller\ComplexDataController;
use Org\Util\Strings;
use Think\Controller;

Class ComCompanyController extends BaseController {
    protected $_user_session = null;
    protected $userId; //登录用户ID
    public function indexAction(){
        $tag = D(CONTROLLER_NAME)->getCompanyTag();
        $this->assign('tag',$tag);
        $this->assign("title", '客户档案');
        $this->display('index');
    }

    public function listAction($page){
        $condition['a.parent_id'] = getBrowseBranchId();
        $condition['a.type'] = ORG_COMPANY;
        $postData = I("post.");
        if($postData['tag_type']){
            $condition['a.tag_type'] = I("post.tag_type");
        }
        if($postData['tag_origin']){
            $condition['a.tag_origin'] = I("post.tag_origin");
        }
        $keyword = $postData['keyword'];
        if($keyword){
            $condition['_string'] = "a.name like '%$keyword%' or a.linkman like '%$keyword%' or a.contact like '%$keyword%'";
        }
        if($postData['timeType'] == 0 && $postData['timeType'] != ""){
            //自定义时间
            $condition['a.create_time'] = array(array("egt",$postData['zdyTimeStart']),array("elt",$postData['zdyTimeEnd']));
        }elseif($postData['timeType'] != ""){
            $date = D("WrkAgreement")->getQdrDate($postData['timeType']);
            $condition['a.create_time'] = array(array("egt",$date['begin']),array("elt",$date['end']));
        }
        $data = D(CONTROLLER_NAME)
            ->setDacFilter('a')
            ->join("left join sys_user b on a.customer_leader_id = b.id")
            ->join("left join com_company_tag c on a.tag_type = c.id")
            ->join("left join com_company_tag d on a.tag_origin = d.id")
            ->field('a.name,a.id,a.linkman,a.contact,b.head_pic,c.value as tag_type_value,d.value as tag_origin_value')
            ->where($condition)
            ->order("a.id desc")
            ->page($page,20)
            ->select();
        $this->ajaxReturn($data);
    }

    public function saveAction(){
        $data = I("post.");
        $branch_id = $data['branch_id'];
        $id=$data['id'];
        $parent_id = $data['parent_id'];
        if($id && $parent_id){
            //判断已有自定义属性是否和表单提交的旧属性数量相等，不相等则删除不匹配的
            $ids = M("SysBranchInformation")->where("branch_id=$id")->field("id")->select();
            if(count($ids) != count($data['information']['old'])){
                $delIds = explode(";",$data['delInfo']);
                for($i=0;$i<count($delIds);$i++){
                    M("SysBranchInformation")->where("id=$delIds[$i]")->delete();
                }
            }
            //修改旧属性
            for($i=0;$i<count($data['information']['old']);$i++) {
                $info = explode("||",$data['information']['old'][$i]);
                //$information['id'] = $info[0];
                $information['title'] = $info[1];
                $information['value'] = $info[2];
                $information['updated_at'] = "123";//time();
                M("SysBranchInformation")->where("id =$info[0] ")->update($information);
            }
            //新增属性
            for($i=0;$i<count($data['information']['new']);$i++){
                $info = explode("||",$data['information']['new'][$i]);
                $information['title'] = $info[0];
                $information['value'] = $info[1];
                $information['branch_id'] = $id;
                $information['created_at'] = time();
                M("SysBranchInformation")->add($information);
            }
            //修改公司信息
            $name = $data['name'];
            $isRepeat = M('SysBranch')->where("name='$name' and parent_id = $parent_id and id<>$id")->count();
            if($isRepeat != 0){
                $this->ajaxReturn(array('error'=>1,'message'=>'名称重复!'));
            }else{
                $data['reg_province'] = $data["reg"]["province"];
                $data['reg_city'] = $data["reg"]["city"];
                $data['reg_district'] = $data["reg"]["district"];
                $data['update_time'] = time();
                $data['querykey'] = firstPinyin($data['name']);
                $result = M("SysBranch")->where("id=$id and parent_id=$parent_id")->update($data);
                if($result === false){
                    /*$customer_leader_id = M("SysBranch")->where("id = ".$data['id'])->getField("customer_leader_id");
                    if($customer_leader_id != $data['customer_leader_id']){
                        M("SysUserBranch")->where("branch_id = ".$data['id']." and user_id = ".$customer_leader_id ." and type <> 2")->delete();
                    }
                    if($data['customer_leader_id']){
                        M("SysUserBranch")->add(["branch_id"=>$data['id'],"user_id"=>$data['customer_leader_id'] ,"type"=>ORG_COMPANY]);
                    }*/
                    $this->ajaxReturn(array('error'=>1,'message'=>'修改失败!'));
                }else{
                    D("WrkInvoicePlan")->addLog(CONTROLLER_NAME,"update",$id);
                    $this->ajaxReturn(array('error'=>0,'message'=>"修改成功！"));
                }
            }
        }else{
            //新增
            $name = $data['name'];
            $parent_id = getBrowseBranchId();
            $isRepeat = M('SysBranch')->where("name='$name' and parent_id = $parent_id")->count();
            if($isRepeat != 0){
                $this->ajaxReturn(array('error'=>1,'message'=>'名称重复!'));
            }else{
                $data['reg_province'] = $data["reg"]["province"];
                $data['reg_city'] = $data["reg"]["city"];
                $data['reg_district'] = $data["reg"]["district"];
                $data['parent_id'] = getBrowseBranchId();
                $data['type'] = 1;
                $data['is_valid'] = 1;
                $data['branch_id'] = getBrowseBranchId();
                $data['create_time'] = time();
                $data['attach_group'] = genUniqidKey();
                $data["querykey"] = firstPinyin($data["name"]);
                $data['user_id'] = $_SESSION['user_id'];
                //添加权限
                $data['creator_id'] = $_SESSION['user_id'];
                $data['leader_id'] = $_SESSION['user_id'];
                $code = empty($data["code"]) ? Strings::randString(8) : substr($data["code"], 0, 8);
                $parent_code = M('sysBranch')->getFieldById($parent_id, "code");
                $data["code"] = sprintf("%s_%s", $parent_code, $code);
                $result = M("SysBranch")->add($data);
                if($result != false){
                    //默认自己为该客户档案的文件负责人
                    $sysDocument = [
                        "company_id"=>$result,
                        "branch_id"=>getBrowseBranchId(),
                        "type"=>DAC_SETTING_TYPE_BRANCH,
                        "user_id"=>$_SESSION['user_id'],
                        "module"=>"SysDocument",
                        "permit_value"=>DAC_PERMIT_VALUE_LEADER
                    ];
                    M("SysUserModuleSetting")->add($sysDocument);
                    D("WrkInvoicePlan")->addLog(CONTROLLER_NAME,"add",$result);
                    //新增属性
                    for($i=0;$i<count($data['information']['new']);$i++){
                        $info = explode("||",$data['information']['new'][$i]);
                        $information['title'] = $info[0];
                        $information['value'] = $info[1];
                        $information['branch_id'] = $result;
                        $information['created_at'] = time();
                        M("SysBranchInformation")->add($information);
                    }
                    if($data['customer_leader_id']){
                        $tmp = M("SysUserBranch")->where("branch_id = $result and user_id = ".$data['customer_leader_id'] ." and type <> 2")->find();
                        if(!$tmp){
                            M("SysUserBranch")->add(["branch_id"=>$result,"user_id"=>$data['customer_leader_id'] ,"type"=>ORG_COMPANY]);
                        }
                    }
                    $this->ajaxReturn(array('error'=>0,'message'=>'新增成功!'));
                }else{
                    $this->ajaxReturn(array('error'=>1,'message'=>'新增失败!'));
                }
            }
        }
    }

    public function file_editAction(){
        $id=$_REQUEST["id"];
        if($id){
            $result = M("SysBranch a")
                ->join("left join sys_user b on a.customer_leader_id = b.id")
                ->where("a.id=$id")->field("a.*,b.name as customer_leader_name,b.head_pic as customer_head_pic")->find();
            $result["information"] = M("SysBranchInformation")->where("branch_id=$id")->select();//自定义字段信息
        }
        //$this->_assign_base_data();
        $tag = D(CONTROLLER_NAME)->getCompanyTag();
        $this->assign("tag",$tag);
        $this->assign("title",empty($id) ? "新增公司" : "编辑档案" );
        $this->assign('company',$result);
        $this->display();
    }

    public function delAction(){
        $id=$_REQUEST["data"];
        for($i=0;$i<count($id);$i++){
            $agreement = M("WrkAgreement")->where("company_id = ".$id[$i])->find();
            if($agreement){
                $this->ajaxReturn(array("error"=>1,"message"=>"客户档案已生成合同，无法删除！"));
            }
            $instance_permit = D(CONTROLLER_NAME)->getPermitValue($id[$i]);
            if($instance_permit < 8){
                $this->ajaxReturn(array("error"=>1,"message"=>"您没有权限删除此客户档案！"));
            }
            M("SysBranch")->where("id=$id[$i]")->delete();
        }
        $this->ajaxReturn(array("error"=>0,"message"=>"删除成功！"));
    }

    protected function _assign_base_data() {
        $region_list = M('sysRegion') -> field("id as value,name as text,id,parent_id") -> cache(true) -> order("level asc,sort desc") -> select();
        $region = list_to_tree($region_list, 0 ,"id", "parent_id", "children");
        $this -> assign('region', json_encode($region));
    }
    protected function handlerPermissionsProcessing()
    {
        parent::handlerPermissionsProcessing();
        switch (ACTION_NAME){
            case 'del':
                $this->_permission_action_name = 'delete';
                break;
            case 'file_edit':
                $this->_permission_action_name = 'detail';
                break;
            case 'save':
                if(I('post.id') > 0 && I('post.parent_id') > 0) {
                    $this->_permission_action_name = 'update';
                } else {
                    $this->_permission_action_name = 'add';
                }
                break;
        }
    }

    //绑定客户管理员选择微信用户界面
    public function bindWeiXinAction(){
        //获取标签
        A("Organization")->userListsAssign(0);
        $this->display();
    }

    //沟通记录
    public function communicationAction(){
        $id = I("get.id");
        $result = M("SysBranch")->where("id = $id")->field("id,name,attach_group")->find();
        $this->assign("model",$result);
        $this->assign("title","沟通记录");
        $this->display();
    }

    //切换公司界面
    public function selectCompanyAction(){
        $condition['a.parent_id'] = getBrowseBranchId();
        $condition['a.type'] = ORG_COMPANY;
        $keyword = I("get.name");
        if($keyword){
            $condition['a.name'] = array("like","%$keyword%");
        }
        $result = D(CONTROLLER_NAME)->setDacFilter("a")->where($condition)->field("id as value,name as text")->select();
        $this->assign("init",I("get.init"));
        $this->assign("company",$result);
        $this->display("select_company");
    }
}