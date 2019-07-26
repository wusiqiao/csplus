<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class SysUserModuleSettingModel extends DataModel {
    //获取商户对应功能的公司列表
	/**
	 * @param $module	对应的功能
	 * @param $query	公司名称
	 * @param array $permit_value 权限值 [1,2,4,8] 权限值（通知人：1，可见人：2，协作人：4，负责人：8）
	 * @return array|mixed
	 */
    public function getCompanysByModule($module, $query,$permit_value=array()){
        $user_session = session(USER_SESSION_KEY);
        $condition_result = array("type"=>ORG_COMPANY);
        if ($user_session->is_leader == 0){
            $condition["branch_id"] = $user_session->currBranchId;
            $condition["user_id"] = $user_session->userId;
            $condition["module"] = $module;
			if (is_array($permit_value) && !empty($permit_value)){
				$condition["permit_value"] = array('in',$permit_value);
			}
            $condition["type"] = DAC_SETTING_TYPE_BRANCH;
            $list = M("SysUserModuleSetting")->where($condition)->group("company_id")->getField("company_id", true);
            if (empty($list)){
                return [];
            }else{
                $condition_result = array("id"=>array("in",$list));
            }
        }else{
            $condition_result["parent_id"] = $user_session->currBranchId;
        }
        $query_condition = array();
        if ($query){
            $query_condition["name"] = array("like", "%".$query."%");
            $query_condition["querykey"] = array("like", "%".$query."%");
            $query_condition["_logic"] = "OR";
        }
        if ($query_condition) {
            $condition_result["_complex"] = $query_condition;
        }
        $result = M("SysBranch")->where($condition_result)->field("id,name,name as text,querykey")->select();
        return $result;
    }

    //获取模块对应商户员工对客户的操作权限
    public function getPermitValue($module, $company_id){
        $result = 0;
        $user_session = session(USER_SESSION_KEY);
        if (DAC_SCOPE_BRANCH == $user_session->dacType){
            return DAC_PERMIT_VALUE_LEADER;
        }
        $condition["branch_id"] = $user_session->currBranchId;
        $condition["company_id"] = $company_id;
        $condition["user_id"] = $user_session->userId;
        $condition["module"] = $module;
        $data = $this->where($condition)->field("permit_value")->order("permit_value desc")->find();
        if ($data){
            $result = $data["permit_value"];
        }
        return $result;
    }

    //获取模块客户对应的负责人
	public function getMemberByModuleAndCompanyId($module,$companyId,$PermitValue){
		$user_session = session(USER_SESSION_KEY);
		$condition["branch_id"] = $user_session->currBranchId;
		$condition["company_id"] = $companyId;
		$condition["module"] = $module;
		$condition["permit_value"] = $PermitValue;
        $condition["type"] = DAC_SETTING_TYPE_BRANCH;
		$user_id = M("SysUserModuleSetting")->where($condition)->getField("user_id");
		return $user_id;
	}


}
