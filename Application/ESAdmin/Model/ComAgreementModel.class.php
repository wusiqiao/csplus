<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/18
 * Time: 10:08
 */

namespace ESAdmin\Model;
use Common\Lib\Model\DataModel;

class ComAgreementModel extends DataModel
{
    protected $tableName = 'wrk_agreement';
    protected $_dacfield = "company_id";

    protected $_link = array(
        "WrkInvoicePlan" => array(
            "join_name" => "INNER",
            'class_name' => "WrkInvoicePlan",
            'foreign_key' => 'id',
            'mapping_name' => 'wip',
            'mapping_fields' => 'id,creater_id,is_sendWX',
            "mapping_key" => "agreement_id"
        )
    );

    public function sendInvoiceApplyMessage($agreement_id,$plan_money){
        $templateId = getWxTemplateIdByStandardId("OPENTM408909668");
        if(!$templateId){
            return false;
        }
        $openid = [];
        $agreement = M("WrkAgreement")->where("id = $agreement_id")->field("customer_leader_id,company_id,agreement_sn,visiblers,leader_id")->find();
        if($agreement['customer_leader_id'] != 0 && $agreement['customer_leader_id'] != ""){
            $tmp = M("SysUser")->where("id = ".$agreement['customer_leader_id'])->field("openid,name")->find();
            $customer_name = $tmp['name'];
        }else{
            $tmp  = M("SysBranch")
                ->alias("a")
                ->join("sys_user as b on a.leader_id = b.id")
                ->where("a.id = ".$agreement['company_id'])
                ->field("b.openid,b.name")->find();
            $customer_name = $tmp['name'];
        }
        $result = M("WrkInvoicePlan")->where("agreement_id = $agreement_id")->field("leader_id,visiblers")->find();
        if($result['leader_id']){
            $openid[] = M("SysUser")->where("id = ".$result['leader_id'])->getField("openid");
        }
        if($result['visiblers']){
            $visiblers = explode(",",$result['visiblers']);
            foreach ($visiblers as $v) {
                $openid[] = M("SysUser")->where("id = $v")->getField("openid");
            }
        }
        $data['first'] = "您好，您的合同收到一笔发票申请";
        $data['remark'] = "请注意查看";
        $data['url'] = "";
        $data['invoice_id'] = $agreement['agreement_sn']; //合同编号
        $data['invoice_day'] = $customer_name; // 客户姓名
        $data['invoice_sum'] = "申请发票金额：".$plan_money; //变更内容
        $data['openid'] = array_unique($openid);
        if($data['openid'] != ""){
            D("WrkInvoicePlan")->handlerWXSendInvoiceData($data,$templateId);
        }
    }
}