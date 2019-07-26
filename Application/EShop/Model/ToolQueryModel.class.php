<?php

namespace EShop\Model;

use Think\Model;

/**
 * 短信微信消息异步管理，不涉及具体业务。
 */
class ToolQueryModel extends DataModel {
    protected $_MODEL = 'ToolQuery';
    protected $_ENC_MODEL = 'ToolEnclosure';
    protected $_PREFIX= 'tool_';
    public function getEnclosureLists(){
        $field = '*';
        $condition['branch_id'] = getBrowseBranchId();
        $condition['is_hidden'] = 0;
        $order = 'sort desc,created_at desc';
        $enclosure = M($this->_ENC_MODEL) ->field($field)
                                          ->where($condition)
                                          ->order($order)
                                          ->select();
        return $enclosure;
    }
    public function addQuery($data){
        $data['branch_id'] = getBrowseBranchId();
        $data['created_at'] = time();
        $data['creator_id'] = session('user_id');
        $data['user_id'] = session('user_id');
        $data['user_branch_id'] = getUserCompanyId();
        $res = M($this->_MODEL)->add($data);
        return $res;
    }
    public function getRelatedCount($type = 0){
        $condition['type'] = $type;
        $condition['branch_id'] = getBrowseBranchId();
        $res = M($this->_MODEL)->where($condition)->count();
        return $res;
    }
    public function getToolTotalCount(){
        $condition['branch_id'] = getBrowseBranchId();
        $total = M($this->_MODEL)->where($condition)->count();
        $condition['_string'] = ' created_at > '.strtotime(date('Y-m-d',time())).' and created_at < '.strtotime(date('Y-m-d',strtotime('+1 day')));
//        $condition['created_at'] = array('gt',strtotime(date('Y-m-d',time())));
//        $condition['created_at'] = array('lt',strtotime(date('Y-m-d',strtotime('+1 day'))));
        $today = M($this->_MODEL)->where($condition)->count();
        return ['total'=>$total,'today'=>$today];
    }
    public function getToolDetail($id){
        $tool = $this->where('id = '.$id)->find();
        $body = json_decode($tool['value'],true);
        $tool['time'] = date('Y年m月d日 H:i',$tool['created_at']);
        $tool['processed'] = date('Y年m月d日 H:i',$tool['processed_at']);
        $html = [];
        if($tool['type'] == 0)
        {   $tool['type_name'] = '核名查询';
            $html[] = "城市:".$body['city'];
            $html[] = "字号:".$body['firm'];
            $html[] = "行业:".$body['trade'];
            $html[] = "类型:".$body['form'];
        }elseif($tool['type'] == 1){
            $tool['type_name'] = '商标查询';
            $html[] = "商标名称:".$body['name'];
            $html[] = "商标类别:".$body['trade'];
        }elseif ($tool['type'] == 2){
            $tool['type_name'] = '免费咨询';
            $html[] = $body['consultant'];
        }
        $tool['body'] = $html;
        return $tool;
    }
    public function handlerToolManage($id){
        $condition['id'] = $id;
        $condition['is_process'] = 1;
        $condition['processed_at'] = time();
        $res = $this->save($condition);
        return ['error'=>0,'msg'=>'你已提交处理完成标记'];
    }
    public function isToolManager($id){
        $branch_id = getBrowseBranchId();
        $res = $this->where('id = '.$id.' and branch_id = '.$branch_id)->count();
        return $res == 0 ? false : true;
    }
    public function getToolQueryData($data){
        $page_size  = $data->rows;
        $paging     = $data->page;
        $condition['a.branch_id'] = getBrowseBranchId();
        $condition['a.type'] = $data->type;
        $condition['a.is_process'] = $data->process;
        $res = $this->setDacFilter('a')->where($condition)->page($paging, $page_size)->order('a.created_at desc')->select();
        foreach ($res as $key=>$value){
            $temp = json_decode($value['value'],true);
            $res[$key]['mobile'] = $value['mobile'] ?? '';
            $res[$key]['name'] = trim($value['nickname']) ??  '无' ;
            //限制昵称长度
            $length = mb_strlen($res[$key]['name']);
            if($length>4){
                $res[$key]['name'] = mb_substr($res[$key]['name'],0,4)."..";
            }
            $res[$key]['tag'] = $data->process == 0 ? ($value['tag'] != null ? '标签:'. $value['tag'] : '标签：暂无') : '已处理';
            if($data->type != 2){
                $res[$key]['message'] = $data->type == 0 ?
                    '核名信息 : '.$temp['city'].$temp['firm'].$temp['trade'].$temp['form']:
                    '商标信息 : '.$temp['name'].' '.$temp['trade'];
            }elseif($data->type == 2){
                $res[$key]['message'] =  '咨询信息 : '.$temp['consultant'];
            }

            $res[$key]['view_time'] = $data->process == 0 ? '提交时间 : '.date('Y.m.d H:i',$value['created_at']) : '处理时间 : '.date('Y.m.d H:i',$value['processed_at']);
            $res[$key]['handler_tag'] = $data->process == 0 ?
                '<div class="add-tag-btn" data-id="'.$value['id'].'" onclick="handlerTagSearch(this);">标记</div>':
                '';
        }
        return ['total'=>count($res),'rows'=>$res];
    }
    protected function saveToolQuerySingleInc($id,$inc){
        $condition['branch_id'] = getBrowseBranchId();
        $condition['id'] = $id;
        $inc['updated_at'] = time();
        return M($this->_MODEL)->where($condition)->save($inc);
    }
    //修改状态 - 已处理
    public function complateOperation($id){
        $save['is_process'] = 1;
        $save['processed_at'] = time();
        return $this->saveToolQuerySingleInc($id,$save);
    }
    //修改Tag
    public function saveTooleQueryTag($data){
        $id = $data->id;
        $save['tag'] = $data->tag ?? null;
        return $this->saveToolQuerySingleInc($id,$save);
    }
}
