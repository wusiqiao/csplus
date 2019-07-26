<?php
namespace EShop\Model;

use Think\Model;
class ComSundryOrderModel extends ModelBase{


    public function sendBorrowApplyWXMessage($order_id,$record_id,$type = 1){
        $templateId = getWxTemplateId('noticeBorrowApply');
        $data = array();
        // transferApply
        if(!$templateId){
            return false;
        }
        $comSundryOrder = D("ComSundryOrder")
        ->alias('a')
        ->join('LEFT JOIN sys_user b ON b.id = a.borrower')
        ->field('b.name as borrower_name,a.*')
        ->where(['a.id'=>$order_id])
        ->find();

        $comSundryOrder['customer_cc_recipient'] = explode(",",$comSundryOrder['customer_cc_recipient']);
        $comSundryOrder['company_cc_recipient'] = explode(",",$comSundryOrder['company_cc_recipient']);
        $user_ids = array_merge(
            $comSundryOrder['customer_cc_recipient'],
            $comSundryOrder['company_cc_recipient']
        );
        array_push($user_ids,$comSundryOrder['lender']);

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
        if ($type==2) {
        	//同意
	        $data['url'] = "";
	        $data['first'] = '你好，你的物品借出申请已通过。';
	        $data["keyword1"] = $comSundryOrder['borrower_name'];
	        $data["keyword2"] = $record['sundry_names'];
	        $data["keyword3"] = $comSundryOrder['remarks'];
	        $data["remark"] = "";
        }elseif ($type==3) {
        	//拒绝
	        $data['url'] = "";
	        $data['first'] = '你好，你的物品借出申请被拒绝。';
	        $data["keyword1"] = $comSundryOrder['borrower_name'];
	        $data["keyword2"] = $record['sundry_names'];
	        $data["keyword3"] = $comSundryOrder['remarks'];
	        $data["remark"] = "";
        }else{
        	//申请
	        $data['url'] = "";
	        $data['first'] = '你好，你收到了一条物品借出申请。';
	        $data["keyword1"] = $comSundryOrder['borrower_name'];
	        $data["keyword2"] = $record['sundry_names'];
	        $data["keyword3"] = $comSundryOrder['remarks'];
	        $data["remark"] = "";
        }

        return $this->HandlerWXSendData($data,$templateId);
    }


    public function sendTransferApplyWXMessage($order_id,$record_id){
        $templateId = getWxTemplateId('noticeTransferApply');
        $data = array();
        if(!$templateId){
            return false;
        }
        $comSundryTransfer = D("ComSundryTransfer")
        ->alias('a')
        ->join('LEFT JOIN sys_user b ON b.id = a.borrower')
        ->field('b.name as borrower_name,a.*')
        ->where(['a.id'=>$order_id])->find();
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

        $lender = D("SysUser")->where(['id'=>$comSundryTransfer['lender']])->find();
        $data["first"] = $lender['name']."已转交以下物品：".$record['sundry_names'].",请确认";
        $data["keyword1"] = $comSundryTransfer['borrower_name'];
        $data["keyword2"] = $record['date'];
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