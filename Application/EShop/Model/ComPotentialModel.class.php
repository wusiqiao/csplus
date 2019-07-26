<?php

namespace EShop\Model;
use Think\Model;

class ComPotentialModel extends DataModel {
    protected $tableName = 'sys_user';
    /*免费咨询发送客户通知
     * type=1 提交咨询发送给客户
     * type=2 商家回复咨询发送给客户
     * type=3 客户留言发送给商家
     * type=4 商家回留言
     * */
    public function sendWXConsult($tool,$type=1,$attach_group = null){
        $templateId = getWxTemplateIdByStandardId('OPENTM202109783');
        if(!$templateId){
            return false;
        }
        if($type == 1){
            $openid = M('SysUser')->where("id=".$_SESSION['user_id'])->getField('openid',true);
            $data['first'] = '您的咨询信息已提交成功';
            $data["reply"] = "商家将在24小时内与您联系";
            $data['remark'] = "点击进入首页";
            $data['url'] =  WEB_ROOT . '/Index';
        }elseif($type == 2){
            $openid = M('SysUser')->where("id=".$tool['user_id'])->getField('openid',true);
            $data['first'] = '您的咨询信息收到一条新消息';
            $data["reply"] = $tool["reply"];
            $data['remark'] = "点击查看详情";
            $data['url'] =  WEB_ROOT . '/Liuyan.html';
        }elseif($type == 3){
            $condition["branch_id"] = getBrowseBranchId();
            $condition["is_leader"] = 1;
            $openid = M('SysUser')->where($condition)->getField('openid',true);
            $data['first'] = '您收到一条留言信息';
            $data["reply"] = $tool["reply"];
            $data['remark'] = "点击查看详情";
            $data['url'] =  WEB_ROOT . '/Liuyan.html';
        }elseif($type == 4){
            $openid = M('SysUser')->where("id=".$tool['user_id'])->getField('openid',true);
            $data['first'] = '您收到一条留言信息';
            $data["reply"] = $tool["reply"];
            $data['remark'] = "点击查看详情";
            // $data['url'] =  WEB_ROOT . '/Liuyan.html';
            $data['url'] =  str_replace('shop','shop'.getBrowseBranchId(),SHOP_ROOT)."/Liuyan.html?attach_group=".$attach_group;
        }
        if(!is_null($openid)){
            $data['consultant'] = $tool['consultant'];
            $data['openid'] = $openid;
                        var_dump($data);
            return $this->HandlerWXSendConsultData($data,$templateId);
        }

    }
    protected function HandlerWXSendConsultData($data,$templateId){
        if ($data) {
            $message = array();
            $body = array();
            $message["template_id"] = $templateId;
            $message["url"] = $data['url'];
            $body["first"]["value"]    = $data['first'];
            $body["keyword1"]["value"] = $data['consultant']; //咨询名称
            $body["keyword2"]["value"] = $data["reply"]; //回复信息
            $body["remark"]["value"] = $data["remark"];//strlen(trim($data["remark"])) > 0 ? $data["remark"] : getMessageRemark();
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
            $message['$templateId'];
            return $message;
        }
    }
}