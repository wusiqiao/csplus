<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class WxBranchTemplateModel extends DataModel {


    /**已经用户已经存在的模板
     * @return mixed
     */
    public function getBranchExistsTemplateMsgKeys($branch_id){
        $templates = $this->alias("a")
            ->join("inner join wx_template_message b on a.parent_id=b.id")
            ->field("b.msg_key")->where("a.branch_id=$branch_id")->select();
        foreach ($templates as $k=>$template){
            $templateKeys[$template["msg_key"]]=1;
        }
        return $templateKeys;
    }

    //未注册的模板,系统已启用的模板，且客户未导入
    public function getUnRegisterTemplate($branch_id){
        $sql= "select a.standard_id,a.msg_key from wx_template_message a 
              left join wx_branch_template b on a.id=b.parent_id and b.branch_id=$branch_id
              where a.is_system_tpl=1 and a.is_valid=1 and isnull(b.id)";
        return $this->query($sql);
    }


    public function addTemplate($branch_id){
        $titles = I("post.title");
        $contents = I("post.content");
        $examples = I("post.example");
        $templateids = I("post.template_id");
        $item_selecteds = I("post.item_selected"); //选中的项
        $brancTtemplateKeys = $this->getBranchExistsTemplateMsgKeys($branch_id);//公司已经存在的模板消息
        $systemTemplateKeys = D("WxTemplateMessage")->getSystemExistsTemplateMsgKeys(); //所有模板
        if (count($titles) == count($contents) && count($examples) == count($titles)){
            $datalist = array();
            $detaillist = array();
            foreach($item_selecteds as $key){
                $title = $titles[$key];
                $msg_key = getTemplateIdentKey($contents[$key]);
                if ($brancTtemplateKeys[$msg_key]) {
                   return buildMessage("模板消息【" . $title . "】已经存在",1);
                }
                if ($contents[$key]) {
                    $detail = array();
                    if ($systemTemplateKeys[$msg_key]){ //系统已经存在的
                        $detail["parent_id"] = $systemTemplateKeys[$msg_key];
                        $detail["template_id"] = $templateids[$key];
                        $detail["branch_id"] = $branch_id;
                        $detaillist[] = $detail;
                    }else{
                        $data["title"] = $title;
                        $data["content"] = $contents[$key];
                        $data["example"] = $examples[$key];
                        $data["msg_key"] = $msg_key;
                        $data["update_time"] = time();
                        $data["branch_id"] = $branch_id;
                        $data["template_id"] = $templateids[$key];
                        $datalist[] = $data;
                    }
                }
            }
            $this->startTrans();
            try{
                foreach ($datalist as $data){
                    $last_id = M("WxTemplateMessage")->add($data);
                    if ($last_id){
                        $detail["parent_id"] = $last_id;
                        $detail["template_id"] = $data["template_id"];
                        $detail["branch_id"] = $branch_id;
                        $detaillist[] = $detail;
                    }
                }
                foreach ($detaillist as $detail){
                    $this->add($detail);
                }
                $this->commit();
                return buildMessage("保存成功！");
            }catch (Exception $exception){
                $this->rollback();
                return buildMessage("保存错误！",1);
            }
        }else{
            return buildMessage("数据有错误！",1);
        }
    }

    protected function _before_delete($options)
    {
        $wxInstance = getWeChatInstance();
        $tpls = $this->field("template_id")->where(array("id" => $options["where"]["id"]))->select();
        foreach ($tpls as $tpl){
            $wxInstance->deleteTemplateMessage($tpl["template_id"]);
        }
        parent::_before_delete($options);
    }

    //
    public function getUserLastNoticeCount($user_id,$timer,$target = 'day')
    {
        $where['wnru.user_id'] = $user_id;
        if ($target == 'timestamp') {
            $target_time = $timer;
        } else {
            $target_time = time() - $timer * 86400;
        }
        $where['wntl.send_at'] = array('gt',$target_time);
        $count = M('wx_notice_relation_user')
            ->alias('wnru')
            ->field('wtm.title,wntl.send_at')
            ->join('inner join wx_notice_template_library wntl on wntl.id = wnru.notice_id')
            ->where($where)
            ->count();
        return $count;
    }
}