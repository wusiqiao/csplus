<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class  WrkInventoryTemplateController extends DataController {
    protected $_store_standard = [
        'OPENTM413009573',//
        'OPENTM413096810',
        'OPENTM200950099',
        'OPENTM401202189'
    ];
    protected function _before_display_dataview(&$data)
    {
        parent::_before_display_dataview($data); // TODO: Change the autogenerated stub
        $condition["standard_id"] = empty($this->_store_standard) ? 0 : array('in',$this->_store_standard);
        $list = M('WxTemplateMessage')
            ->field("id as value,title as text")
            ->where($condition)->select();
        $this->templates = count($list) > 0 ? json_encode($list) : [];
        $this->contents = json_encode([]);
        if ($data['id'] > 0) {
            $example = $this->handlerContentRecords(D('EShop/'.CONTROLLER_NAME)->getContentTemplate($data['wx_template']),'example');
            $content = $this->handlerNoticeContentRecords($data['content']);
            $this->contents = json_encode(['content_records'=>$content,'example_records'=>$example]);
            $this->id = $data['id'];
        }
    }
}