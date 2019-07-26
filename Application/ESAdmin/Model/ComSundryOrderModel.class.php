<?php

namespace ESAdmin\Model;
use Common\Lib\Model\DataModel;

class ComSundryOrderModel extends DataModel
{
    protected $_link = array(
        "Returner" => array(
            "join_name" => 'LEFT',
            'class_name' => 'SysUser',
            'foreign_key' => 'returner',
            'mapping_name' => 'returner',
            'mapping_fields' => 'name',
            "mapping_key" => 'id'
        ),
        "Lender" => array(
            "join_name" => 'LEFT',
            'class_name' => 'SysUser',
            'foreign_key' => 'lender',
            'mapping_name' => 'lender',
            'mapping_fields' => 'name',
            "mapping_key" => 'id'
        ),
        "Borrower" => array(
            "join_name" => 'LEFT',
            'class_name' => 'SysUser',
            'foreign_key' => 'borrower',
            'mapping_name' => 'borrower',
            'mapping_fields' => 'name',
            "mapping_key" => 'id'
        )
    );
    protected function _before_insert(&$data, $options)
    {
        parent::_before_insert($data, $options);
        $data['created_at'] = time();
        $data['updated_at'] = time();
    }
    protected function _before_update(&$data, $options)
    {
        parent::_before_update($data, $options);
        $data['updated_at'] = time();
    }

    protected function _options_filter(&$options) {
        $this->addOptionsFilter($options, array("type" =>0));
        parent::_options_filter($options);
    }


    private function insert_items($data) {
        $sundry_ids = I("post.sundry_ids");
        $sundry_datas = array();
        foreach ($sundry_ids as $key => $value) {
            $sundry_datas[] = array(
                "parent_id" => $data["id"],
                "sundry_id" => $sundry_ids[$key],
                "status" => 2,
                "branch_id" =>$data["branch_id"],
                "created_at"=> time()
            );
        }
        D("ComSundryItem")->addAll($sundry_datas, null, true);
    }
    protected function _after_insert($data, $options) {
        if ($data['type']==0) {
            $this->insert_items($data);
        }
        parent::_after_insert($data, $options);
    }
    protected function _after_update($data, $options) {
        if ($data['type']==0) {
            M("ComSundryItem")->where(array("parent_id" => $data["id"]))->delete();
            $this->insert_items($data);       
        }
    }
    protected function _after_delete($data, $options) {
        if ($data['type']==0) {
            M("ComSundryItem")->where(array("parent_id" => $data["id"]))->delete();
        }
    }

    public function sendBorrowApplyWXMessage($order_id,$record_id){
        $templateId = getWxTemplateId('noticeBorrowApply');
        $data = array();
        // transferApply
        if(!$templateId){
            return false;
        }
        $comSundryOrder = D("ComSundryOrder")->setDacFilter("a")->relation(true)->field("a.*")->where(['a.id'=>$order_id])->find();

        $comSundryOrder['customer_cc_recipient'] = explode(",",$comSundryOrder['customer_cc_recipient']);
        $comSundryOrder['company_cc_recipient'] = explode(",",$comSundryOrder['company_cc_recipient']);
        $user_ids = array_merge(
            $comSundryOrder['customer_cc_recipient'],
            $comSundryOrder['company_cc_recipient']
        );
        array_push($user_ids,$comSundryOrder['lender']);
        // return $user_ids;
        $openid = array();
        if (!empty($user_ids)) {
            $conditon['id'] = array("in", $user_ids);
            $user = M('SysUser')->where($conditon)->select();    
            foreach ($user as $k => $v) {
                array_push($openid, $v['openid']);
            }
        }

        $conditon['id'] = $record_id;
        $record = M('ComSundryRecord')->where($conditon)->find();
        //通知用户
        $data['openid'] = $openid;
        $data['url'] = "";
        $data['first'] = '你好，你收到了一条物品出借申请。';
        $data["keyword1"] = $comSundryOrder['borrower_name'];
        $data["keyword2"] = $record['sundry_names'];
        $data["keyword3"] = $comSundryOrder['remarks'];
        $data["remark"] = "";
        // $record;
        return $this->HandlerWXSendData($data,$templateId);
    }


    public function sendTransferApplyWXMessage($order_id,$record_id){
        $templateId = getWxTemplateId('noticeTransferApply');
        $data = array();
        if(!$templateId){
            return false;
        }
        $comSundryTransfer = D("ComSundryTransfer")->setDacFilter("a")->relation(true)->field("a.*")->where(['a.id'=>$order_id])->find();
        $comSundryTransfer['company_cc_recipient'] = explode(",",$comSundryTransfer['company_cc_recipient']);
        $user_ids = $comSundryTransfer['company_cc_recipient'];
        array_push($user_ids,$comSundryTransfer['borrower']);

        $openid = array();
        if (!empty($user_ids)) {
            $conditon['id'] = array("in", $user_ids);
            $user = M('SysUser')->where($conditon)->select();    
            foreach ($user as $k => $v) {
                array_push($openid, $v['openid']);
            }
        }

        $conditon['id'] = $record_id;
        $record = M('ComSundryRecord')->where($conditon)->find();
        //通知用户
        $data['openid'] = $openid;
        $data['url'] = "";

        $data["first"] = $comSundryTransfer['lender_name']."已转交以下物品：".$record['sundry_names'].",请确认";
        $data["keyword1"] = $comSundryTransfer['borrower_name'];
        $data["keyword2"] = date('Y/m/d',$record['date']);
        // $record['date'];
        $data["remark"] = $comSundryTransfer['remarks'];
        return $this->HandlerWXSendData($data,$templateId);
    }

    public function sendReturnWXMessage($order_id,$record_id){
        $templateId = getWxTemplateId('noticeReturn');
        $data = array();
        if(!$templateId){
            return false;
        }
        $comSundryOrder = M("ComSundryOrder")->where(['id'=>$order_id])->find();

        $conditon['id'] = $record_id;
        $record = M('ComSundryRecord')->where($conditon)->find();
        //通知用户


        $conditon['id'] = $comSundryOrder['lender'];
        $user = M('SysUser')->where($conditon)->find();

        $data['openid'] = $user['openid'];
        $data['url'] = "";

        $data["first"] = "您的物品".$record['sundry_names']."即将归还，请确认";
        $data["keyword1"] = date('Y/m/d',$comSundryOrder['return_date']);
        // $data["keyword2"] = $record['date'];
        $data["remark"] = $comSundryOrder['remarks'];
        return $this->HandlerWXSendData($data,$templateId);
    }

    protected function HandlerWXSendData($data,$templateId){
        if ($data) {
            $message = array();
            $body = array();
            $message["template_id"] = $templateId;
            $message["url"] = $data['url'];
            $body["first"]["value"]    = $data['first'];
            if (isset($data["keyword1"])) {
                $body["keyword1"]["value"] = $data["keyword1"];
            }
            if (isset($data["keyword2"])) {
                $body["keyword2"]["value"] = $data["keyword2"];
            }
            if (isset($data["keyword3"])) {
                $body["keyword3"]["value"] = $data["keyword3"];
            }
            if (isset($data["keyword4"])) {
                $body["keyword4"]["value"] = $data["keyword4"];
            }
            $body["remark"]["value"] = $data["remark"];
            $message["body"] = $body;
            if(is_array($data['openid'])){
                foreach ($data['openid'] as $val){
                    $message["openid"] = $val;
                    send_wx_message($message);
                }
            }else{
                $message["openid"] = $data['openid'];
                send_wx_message($message);
            }
            return $message;
        }
    }

}