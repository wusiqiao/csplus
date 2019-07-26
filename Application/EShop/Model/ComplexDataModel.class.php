<?php

namespace EShop\Model;


/**混合权限Model,适用于需要控制可见人和协助人之类的功能
 * Class ComplexDataModel
 * @package Common\Lib\Model
 */
class ComplexDataModel extends DataModel {

    /* 默认权限过来字段 */
    protected $_leaderField = "leader_id";//负责人字段，在子类设置
    protected $_visiblersField = "visiblers"; //可见人字段，在子类设置
    protected $_collaboratorsField = "collaborators"; //协作人
    protected $_companyField = "company_id"; //客户字段
    protected $_access_module = ""; //access表对应的module名称,用于一表多用

    /**数据权限拦截函数
     * 根据用户dac设置，增加记录过滤条件，如果是按指定部门，表结构必须有creater_id或leader_id字段，且自动填入创建人
     * 涉及字段:
     * 用户表 sys_user: 查看数据权限范围：dac_type，0：表示默认，1表示指定部门，2表示全部，dac_branchs:存放用户能查看的部门
     * 控制表，比如合同：wrk_agreement，字段：creater_id, leader_id, visiblers, collaborators，invisiblers（暂不启用）。
     * 必须指定WrkAgreementModel的四个属性：创建人字段，负责人字段，可见人字段，不可见人字段
     * 有些表只要求控制创建人字段作为过滤的话，只需要设置创建人字段，其他为空。
     * 当功能表中没有创建人字段，但是又设置按部门或创建人查看，则可以看到全部数据。
     * @param $alias
     */
    protected function defaultDacFilter($alias) {
        if ($user_id = session("user_id")) {
            $user_dac_data = $this->getUserAccessDataFromSession();
            $dacField = sprintf("%s.%s",$alias , $this->_dacfield);
            $idField = sprintf("%s.id",$alias);
            $this->options["where"][$dacField] = $user_dac_data["branch_id"];
            if (DAC_SCOPE_BRANCH != intval($user_dac_data["dac_type"])) { //自己可见或下属可见
                $acc_condition["UMA.user_id"] = $user_id;
                if (in_array($this->_leaderField, $this->fields)) {
                    $acc_condition[$alias.".".$this->_leaderField] = array("in", $user_dac_data["visiblers"]);
                }
                $acc_condition["_logic"] = "OR";
                $join_condition = sprintf(" %s=UMA.instance_id AND UMA.module='%s' AND UMA.user_id=%d", $idField, $this->getAccessModule(), $user_id);
                $this->options["join"]["UMA"] = "LEFT JOIN sys_user_module_access UMA ON " . $join_condition;
                $this->options["where"]["_complex"][] = $acc_condition;
            }
        }
    }

    protected function _after_insert($data, $options) {
        parent::_after_insert($data, $options);
        $this->insert_module_userpermit($data);
    }

    protected function _after_delete($data,$options) {
        $condition["instance_id"] = $data["id"];
        $condition["module"] = $this->getAccessModule();
        M("SysUserModuleAccess")->where($condition)->delete();
        parent::_after_delete($data, $options);
    }

    // 更新成功后的回调方法
    protected function _after_update($data,$options) {
        parent::_after_update($data, $options);
        $condition["instance_id"] = $data["id"];
        $condition["module"] = $this->getAccessModule();
        M("SysUserModuleAccess")->where($condition)->delete();
        $this->insert_module_userpermit($data);
    }

    //可见人和协作人分解到SysUserModuleAccess表,如果提交的数据没有，就从sysUserModuleAccess提取
    private function insert_module_userpermit($model_data){
        $datas = array();
        if ($this->_visiblersField) {
            $datas = $this->getPermitDatasFromPostData($model_data, $this->_visiblersField, DAC_PERMIT_VALUE_VISIBLER);
        }else{
            $datas = getPermitDatasFromSetting($model_data, DAC_PERMIT_VALUE_VISIBLER);
        }
        if ($this->_collaboratorsField) {
            $datas = array_merge($datas, $this->getPermitDatasFromPostData($model_data, $this->_collaboratorsField, DAC_PERMIT_VALUE_COLLABORATOR));
        }else{
            $datas = array_merge($datas, $this->getPermitDatasFromSetting($model_data, DAC_PERMIT_VALUE_COLLABORATOR));
        }
        //更根据公司编号获取通知人，复制添加到表内，方便扩展和提高查询效率
        if (in_array($this->_companyField, $this->fields)) {
            $datas = array_merge($datas, $this->getPermitDatasFromSetting($model_data, DAC_PERMIT_VALUE_NOTICER));
        }
        if($datas) {
            M("SysUserModuleAccess")->addAll($datas);
        }
    }

    //从post数据提取
    private function getPermitDatasFromPostData($model_data, $permit_field, $permit_value){
        $result = array();
        $permit_users = I("post.".$permit_field);
        if ($permit_users){
            if (!is_array($permit_users)){
                $permit_users = explode(",", $permit_users);
            }
            $instance_id = $model_data["id"];
            foreach ($permit_users as $key=>$permit_user){
                $permit_data[$key]["branch_id"] = getBrowseBranchId();
                $permit_data[$key]["module"] = $this->getAccessModule();
                $permit_data[$key]["user_id"] = $permit_user;
                $permit_data[$key]["instance_id"] = $instance_id;
                $permit_data[$key]["permit_value"] = $permit_value;
                $result[] = $permit_data[$key];
            }
        }
        return $result;
    }

    /**从配置表里面复制
     * @param $model_data
     * @param $permit_value
     * @return array
     */
    private function getPermitDatasFromSetting($model_data, $permit_value){
        $company_id = $model_data[$this->_companyField];
        $condition["company_id"] = $company_id;
        $condition["module"] = $this->getAccessModule();
        $condition["permit_value"] = $permit_value; //类型
        $condition["type"] = DAC_SETTING_TYPE_BRANCH;
        $result = M("SysUserModuleSetting")->where($condition)->select();
        foreach ($result as $key=>$value){
            $result[$key]["instance_id"] = $model_data["id"];
        }
        return $result;
    }

    //更新、删除前检查是否有数据权限
    public function checkDataPermission($id) {
        return  (D(CONTROLLER_NAME)->setDacFilter("a")->where("a.id=$id")->count() > 0);
    }

    //获取单据权限
    public function getPermitValue($id){
        $result = 0;
        $user_dac_data = $this->getUserAccessDataFromSession();
        if (DAC_SCOPE_BRANCH == $user_dac_data["dac_type"] || $user_dac_data["is_leader"]){
            return DAC_PERMIT_VALUE_LEADER;
        }
        //是否是负责人（负责人不会添加到SysUserModuleAccess表）
        if (in_array($this->_leaderField, $this->fields)) {
            $leader_id = $this->where("id=$id")->getField($this->_leaderField);
            if (in_array($leader_id, $user_dac_data["visiblers"])){
                return DAC_PERMIT_VALUE_LEADER;
            }
        }
        $where = sprintf("module='%s' and instance_id=%d and user_id=%d", $this->getAccessModule(), $id, $user_dac_data["id"]);
        $datas = $this->query("select permit_value from sys_user_module_access where $where order by permit_value desc");
        if ($datas){
            $result = $datas[0]["permit_value"];
        }
        return $result;
    }

    private function getAccessModule(){
        if (empty($this->_access_module)){
            $this->_access_module = $this->getModelName();
        }
        return $this->_access_module;
    }
}
