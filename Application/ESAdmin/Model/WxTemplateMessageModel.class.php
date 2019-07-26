<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class WxTemplateMessageModel extends DataModel {


    /**已经存在的模板
     * @return mixed
     */
    public function getSystemExistsTemplateMsgKeys(){
        $templates = M("WxTemplateMessage")->field("msg_key,id")->select();
        foreach ($templates as $k=>$template){
            $templateKeys[$template["msg_key"]]=$template["id"];
        }
        return $templateKeys;
    }

    public function addSystemTemplate(){
        $titles = I("post.title");
        $contents = I("post.content");
        $examples = I("post.example");
        $standard_ids = I("post.standard_id");
        $item_selecteds = I("post.item_selected"); //选中的项
        $templateKeys = $this->getSystemExistsTemplateMsgKeys();
        if (count($titles) == count($contents) && count($standard_ids) == count($titles)){
            $datalist = array();
            foreach($item_selecteds as $key){
                $title = $titles[$key];
                if (empty($standard_ids[$key])){
                    return buildMessage("模板消息【" . $title . "】对应的模板编号不能为空", 1);
                }
                $msg_key = getTemplateIdentKey($contents[$key]);
                if ($templateKeys[$msg_key]) {
                    return buildMessage("模板消息【" . $title . "】已经存在",1);
                }
                if ($contents[$key]) {
                    $data["title"] = $title;
                    $data["content"] = $contents[$key];
                    $data["example"] = $examples[$key];
                    $data["msg_key"] = $msg_key;
                    $data["standard_id"] = $standard_ids[$key];
                    $data["update_time"] = time();
                    $data["is_system_tpl"] = 1;//系统
                    $datalist[] = $data;
                }
            }
            if (M(CONTROLLER_NAME)->addAll($datalist) !== false){
               return buildMessage("导入成功！");
            }
        }else{
             return buildMessage("数据有错误！",1);
        }
    }
    protected function _before_delete($options)
    {
        $count = M("WxBranchTemplate")->field("id")->where(array("parent_id" => $options["where"]["id"]))->count();
        if ($count > 0){
            $this->error = "模板已经被用，不能删除！";
            return false;
        }
        parent::_before_delete($options);
    }

	/**
	 * @param $standard_id 	公众号提供的模板编号
	 * @param $title		使用的场景如：清卡通知
	 * @return mixed		array（id,title,content,example,msg_key）
	 * 根据模板编号和使用的场景获取对应的模板详情
	 */
    public function getScenarioTemplate($standard_id,$title){
		$tmcondition["standard_id"] = array('eq',$standard_id);
		$tmcondition["title"] = array('like','%'.$title.'%');
		$template = M('WxTemplateMessage')->where($tmcondition)->field('id,title,content,example,msg_key')->find();
		return $template;
	}
}