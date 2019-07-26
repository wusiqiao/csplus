<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class  WxTemplateMessageController extends DataController {

    public function showImportViewAction(){
        $wxInstance = $this->getWxInstance();
        if ($wxInstance && $list = $wxInstance->getTemplateMessageList()){
            $templatekeys = D(CONTROLLER_NAME)->getSystemExistsTemplateMsgKeys();
            $template_list = array();
            foreach ($list["template_list"] as $key=>$v){
                if ($v["title"] != '订阅模板消息' && $v["title"] != '服务进度通知' && $v["title"] != '客户请求通知'){
                    $msg_key = getTemplateIdentKey($v["content"]); //排除重复
                    if (empty($templatekeys[$msg_key])) {
                        $template_list[] = &$list["template_list"][$key];
                    }
                }
            }
            $this->list = $template_list;
        }
        $this->display("import");
    }

    private function getWxInstance(){
        $branch_id = 26;//系统默认以财穗+为系统模板
        $wx_config =  M("WxConfig")->field("appid,appsecret")->where("branch_id=$branch_id")->find();
        if ($wx_config) {
            return getWeChatInstance($wx_config);
        }
        return null;
    }

    public function importAction(){
        $result = D(CONTROLLER_NAME)->addSystemTemplate();
        $this->responseJSON($result);
    }

    public function setValidFieldAction($id, $is_valid){
        if (!D(CONTROLLER_NAME)->checkDataPermission($id)) {
            $this->responseJSON(buildMessage("修改失败：您没有权限更新此记录！", 1));
        }
        D(CONTROLLER_NAME)->where("id=$id")->setField("is_valid", $is_valid);
        $this->responseJSON(buildMessage("更新成功"));
    }
}