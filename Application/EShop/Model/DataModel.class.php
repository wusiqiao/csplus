<?php

namespace EShop\Model;

use Think\Model;



class DataModel extends Model {
    protected $_dacfield = "branch_id";//创建人字段，如果是客户，可以设company_id
    protected $_createrField = "creater_id";//创建人字段，在子类设置
    //添加 lynn 10.30
    protected function handlerAuthorityDistribution($user_session,$alias)
    {
        if ($user_session['user_type'] == USER_TYPE_COMPANY_MANAGER && $user_session['is_leader'] == 1) {
            //商户管理员
            $this->options['where'][$alias.'.branch_id'] = $user_session['branch_id'];
        } else {
            if ($user_session['user_type'] == USER_TYPE_COMPANY_MANAGER && $user_session['is_leader'] = 0){
                $probes_filter = $this->handlerAuthorityFieldProbe('customer');
            } else {
                $probes_filter = $this->handlerAuthorityFieldProbe('user');
            }
            if ($probes_filter) {
                $where = [];
                if ($probes_filter) {
                    foreach($probes_filter as $k => $filter){
                        $target_ids = $user_session['userDataAccess'][$k];
                        if ($k == '_users') {
                            $target_ids[] = $user_session['user_id'];
                        }
                        if ($target_ids) {
                            foreach ($probes_filter[$k] as $key =>$value) {
                                $where[$alias.'.'.$value] = array('in',$target_ids);
                            }
                        }
                    }
                }
                if ($where !== []) {
                    $where['_logic'] = 'or';
                    $this->options["where"]["_complex"] = $where;
                }
            }
        }
    }
    //判断字段是否存在,过滤,返回字段存在的数组
    protected function handlerAuthorityFieldProbe($type)
    {
        $probes = [
            'customer' =>
                [
                    ['field' => 'user_id',        'target' =>'_users'],
                    ['field' => 'user_branch_id', 'target' =>'_branchs'],
                    ['field' => 'service_id',     'target' =>'_users'],
                    ['field' => 'creator_id',     'target' =>'_users'],
                ],
            'user' =>
                [
                    ['field' => 'user_branch_id',  'target' =>'_branchs'],
                    ['field' => 'user_id',  'target' =>'_users']
                ]
        ];
        $fields = $this->getDbFields();
        if (is_array($fields) && $probes[$type]) {
            $probes_has = [];
            foreach ($probes[$type] as $key => $value) {
                if (in_array($value['field'],$fields)) {
                    $probes_has[$value['target']][] = $value['field'];
                }
            }
            return $probes_has !== [] ? $probes_has : false;
        } else {
            return false;
        }
    }

    //加入数据控制
    public function setDacFilter($alias = "a") {
        $this->options["alias"] = $alias;
        $this->defaultDacFilter($alias);
        return $this;
    }


    protected function getUserAccessDataFromSession(){
        if ($user_id = session("user_id")){
            $user_dac_data = session("_user_data_access_$user_id");
            if(empty($user_dac_data)){
                $user_dac_data = M("SysUser")->where("id=$user_id")->field("id,dac_type,branch_id,dac_branchs,dac_users,is_leader")->find();
                $dacType = intval($user_dac_data["dac_type"]);
                switch ($dacType){
                    case DAC_SCOPE_DEFAULT:
                        $user_dac_data["visiblers"] = [$user_id];
                        break;
                    case DAC_SCOPE_DEPARTMENT:
                        $condition["branch_id"] = array("in", strval($user_dac_data["dac_branchs"]));
                        $visiblers = M("SysUserBranch")->where($condition)->getField("user_id", true);
                        $visiblers[] = $user_id;
                        $user_dac_data["visiblers"] = $visiblers;
                        break;
                    case DAC_SCOPE_USER:
                        $visiblers = explode(",", $user_dac_data["dac_users"]);
                        $visiblers[] = $user_id;
                        $user_dac_data["visiblers"] = $visiblers;
                        break;
                }
                session("_user_data_access_$user_id", $user_dac_data);
            }
            return $user_dac_data;
        }
    }
    /**数据权限拦截函数
     * 根据用户dac设置，增加记录过滤条件，如果是按指定部门，表结构必须有creater_id或leader_id字段，且自动填入创建人
     * 涉及字段:
     * 用户表 sys_user: 查看数据权限范围：dac_type，0：表示默认，1表示指定部门，2表示全部，dac_branchs:存放用户能查看的部门
     * @param $user_session
     * @param $alias
     */
    protected function defaultDacFilter($alias) {
        if ($user_id = session("user_id")) {
            $user_dac_data = $this->getUserAccessDataFromSession();
            $dacField = sprintf("%s.%s",$alias , $this->_dacfield);
            $createrField = $this->getCreaterField($alias);
            $this->options["where"][$dacField] = $user_dac_data["branch_id"];
            if ($createrField && $user_dac_data["visiblers"]) { //有创建人字段才进行过滤，否则默认按公司权限过滤
                $this->options["where"][$createrField] = array("in", $user_dac_data["visiblers"]);
            }
        }
    }

        //获取创建人字段条件
    private function  getCreaterField($alias){
        if (in_array($this->_createrField, $this->fields)) {
            return $alias.".".$this->_createrField;
        }
        return "";
    }
}
