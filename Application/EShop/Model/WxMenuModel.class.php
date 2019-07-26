<?php

namespace EShop\Model;



use Think\Model;

class WxMenuModel extends Model {

    public function getKeyContent($branch_id,$key){
        $result = D('WxMenu')->field('content')->where('branch_id = '.$branch_id.' and value = \''.$key.'\' and type = \'click\'')->find();
        $content = trim($result['content']) == '' ? '该功能在暂未上线，敬请期待~' :  $result['content'];
        return $content;
    }

}
