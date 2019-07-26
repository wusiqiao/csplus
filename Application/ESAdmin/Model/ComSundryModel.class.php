<?php

namespace ESAdmin\Model;
use Common\Lib\Model\DataModel;

class ComSundryModel extends DataModel
{
    protected $_link = array(
        "SysUser" => array(
            "join_name" => 'LEFT',
            'class_name' => 'SysUser',
            'foreign_key' => 'user_id',
            'mapping_name' => 'user',
            'mapping_fields' => 'name',
            "mapping_key" => 'id'
        ),
        "Company" => array(
            "join_name" => 'LEFT',
            'class_name' => 'SysBranch',
            'foreign_key' => 'customer_company',
            'mapping_name' => 'company',
            'mapping_fields' => 'name',
            "mapping_key" => 'id'
        )
    );
    protected function _before_insert(&$data, $options)
    {
        parent::_before_insert($data, $options);
        $data['created_at'] = time();
        $data['updated_at'] = time();
        // $data['status']=2;
        // unset($data['user_id']);
        unset($data['last_return_date']);
        unset($data['last_borrow_date']);
    }
    protected function _before_update(&$data, $options)
    {
        parent::_before_update($data, $options);
        $data['updated_at'] = time();
        unset($data['status']);
        unset($data['user_id']);
        unset($data['last_return_date']);
        unset($data['last_borrow_date']);
    }

    protected function _after_insert($data, $options) {
        if ($data['status']==0) {
            $ComSundryOrder_data = array();
            
            $model = D('ComSundryOrder');
            $ComSundryOrder_data['customer_company'] = $data['customer_company'];
            $condition["branch_id"] = $data['branch_id'];
            $ComSundryOrder_data['no'] = D(CONTROLLER_NAME)->getMaxBillNo("borrow_date","no",4,$condition);
            $ComSundryOrder_data['type'] = 0;
            $ComSundryOrder_data['status'] = 3;
            $ComSundryOrder_data['branch_id'] = $data['branch_id'];
            $ComSundryOrder_data['lender'] = I("post.lender");
            $ComSundryOrder_data['borrower'] = $data['user_id'];
            $ComSundryOrder_data['borrow_date'] = time();
            $ComSundryOrder_data['expected_return_date'] = time();
            $ComSundryOrder_data['created_at'] = time();
            $ComSundryOrder_data['updated_at'] = time();
            try {
                $model->startTrans();
                $last_id = $model->add($ComSundryOrder_data, array("callback"=>true));
                $model->commit();
            } catch (Think\Exception $ex) {
                $model->rollback();
                $last_id = false;
            }

            if($last_id){
                $model = D("ComSundryItem");

                $sundry_data = array(
                    "parent_id" => $last_id,
                    "sundry_id" => $data["id"],
                    "branch_id" => $data['branch_id'],
                    "status" => 0,
                    "created_at"=> time(),
                    "updated_at"=> time()
                );

                try {
                    $model->startTrans();
                    $model->add($sundry_data);
                    $model->commit();
                } catch (Think\Exception $ex) {
                    $model->rollback();
                    $last_id = false;
                }

                $record_data = array(
                    "parent_id" => $last_id,
                    "type" => 3,
                    "sundry_names" => $data['name'],
                    "branch_id" => $data['branch_id'],
                    "user_id"=>I("post.lender"),
                    "date"=> time(),
                    "created_at"=> time()
                );
                try {
                    $model->startTrans();
                    $record_id = D("ComSundryRecord")->add($record_data);
                    $model->commit();
                } catch (Think\Exception $ex) {
                    $model->rollback();
                    $last_id = false;
                }
            }
        }
        parent::_after_insert($data, $options);
    }
}