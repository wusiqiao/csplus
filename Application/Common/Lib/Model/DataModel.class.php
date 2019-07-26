<?php

namespace Common\Lib\Model;

use Think\Model;

class DataModel extends Model {
    /* 设置外键关联 */
    protected $_link = array();
    //创建人
    protected $_createrField = ""; //创建人字段，如果是客户，可以设置成客户对应的字段
    /* 默认权限过来字段 */
    protected $_dacfield = "branch_id";//创建人字段，如果是客户，可以设company_id
    /* 过滤条件，用于一表多用 */
    protected $_filter = array();
    protected $_auto = array(
        array('update_time', 'time', 3, 'function')
    );

    protected  function getRelativeController(){
        return $this->name;
    }
    protected function isDataFilterControll() {
        $menu_url  =  $this->getRelativeController();
        $sysMenu = getSysMenuList($menu_url);
        if (empty($sysMenu)){
            $sysMenu = M("SysMenu")->where("url='$menu_url'")->getField("is_dac");
        }
        if ($sysMenu && $sysMenu["is_dac"]) {
            return true;
        }
        return false;
    }

    //加入数据控制
    public function setDacFilter($alias = "a") {
        $this->options["alias"] = $alias;
        $user_session = session(USER_SESSION_KEY);
        if ($this->isDataFilterControll()) {
            $this->defaultDacFilter($user_session, $alias);
            if (empty($user_session) || (!$user_session->isPlatformUser && empty($user_session->currBranchCode))) {
                $this->options["where"]["$alias.id"] = 0;
            }
        }
        return $this;
    }

