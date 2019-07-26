<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class ComMaterialLibraryModel extends DataModel {
    /**
     * 同步状态 -- 微信同步
     * */
    const SYN_STATUS_10 = 10;
    /**
     * 同步状态 -- 修改未同步
     * */
    const SYN_STATUS_20 = 20;
    /**
     * 同步状态 -- 新增 未同步
     * */
    const SYN_STATUS_30 = 30;

    protected function _after_delete($data,$options) {
        parent::_after_delete($data,$options);
        $condition['parent_id'] = $data['id'];
        M('ComMaterialLibrary')->where($condition)->delete();
    }

    public function materialSynchronization($material_data,$material)
    {
        $branch_id = getBrowseBranchId();
        $condition['various'] = $material;
        $condition['branch_id'] = $branch_id;
        $condition['syn_status'] = ['neq', self::SYN_STATUS_30];
        M('ComMaterialLibrary')->where($condition)->delete();//清除所有的记录
        if ($material_data['total_count'] > 0) {
            $append_all = [];
            if ($material == 'news') {
                foreach ($material_data['item'] as $key => $value) {
                    $append_temp['media_id'] = $value['media_id'];
                    $append_temp['update_time'] = $value['update_time'];
                    $append_temp['parent_id'] = 0;
                    $append_temp['branch_id'] = $branch_id;
                    $append_temp['various'] = $material;
                    $parent_id = $this->add($append_temp);
                    foreach($value['content']['news_item'] as $k => $v) {
                        $append_single = $v;
                        $thumb_url = $v['thumb_url'];
                        $img_location_url ='./uploads/asks/'. str_replace(['http://mmbiz.qpic.cn/mmbiz_jpg/','http://mmbiz.qpic.cn/mmbiz_png/','/0?wx_fmt=jpeg','/0?wx_fmt=png'],'',$thumb_url).'.jpeg';
                        if (!file_exists($img_location_url)) {
                            file_put_contents($img_location_url, file_get_contents($thumb_url));
                        }
                        $append_single['local_thumb_url'] = $img_location_url;
                        unset($append_single['content']);
                        $append_single['branch_id'] = $branch_id;
                        $append_single['parent_id'] = $parent_id;
                        $append_single['various'] = $material;
                        $append_single['order'] = $k;
                        $append_single['content'] = $v['content'];
//                        $append_all[] = $append_single;
                        \Think\Log::write($v['local_thumb_url']);
                        $this->add($append_single);
                    }
                }
                return ['code'=>0,'message' =>'同步成功','data'=> $material_data ];
            } else {
                foreach ($material_data['item'] as $key => $value) {
                    $append_single = $value;
                    $append_single['various'] = $material;
                    $append_single['branch_id'] = $branch_id;
                    $append_single['parent_id'] = 0;
                    $append_all[] = $append_single;
                }
                $result = $this->addAll($append_all);
                if ($result) {
                    return ['code'=>0,'message' =>'同步成功','data'=>$material_data];
                } else {
                    return ['code'=>1,'message' =>'同步失败','data'=>$material_data];
                }
            }
        } else {
            return ['code'=>0,'message' =>'同步成功','data'=>$material_data];
        }
    }

    /**
     * 同步更新
     * */
    public function sysUpdate($id, $url){
        $this->where(['id' => $id])->save([
            'url' => $url,
            'syn_status' => self::SYN_STATUS_10,
        ]);
    }
    /**
     * 当同步进行修改操作
     * */
    public function modifyParent($parentId){
        return $this->where(['id' => $parentId])->update([
            'syn_status' => self::SYN_STATUS_20,
        ]);
    }
}