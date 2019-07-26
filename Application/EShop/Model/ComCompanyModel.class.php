<?php

namespace EShop\Model;


class ComCompanyModel extends ComplexDataModel {
    protected $tableName = 'sys_branch';

    protected $_createrField = "creater_id"; //创建人字段，如果是客户，可以设置成客户对应的字段
    protected $_leaderField = "leader_id";//负责人字段
    protected $_visiblersField = "visiblers"; //可见人字段
    protected $_collaboratorsField = "collaborators"; //协作人，如果原有此字段，就设置，没有就不需要

    protected function _options_filter(&$options) {
        $this->addOptionsFilter($options, array("type" => ORG_COMPANY));
        parent::_options_filter($options);
    }

    private function addOptionsFilter(&$options, $otherCondition) {
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

    public function getCompanyTag(){
        $condition['branch_id'] = getBrowseBranchId();
        $condition['type'] = 0;
        $tag["type"] = M("ComCompanyTag")->where($condition)->select();
        $condition['type'] = 1;
        $tag["origin"] = M("ComCompanyTag")->where($condition)->select();
        return $tag;
    }
}
