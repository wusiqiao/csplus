<?php

namespace ESAdmin\Model;

use Common\Lib\Model\TreeComplexDataModel;
use Common\Lib\Model\ComplexDataModel;

class ComCompanyModel extends TreeComplexDataModel {

    protected $_leaderField = "leader_id";//负责人字段，在子类设置,一定要设置
    protected $_visiblersField = "visiblers"; //可见人字段，如果原有此字段，就设置，没有就不需要
    protected $_collaboratorsField = "collaborators"; //协作人，如果原有此字段，就设置，没有就不需要
    protected $_companyField = "id"; //公司字段，一定要设置

    protected $tableName = 'sys_branch';
    protected $_branch_type = ORG_COMPANY;
    protected $_model_branch_information = 'sys_branch_information';
     protected $_link = array(
        "SysUser" => array(
            "join_name" => "LEFT",
            'class_name' => "SysUser",
            'foreign_key' => 'leader_id',
            'mapping_name' => 'leader',
            'mapping_fields' => 'name,staff_name',
            "mapping_key" => "id"
        ),
         "ComCompanyTag" => array(
             "join_name" => "LEFT",
             'class_name' => "ComCompanyTag",
             'foreign_key' => 'tag_type',
             'mapping_name' => 'tag_type',
             'mapping_fields' => 'value',
             "mapping_key" => "id"
         )
     );

    protected function _before_delete($options)
    {
        foreach ($options["where"]['id'][1] as $id){
            $agreement = M("WrkAgreement")->where("company_id = $id")->find();
            if($agreement){
                E("客户档案已生成合同，无法删除！");
                return false;
            }
        }
        parent::_before_delete($options);
    }

    protected function _options_filter(&$options) {
        $this->addOptionsFilter($options, array("type" => $this->_branch_type));
        parent::_options_filter($options);
    }

    public function __construct($name = '', $tablePrefix = '', $connection = '') {
        parent::__construct($name, $tablePrefix, $connection);
        $this->_auto[] = array("type", $this->_branch_type);
    }

    protected function _before_write(&$data) {
        parent::_before_write($data);
        /*if (empty($data["parent_id"])){
            E("必须有父级部门！");
        }
        $data["branch_id"] = $data["parent_id"];
        if (empty($data["querykey"])){
            $data["querykey"] = firstPinyin($data["name"]);
        }
        //名称检查
        $c["name"] = $data["name"];
        $c["branch_id"] = $data["branch_id"];
        if (($data["id"])){
            $c["id"] = array("neq", $data["id"]);
        }
        if (M("SysBranch")->where($c)->count() > 0){
            E("公司/部门名称已经存在");
        }*/
    }

    //获取自定义列表
    public function getCustomers($company_id)
    {
        $condition['branch_id'] = $company_id;
        return M($this->_model_branch_information)->field('id,title,value,type')->where($condition) ->select();
    }
    protected function _after_delete($data, $options)
    {
        parent::_after_delete($data, $options); // TODO: Change the autogenerated stub
        $condition['branch_id'] = array('in',$_POST['id']);
        M($this->_model_branch_information)->where($condition)->delete();
    }

    protected function _after_insert($data, $options)
    {
        parent::_after_insert($data, $options); // TODO: Change the autogenerated stub
        //自定义
        if (!empty($_POST['information'])) {
            $information_new = [];
            foreach($_POST['information']['value'] as $key => $value){
                $information_new[] = [
                    'value' => $value,
                    'title' => $_POST['information']['title'][$key],
                    'type' => $_POST['information']['type'][$key],
                    'branch_id' => $data['id'],
                    'created_at' => time(),
                    'updated_at' => time()
                ];
            }
            if ($information_new) {
                M($this->_model_branch_information)->addAll($information_new);
            }
        }

        $this->handlerCapitalJurisdiction($data);
        if($data['customer_leader_id']){
            $customer['user_id'] = $data['customer_leader_id'];
            $customer['branch_id'] = $data['id'];
            $customer['type'] = 1;
            $count = M("SysUserBranch")->where($customer)->count();
            if(!$count){
                M("SysUserBranch")->add($customer);
            }
        }
        $this->addUserModuleSetting($data);
    }
    protected function _after_update($data, $options)
    {
        parent::_after_update($data, $options); // TODO: Change the autogenerated stub
        //自定义
        if (!empty($_POST['information'])) {
            $information_new = [];
            $information_old = [];
            foreach($_POST['information']['value'] as $key => $value){
                if (strpos($key,'d-') !== false) {
                    $information_new[] = [
                        'value' => $value,
                        'title' => $_POST['information']['title'][$key],
                        'type' => $_POST['information']['type'][$key],
                        'branch_id' => $_POST['id'],
                        'created_at' => time(),
                        'updated_at' => time()
                    ];
                } else {
                    $information_old[] = [
                        'id' => $key,
                        'value' => $value,
                        'title' => $_POST['information']['title'][$key]
                    ];
                }
            }
            if ($information_old) {
                $oldIds = [];
                foreach($information_old as $key => $value) {
                    $oldIds[] = $value['id'];
                    $save = $value;
                    $save['updated_at'] = time();
                    M($this->_model_branch_information)->save($save);
                }
                //删除没有修改的旧数据
                if (!empty($oldIds)) {
                    $condition = 'branch_id = '.$_POST['id'].' and id NOT IN ('.implode(",",$oldIds).')';
                    M($this->_model_branch_information)->where($condition)->delete();
                }
            } else {
                $condition['branch_id'] = $_POST['id'];
                M($this->_model_branch_information)->where($condition)->delete();
            }
            if ($information_new) {
                M($this->_model_branch_information)->addAll($information_new);
            }
        } else {
            $condition['branch_id'] = $_POST['id'];
            M($this->_model_branch_information)->where($condition)->delete();
        }
        $this->handlerCapitalJurisdiction($data);
        if($data['customer_leader_id']){
            $customer['user_id'] = $data['customer_leader_id'];
            $customer['branch_id'] = $data['id'];
            $customer['type'] = 1;
            $count = M("SysUserBranch")->where($customer)->count();
            if(!$count){
                M("SysUserBranch")->add($customer);
            }
        }
        $this->addUserModuleSetting($data);
    }