    /**数据权限拦截函数
     * 根据用户dac设置，增加记录过滤条件，如果是按指定部门，表结构必须有creater_id或leader_id字段，且自动填入创建人
     * 涉及字段:
     * 用户表 sys_user: 查看数据权限范围：dac_type，0：表示默认，1表示指定部门，2表示全部，dac_branchs:存放用户能查看的部门
     * @param $user_session
     * @param $alias
     */
    protected function defaultDacFilter($user_session, $alias) {
        if ($user_session) {
            $dacField = sprintf("%s.%s",$alias , $this->_dacfield);
            if (!$user_session->isPlatformUser) {
                switch($user_session->dacType){
                    case DAC_SCOPE_DEFAULT:
                    case DAC_SCOPE_BRANCH: ////所属公司或商户
                        if ($user_session->currBranchCode) {
                            $this->options["join"]["DAC"] = "INNER JOIN sys_branch DAC on $dacField=DAC.id";
                            $this->options["where"]["DAC.code"] = array("LIKE", $user_session->currBranchCode . "%");
                        }else{
                            $this->options["where"]["$alias.id"] = 0; //强制返回空
                        }
                        break;
                    case DAC_SCOPE_DEPARTMENT: ////指定部门
                        $userFilterField = $this->getCreaterField($alias);
                        if ($userFilterField) { //有创建人字段才进行过滤，否则默认按公司权限过滤
                            $condition = array();
                            $visiblers = mergeString($user_session->visiblers, $user_session->userId, ",");
                            $condition[$userFilterField] = array("in", $visiblers);
                        }else{
                            if ($user_session->currBranchCode) {
                                $this->options["join"]["DAC"] = "INNER JOIN sys_branch DAC on $dacField=DAC.id";
                                $this->options["where"]["DAC.code"] = array("LIKE", $user_session->currBranchCode . "%");
                            }else{
                                $this->options["where"]["$alias.id"] = 0; //强制返回空
                            }
                        }
                        break;
                    default:
                        $this->options["where"]["$alias.id"] = 0; //强制返回空
                        break;
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

    //获取创建人字段条件
    private function  getCreaterField($alias){
        if (in_array($this->_leaderField, $this->fields)) {
            return $alias.".".$this->_leaderField;
        }
        return "";
    }

    /**一表多用的时候使用，添加过滤条件
     * @param $options
     * @param $otherCondition
     */
    public function addOptionsFilter(&$options, $otherCondition) {
        if (is_array($otherCondition)) {
            $alias = empty($options["alias"]) ? "" : $options["alias"] . ".";
            foreach ($otherCondition as $key => $value) {
                if(stripos($key, "_") ===  0){ //开头是_的表示是系统定义的特殊标识符，比如_string, _complex
                    $options["where"][$key] = $value; 
                }else{
                    $options["where"][$alias . $key] = $value;
                }
            }
        }
    }

    //如果调用relation,则必须制定field，默认不会取主表任何字段
    public function relation($relation = true) {
        if ($relation && $this->_link) {
            return $this->getRelation();
        }
        return $this;
    }

    //重写field,使之可以多次调用
    public function field($field, $except = false) {
        if (isset($this->options['field'])) {
            $last_field = $this->options['field'];
        } else {
            $last_field = "";
        }
        parent::field($field, $except);
        $this->options['field'] = mergeString($this->options['field'], $last_field, ",");
        return $this;
    }

    private function getRelation() {
        $table_alias = isset($this->options["alias"]) ? $this->options["alias"] : "a";
        $join_conditions = "";
        $select_mappingFields = "";
        foreach ($this->_link as $key => $val) {
            $mappingClass = !empty($val['class_name']) ? $val['class_name'] : $key;            //  关联类名
            $mappingTable = parse_name($mappingClass);
            $mappingName = !empty($val['mapping_name']) ? $val['mapping_name'] : $key; // 映射名称
            $mappingFields = !empty($val['mapping_fields']) ? $val['mapping_fields'] : '*';     // 映射字段
            $mappingKey = !empty($val['mapping_key']) ? $val['mapping_key'] : $this->getPk(); // 关联键名
            $mappingFk = !empty($val['foreign_key']) ? $val['foreign_key'] : strtolower($this->name) . '_id';
            $mappingJoin = !empty($val['join_name']) ? $val['join_name'] : "INNER";
            $mappingKeys = explode(",", $mappingKey); //多主键关联
            $mappingFks = explode(",", $mappingFk);
            $join_conditions.= " $mappingJoin JOIN $mappingTable as $mappingName On ";
            for ($i = 0; $i < count($mappingKeys); $i++) {
                if (strpos($mappingFks[$i], ".") === false) {  //加点号表示Leftjoin 其他表
                    $table_mappingFk = $table_alias . "." . $mappingFks[$i];
                } else {
                    $table_mappingFk = $mappingFks[$i];
                }
                $join_conditions.= ($i == 0 ? "" : " and ") . $mappingName . "." . $mappingKeys[$i] . "=" . $table_mappingFk;
            }
            if ($mappingFields == "*.*") {
                $select_mappingFields.= $mappingName . ".*,";
            } else {
                $mappingFields_arr = explode(",", $mappingFields);
                foreach ($mappingFields_arr as $value) {
                    if (stripos($mappingName, "_") === 0) { //以"_"开头表示不作为前缀
                        $select_mappingFields.= sprintf("%s.%s as %s,", $mappingName, $value, $value);
                    } else {
                        $select_mappingFields.= sprintf("%s.%s as %s_%s,", $mappingName, $value, $mappingName, $value);
                    }
                }
            }
        }
        $select_fields = rtrim($select_mappingFields, ",");
        return $this->alias($table_alias)->join($join_conditions)->field($select_fields);
    }

    public function getDACFilter($tb_alias = "b") {
        $result = "";
        $user_session =  session(USER_SESSION_KEY);
        if ($user_session && $user_session->currBranchCode) {
            $code = $user_session->currBranchCode;
            $result = " $tb_alias.code like '$code%'";
        }
        return $result;
    }

    //更新、删除前检查是否有数据权限
    public function checkDataPermission($id) {
        $user_session = session(USER_SESSION_KEY);
        if ($user_session->isPlatformUser && C("ADMIN_ALLOW_UPDATE")) {
            return true;
        }
        if (!in_array($this->_dacfield, $this->fields) || !$this->isDataFilterControll()) { //没有branch_id字段就表示不需要检查
            return true;
        }
        $result = true;
        if ($user_session) {
            $condition["a.id"] = array("in", $id);
            $dataList = $this->alias("a")->join("left join sys_branch b on b.id=a.branch_id")->where($condition)->field("b.code")->select();
            foreach ($dataList as $data) {
                if (stripos($data["code"], $user_session->currBranchCode) === false){
                    return false;
                }
                break;
            }
        }
        return $result;
    }

    //转换成实际类型，默认返回的记录全部都是字符型的
    public function parseDataType(&$recordset) {
        foreach ($recordset as $index => $record) {
            foreach ($recordset[$index] as $key => $data) {
                if (isset($this->fields['_type'][$key])) {
                    $fieldType = strtolower($this->fields['_type'][$key]);
                    if (false !== strpos($fieldType, 'enum')) {
                        // 支持ENUM类型优先检测
                    } elseif (false === strpos($fieldType, 'bigint') && false !== strpos($fieldType, 'int')) {
                        $recordset[$index][$key] = intval($data);
                    } elseif (false !== strpos($fieldType, 'float') || false !== strpos($fieldType, 'double')) {
                        $recordset[$index][$key] = floatval($data);
                    } elseif (false !== strpos($fieldType, 'bool')) {
                        $recordset[$index][$key] = (bool) $data;
                    } elseif (false !== strpos($fieldType, 'decimal')) {
                        $recordset[$index][$key] = doubleval($data);
                    }
                }
            }
        }
    }

    protected function _options_filter(&$options) {
        if ($this->_filter) {
            $this->addOptionsFilter($options, $this->_filter);
        }
        parent::_options_filter($options);
    }

    public function getKeyNameList($select_all, $selected, $condition, $fields, $relation, $order) {
        $sql = $this->setDacFilter("a")->relation($relation)->where($condition)->field($fields)->fetchSql(true)->select();
        $union_sql = " ";   
        $orderby = "";
        if ($selected && !$select_all) { //前端传入的选择ID列表
            $ipos = stripos($sql, "where");
            if ($ipos) { //去除条件
                $union_sql = substr($sql, 0, $ipos);
            } 
            $union_sql.=  " Where (a.id in ($selected))";
            $union_sql = $union_sql ." union " . $sql;
        }else{
            $union_sql = $sql;
            $orderby = " order by $order";
        }
        $limit_condition = $select_all ? "" : " limit " . SELECT_LIMIT;
        $sql = $union_sql. $orderby . $limit_condition;
        return $this->query($sql);
    }
    
    protected function parseWhere($where){
        $query = parent::parseWhere($where);
        if ($query){
           return str_ireplace("WHERE", "", $query);
        }
        return "";
    }
    /**
     * 根据code获取id，只对有父子关系的表有效
     * @param type $code
     * @return type
     */
    public function getIdListByCode($code){
        $result = array(0);
        if ($code)
        {
            $condition["code"] = array("like", "$code%");
            $list = $this->field("id")->where($condition)->select();
            foreach ($list as $value) {
                $result[] =$value["id"];
            }
        }
        return $result;
    }

    protected function _after_insert($data, $options) {
        parent::_after_insert($data, $options);
        if (I("_COPY_DATA_ID_")){
            $data["_COPY_DATA_ID_"] = I("_COPY_DATA_ID_");
            $this->_after_copy($data, $options);
        }
    }
    
    protected function _after_copy($data, $options) {
    }


    /**根据表获取最大单据编号
     * @param string $billdate_field
     * @param string $billno_field
     * @param int $serinal_size
     * @param array $condition
     * @return string
     */
    public function getTableMaxBillNo($billdate_field = "bill_date", $billno_field = "bill_no", $serinal_size = 4, $condition = array()) {
        $condition[$billdate_field] = strtotime(date("Ymd"));
        $table = $this->getTableName();
        $max_datebill = M($table)->where($condition)->max($billno_field);
        return $this->incBillNo($max_datebill, $serinal_size);
    }

    /**根据模块获取最大单据编号--存在一个表存不同类型单据的情况，所以和getMaxBillNoByTable会有不同
     * @param string $billdate_field
     * @param string $billno_field
     * @param int $serinal_size
     * @param array $condition
     * @return string
     */
    public function getMaxBillNo($billdate_field = "bill_date", $billno_field = "bill_no", $serinal_size = 4, $condition = array()) {
        $condition[$billdate_field] = strtotime(date("Ymd"));
        $max_datebill = $this->where($condition)->max($billno_field);
        return $this->incBillNo($max_datebill, $serinal_size);
    }

    public function getTableMaxBillNoByUserBranch($billdate_field = "bill_date", $billno_field = "bill_no", $serinal_size = 4) {
        $user_session = session(USER_SESSION_KEY);
        $condition["branch_id"] = $user_session->currBranchId;
        return $this->getTableMaxBillNo($billdate_field, $billno_field, $serinal_size, $condition);
    }

    public function getMaxBillNoByUserBranch($billdate_field = "bill_date", $billno_field = "bill_no", $serinal_size = 4) {
        $user_session = session(USER_SESSION_KEY);
        $condition["branch_id"] = $user_session->currBranchId;
        return $this->getMaxBillNo($billdate_field, $billno_field, $serinal_size, $condition);
    }

    public function incBillNo($max_datebill, $serinal_size = 4){
        if (empty($max_datebill)) {
            $result = date("Ymd") . str_pad("1", $serinal_size, "0", STR_PAD_LEFT);
        } else {
            $last_val = substr($max_datebill, -$serinal_size, $serinal_size);
            $result = date("Ymd") . str_pad(intval($last_val) + 1, $serinal_size, "0", STR_PAD_LEFT);
        }
        return $result;
    }

    /**
     * 获取最大编号
     * @param type $prefix  //前缀
     * @param type $no_field //编号对应字段
     * @param type $serinal_size //编号长度
     * @param type $condition //其他条件
     * @return string
     */
    public function getMaxNo($prefix = "", $no_field = "no", $serinal_size = 4, $condition = array()) {
        $max_no = $this->where($condition)->max($no_field);
        if (empty($max_no)) {
            $result = $prefix . str_pad("1", $serinal_size, "0", STR_PAD_LEFT);
        } else {
            $last_val = substr($max_no, -$serinal_size, $serinal_size);
            $result = $prefix . str_pad(intval($last_val) + 1, $serinal_size, "0", STR_PAD_LEFT);
        }
        return $result;
    }

    public function getMaxNoByUserBranch($prefix = "", $no_field = "no", $serinal_size = 4) {
        $user_session = session(USER_SESSION_KEY);
        $condition["branch_id"] = $user_session->currBranchId;
        return $this->getMaxNo($prefix, $no_field, $serinal_size, $condition);
    }

    //重新排序
    public function initialSortField($branch_id){
        $condition = array();
        if (in_array("branch_id", $this->fields) && $branch_id) {
            $condition["branch_id"] = $branch_id;
        }
        $sort_data = $this->field("id,sort")->where($condition)->select();
        if ($sort_data && empty($sort_data[0]["sort"])) { //没有初始化过
            $update_kv = "";
            foreach ($sort_data as $key => $value) {
                $start = $key + 10;
                $update_kv .= sprintf(" when %d then %d", $value["id"], $start);
            }
            $sql = "update " . $this->getTableName() . " set sort=case id $update_kv end";
            if ($condition["branch_id"]){
                $sql = $sql." where branch_id=$branch_id";
            }
            $this->execute($sql);
        }

    }

}
