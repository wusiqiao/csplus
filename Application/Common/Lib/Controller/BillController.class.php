<?php

namespace Common\Lib\Controller;

use Common\Lib\Controller\DataController;


class BillController extends DataController {

    protected function _before_add(&$data) {
        parent::_before_add($data);
        $data["bill_date"] = time();
    }

    protected function getBillNo($rule_param_name, $data) {
        $rule = D("SysParam")->getParamValue($rule_param_name);
        $billno = getBillNoByRule($rule, $data);
        return $billno;
    }
    
    protected  function getRandBillNo(){
       return  date("YmdHis"). str_pad(rand(0, 1000), 4, "0", STR_PAD_LEFT);
    }
    
     protected  function getOrderBillNo($billdate_field = "bill_date", $billno_field = "bill_no", $serinal_size = 4){
        return $this->getMaxBillNo($billdate_field, $billno_field, $serinal_size);
    }

    //单据选择不做异步查询，因为没有关键字,同时返回10条记录，再包括当前记录
    public function keyNoListAction($selected = "") {
        $condition = $this->getChosenSearchCondition($selected); 
        $list = D(CONTROLLER_NAME)->alias("a")->field("id,bill_no")->where($condition)->limit(SELECT_LIMIT)->order("a.id desc")->select();
        $this->responseJSON($list);
    }

}
