<?php

namespace Common\Lib\Model;

/**混合权限Model,适用于需要控制可见人和协助人之类的功能
 * Class ComplexDataModel
 * @package Common\Lib\Model
 */
class ComplexDataModel extends DataModel {
    /* 设置外键关联 */

    protected $_link = array();
    /* 默认权限过来字段 */
    protected $_leaderField = "leader_id";//负责人字段，在子类设置
    protected $_visiblersField = "visiblers"; //可见人字段，在子类设置
    protected $_collaboratorsField = "collaborators"; //协作人
    protected $_companyField = "company_id"; //客户字段
    protected $_access_module = ""; //access表对应的module名称,用于一表多用
    /* 过滤条件，用于一表多用 */
    protected $_filter = array();
    protected $_auto = array(
        array('update_time', 'time', 3, 'function')
    );

    /**数据权限拦截函数
     * 根据用户dac设置，增加记录过滤条件，如果是按指定部门，表结构必须有creater_id或leader_id字段，且自动填入创建人
     * 涉及字段:
     * 用户表 sys_user: 查看数据权限范围：dac_type，0：表示默认，1表示指定部门，2表示全部，dac_branchs:存放用户能查看的部门
     * 控制表，比如合同：wrk_agreement，字段：creater_id, leader_id, visiblers, collaborators，invisiblers（暂不启用）。
     * 必须指定WrkAgreementModel的四个属性：创建人字段，负责人字段，可见人字段，不可见人字段
     * 有些表只要求控制创建人字段作为过滤的话，只需要设置创建人字段，其他为空。
     * 当功能表中没有创建人字段，但是又设置按部门或创建人查看，则可以看到全部数据。
     * @param $user_session
     * @param $alias
     */
    protected function defaultDacFilter($user_session, $alias) {
        if ($user_session) {
            $dacField = sprintf("%s.%s",$alias , $this->_dacfield);
            $idField = sprintf("%s.id",$alias);
            if (!$user_session->isPlatformUser) {
                if ($user_session->currBranchCode) { //branchCode有值
                    $this->options["join"]["DAC"] = "INNER JOIN sys_branch DAC ON $dacField=DAC.id";
                    $this->options["where"]["DAC.code"] = array("LIKE", $user_session->currBranchCode . "%");
                    if (DAC_SCOPE_BRANCH != $user_session->dacType && !$user_session->is_leader) { //自己可见或下属可见
                        if (in_array($this->_leaderField, $this->fields)) {
                            $acc_condition["UMA.user_id"] = $user_session->userId;
                            //自己可见，可见范围为负责人、可见人或协作，通知人是自己数据
                            if (DAC_SCOPE_DEFAULT == $user_session->dacType) {
                                $acc_condition[$alias.".".$this->_leaderField] = $user_session->userId;
                            }else{
                                //自己及下属可见，可见范围为负责人是下属或自己的数据，或者自己是可见人或协作，通知人的数据
                                $acc_condition[$alias.".".$this->_leaderField] = array("in", mergeString($user_session->visiblers, strval($user_session->userId), ","));
                            }
                        }
                        $acc_condition["_logic"] = "OR";
                        $join_condition = sprintf(" %s=UMA.instance_id AND UMA.module='%s' AND UMA.user_id=%d", $idField, $this->getAccessModule(), $user_session->userId);
                        $this->options["join"]["UMA"] = "LEFT JOIN sys_user_module_access UMA ON " . $join_condition;
                        $this->options["where"]["_complex"][] = $acc_condition;
                    }
                }else{
                    $this->options["where"]["$alias.id"] = 0; //强制返回空
                }
            }else{ //是超级用户并且指定在条件内指定DAC*code
                $code = I("post.ql-DAC*code");
                if ($code){
                    $this->options["where"]["DAC.code"] = array("LIKE", $code . "%");
                    $this->options["join"]["DAC"] = "LEFT JOIN sys_branch DAC on $dacField=DAC.id";
                }
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
            $datas = $this->getPermitDatasFromSetting($model_data, DAC_PERMIT_VALUE_VISIBLER);
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
            foreach($datas as $k=>$v){
                M("SysUserModuleAccess")->add($v);
            }
            //M("SysUserModuleAccess")->addAll($datas);
        }
    }

    //从post数据提取
    private function getPermitDatasFromPostData($model_data, $permit_field, $permit_value){
        $result = array();
        $permit_users = I("post.".$permit_field);
        $user_session =  session(USER_SESSION_KEY);
        if ($permit_users){
            if (!is_array($permit_users)){
                $permit_users = explode(",", $permit_users);
            }
            $instance_id = $model_data["id"];
            foreach ($permit_users as $key=>$permit_user){
                $permit_data[$key]["branch_id"] = $user_session->currBranchId;
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
        $condition["type"] = DAC_SETTING_TYPE_BRANCH; //类型为商户端
        $result = M("SysUserModuleSetting")->where($condition)->select();
        foreach ($result as $key=>$value){
            $result[$key]["instance_id"] = $model_data["id"];
        }
        return $result;
    }

    //更新、删除前检查是否有数据查看权限
    public function checkDataPermission($id) {
        $user_session = session(USER_SESSION_KEY);
        if ($user_session->isPlatformUser && C("ADMIN_ALLOW_UPDATE")) {
            return true;
        }
        if (!in_array($this->_dacfield, $this->fields) || !$this->isDataFilterControll()) { //没有branch_id字段就表示不需要检查
            return true;
        }
        $result = false;
        if ($user_session && $id) {
            if (is_array($id)){ //批量操作,必须都》0才返回true
                $result = true;
                foreach ($id as $item){
                    if ($this->getPermitValue($item) == 0){
                        return false;
                    }
                }
            }else{
                return ($this->getPermitValue($id) > 0);
            }
        }
        return $result;
    }

    /**获取单据权限
     * @param $id  单据ID
     * @return int
     */
    public function getPermitValue($id){
        $result = 0;
        $user_session = session(USER_SESSION_KEY);
        if (DAC_SCOPE_BRANCH == $user_session->dacType || $user_session->is_leader){
            return DAC_PERMIT_VALUE_LEADER;
        }
        //是否是负责人（负责人不会添加到SysUserModuleAccess表）
        if (in_array($this->_leaderField, $this->fields)) {
            $visiblers = mergeString($user_session->visiblers, $user_session->userId, ",");
            $leader_id = $this->where("id=$id")->getField($this->_leaderField);
            if (in_array($leader_id, explode(",", $visiblers))){
                return DAC_PERMIT_VALUE_LEADER;
            }
        }
        $condition["branch_id"] = $user_session->currBranchId;
        $condition["user_id"] = $user_session->userId;
        $condition["module"] = $this->getAccessModule();
        $condition["instance_id"] = $id;
        $data = M("SysUserModuleAccess")->where($condition)->field("permit_value")->order("permit_value desc")->find();
        if ($data){
            $result = $data["permit_value"];
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
