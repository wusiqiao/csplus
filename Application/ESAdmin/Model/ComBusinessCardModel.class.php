<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/26
 * Time: 9:23
 */

namespace ESAdmin\Model;


use ESAdmin\Controller\ComBusinessCardController;

class ComBusinessCardModel extends SysUserModel
{
    protected $tableName = 'sys_user';

    protected function _options_filter(&$options) {
        $this->addOptionsFilter($options, array("user_type" => USER_TYPE_BUSINESS));
        parent::_options_filter($options);
    }

    public function __construct($name = '', $tablePrefix = '', $connection = '') {
        parent::__construct($name, $tablePrefix, $connection);
        $this->_auto[] = array("user_type", USER_TYPE_BUSINESS);
    }

    protected function _after_insert($data, $options)
    {
        return true;
    }
    protected function _after_update($data, $options)
    {
        return true;
    }

    /*删除前检查*/
    protected function _before_delete($options) {
        $condition["inviter_id"] = $options["where"]["id"];
        $count = M("DistributionRelation")->where($condition)->count();
        if ($count > 0){
            E("已经有下级用户!");
            return false;
        }
        return true;
    }
}