    public function getCompanyListsByName()
    {
        $condition['parent_id'] = getBrowseBranchId();
        $condition['type'] = ORG_COMPANY;
        $condition['is_valid'] = 1;
        return $this->where($condition)->field('name,id')->select();
    }
    public function getCompanys()
    {
        $condition['a.parent_id'] = getBrowseBranchId();
        $condition['a.type'] = ORG_COMPANY;
        $condition['a.is_valid'] = 1;
        $result = $this->alias("a")
            ->join("left join com_company_tag b on a.tag_type = b.id")
            ->join("left join com_company_tag c on a.tag_origin = c.id")
            ->where($condition)->field("a.*,b.value as tag_type,c.value as tag_origin")->select();
        foreach ($result as $k=>$v){
            $result[$k]['customer_leader_id'] = $v['customer_leader_id'] == "" ? "否":"是";
            if($v['attach_group']){
                $tmp['group'] = $v['attach_group'];
                $tmp['content'] = array("neq","");
                $result[$k]['attach_group'] = M("ComAttachment")->where($tmp)->order("id desc")->getField("content");
            }
        }
        return $result;
    }
    public function handlerCapitalJurisdiction($data)
    {
        $account_jurisdiction = D('ComAccountJurisdiction');
        $postdata = I('post.');
        $recharge['leader_id'] = $postdata['recharge_leader_id'] ?? null;
        $withdrawal['leader_id'] = $postdata['withdrawal_leader_id'] ?? null;
        $account_jurisdiction->setOptions('object_id',$data['id']);
        $account_jurisdiction->setOptions('object_type',1);
        $account_jurisdiction->setObjectVarious([CAJ_BRANCH_RECHARGE,CAJ_BRANCH_WITHDRAWAL]);
        $account_jurisdiction->setOptions(CAJ_BRANCH_RECHARGE,$recharge);
        $account_jurisdiction->setOptions(CAJ_BRANCH_WITHDRAWAL,$withdrawal);
        $account_jurisdiction->saveAccountJurisdiction();
    }

    public function addUserModuleSetting(&$data){
        $permitValue = array(
            DAC_PERMIT_VALUE_LEADER=>"_leader_id",
            DAC_PERMIT_VALUE_COLLABORATOR=>"_collaborators",
            DAC_PERMIT_VALUE_VISIBLER=>"_visiblers",
            DAC_PERMIT_VALUE_NOTICER=>"_notifiers");
        $modules = array('SysDocument', 'WxOperateTemplate', 'WrkAgreement', 'WrkInvoicePlan', 'WrkReceivables', 'WrkPrompt',"SocialSecurityNotice","WrkTaskPlan");
        $condition = [];
        $branch_id = getBrowseBranchId();
        foreach ($modules as $k=>$module){
            foreach ($permitValue as $key=>$value){
                $tmp = I("post.$module$value");
                if($value == "_leader_id" && $tmp){
                    $condition[] = ["module"=>$module,"branch_id"=>$branch_id,"company_id"=>$data['id'],"user_id"=>$tmp,"permit_value"=>array_search($value,$permitValue),"type"=>DAC_SETTING_TYPE_BRANCH];
                }elseif($tmp){
                    foreach($tmp as $v){
                        $condition[] = ["module"=>$module,"branch_id"=>$branch_id,"company_id"=>$data['id'],"user_id"=>$v,"permit_value"=>array_search($value,$permitValue),"type"=>DAC_SETTING_TYPE_BRANCH];
                    }
                }
            }
        }
        M("SysUserModuleSetting")->where("company_id = ".$data['id']." and type = ".DAC_SETTING_TYPE_BRANCH)->delete();
        M("SysUserModuleSetting")->addAll($condition);
    }
    /*获取项目树形结构，包括公司、项目*/
    public function getUserBranchTree($condition, $user_session, $is_filter = false) {
        $fields = "a.id,a.name as text, 'open' as state, a.parent_id,a.code,a.type";
        $condition["a.is_valid"] = 1;
        if (!$user_session->isPlatformUser) {
            $condition["a.parent_id"] = $user_session->currBranchId;
        } else {
            $condition["a.type"] = 0;
        }
        $branch_list = $this->setDacFilter("a")->callFilter($is_filter)->where($condition)->field($fields)->order("a.code")->select();
        $parent_id = 0;
        if (!$user_session->isPlatformUser) {
            array_unshift($branch_list,
                array("id" => $user_session->currBranchId,
                    "text" => $user_session->currBranchName,
                    "state"=>"open",
                    "type"=>$user_session->currBranchType,
                    "code"=>$user_session->currBranchCode,
                    "parent_id" => $user_session->parentBranchId
                )
            );
            $parent_id = $user_session->parentBranchId;
        }
        return list_to_tree($branch_list, $parent_id);
    }

    //设置客户档案标签类型为成交客户
    public function setCompanyTagType($company_id){
        $condition['value'] = "成交客户";
        $condition['type'] = 0;
        $condition['branch_id'] = getBrowseBranchId();
        $tag_type = M("ComCompanyTag")->where($condition)->getField("id");
        if($tag_type){
            M("SysBranch")->where("id = $company_id")->setField("tag_type",$tag_type);
        }
    }
}
