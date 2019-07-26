<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/5
 * Time: 14:43
 */

namespace ESAdmin\Controller;


use Common\Lib\Controller\DataController;

class SysAskController extends DataController
{
    public function listAction() {
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $_order = array();
        $this->_parseOrder($_order);
        $branch_id = getBrowseBranchId();
        $orders    = D('ComOrder')->where('branch_id = '.$branch_id)->getField('id',true);
        $manages   = D('SysUser')->where('branch_id = '.$branch_id.' and user_type = '.USER_TYPE_COMPANY_MANAGER)->getField('id',true);
        $_filter   = '(a.branch_id = '.$branch_id.' and a.obj_type = \'order\' and a.obj_id in ('.implode(",",$orders).'))';
        $_filter  .= ' or ';
        $_filter  .= '(a.branch_id = '.$branch_id.' and a.obj_type = \'system\' and a.obj_id in ('.implode(",",$manages).'))';
        $count = D(CONTROLLER_NAME) ->setDacFilter('a')
                                    ->field("a.origin_id,a.obj_id,a.obj_type,max(a.comment_time) as time")
                                    ->where($_filter)
                                    ->group("obj_type,obj_id,origin_id")
                                    ->count();
        $list = D(CONTROLLER_NAME)  ->setDacFilter('a')
                                    ->field("a.origin_id,a.obj_id,a.obj_type,max(a.comment_time) as time")
                                    ->where($_filter)
                                    ->group("obj_type,obj_id,origin_id")
                                    ->order($_order)
                                    ->page($page_index, $page_size)
                                    ->select();
        $lists = array();
        $routine = M('ComStore')->field('default_header_pic')->where('branch_id ='.$branch_id)->find();
        $default_header_pic = strlen($routine['default_header_pic'] ) == 0 ? IMG_URL.'head_pic/logo.png' : $routine['default_header_pic'] ;
        foreach($list as $key => $val){
            if($val['obj_type']	==	'order'){
                $temp = D(CONTROLLER_NAME) ->alias("a")
                                                  ->field("a.id,a.attach_1,u.head_pic,o.contacts,CONCAT('服务|',pro.product_title) as title,a.obj_type,a.content,a.comment_time,a.origin_id,a.obj_id")
                                                  ->join("com_order o on o.id = a.obj_id")
                                                  ->join("com_product pro  on pro.id = o.product_id")
                                                  ->join("sys_user u on u.id = o.user_id")
                                                  ->where("a.origin_id = ".$val["origin_id"].' and a.obj_id = '.$val["obj_id"].' and a.comment_time = '.$val["time"].' and a.branch_id ='.$branch_id)
                                                  ->find();
                $temp['comment_time']   = date('Y年m月d日 H:i',$temp['comment_time']);
                $temp['head_pic']   = $temp['head_pic'] == '' ? $default_header_pic : $temp['head_pic'];
                $temp['content']    = ($temp['attach_1'] != '') ?
                                        getAskUploadFileImages($temp['attach_1'],'',true)['type_name'] :
                                        base64_decode($temp['content']);
                $temp['remark']     = $temp['title'];
                $lists[$key] = $temp;
            }elseif($val['obj_type']	==	'system'){
                $temp = D(CONTROLLER_NAME) ->field("id,'' as head_pic,'系统' as contacts,CONCAT('系统信息') as title,obj_type,content,comment_time,origin_id,obj_id")
                                                  ->where("origin_id = ".$val["origin_id"].' and obj_id = '.$val["obj_id"].' and comment_time = '.$val["time"].' and branch_id ='.$branch_id)
                                                  ->find();
                $temp['comment_time']   = date('Y年m月d日 H:i',$temp['comment_time']);
                $temp['head_pic']       = $default_header_pic;
                $temp['content']        = base64_decode($temp['content']);
                $temp['remark']     = $temp['title'];
                $lists[$key] = $temp;
            }
        }
        $result["total"] = $count;
        $result["rows"] = $lists;
        $this->responseJSON($result);
    }
    public function detailAction($id = null) {
        $this->assignPermissions();
        $branch_id = getBrowseBranchId();
        $ask = D(CONTROLLER_NAME)->where("id='$id'")->find();
        $where = array('a.origin_id' => $ask['origin_id'], 'a.obj_type' => $ask['obj_type'], 'a.obj_id' => $ask['obj_id'],'a.branch_id'=>$branch_id);
        $list = D(CONTROLLER_NAME)
                    ->alias('a')
                    ->field('a.content,a.comment_time,a.user_id,u.head_pic,u.user_type,a.id,a.read,a.attach_1')
                    ->join("sys_user as u on a.user_id=u.id",'left')
                    ->where($where)
                    ->order('a.comment_time desc')
                    ->select();
        $routine = M('ComStore')->field('default_header_pic')->where('branch_id ='.$branch_id)->find();
        $default_header_pic = strlen($routine['default_header_pic'] ) == 0 ? IMG_URL.'head_pic/logo.png' : $routine['default_header_pic'] ;
        foreach ($list as $val) {
            $flag = 0;
            if ($val['user_type'] != USER_TYPE_COMPANY_MANAGER) {
                $flag = 1;
            }
            $face	=	strpos($val['head_pic'], 'Public/') === false ? $val['head_pic'] : "/" . $val['head_pic'];
            //NEW Jan 5,2018 文件处理
            $attachs =   getAskUploadFileImages($val['attach_1'],'',true);
            $record[] = array(
                'face' => $face?$face:$default_header_pic,
                'content' => base64_decode($val['content'], true),
                'begtime' => timeline($val['comment_time']),
                'flag' => $flag,
                'user_id' => $val['user_id'],
                'attach'	=> $val['attach_1'],
                'attach_type'=> $attachs['type']
            );
        }
        $this->assign("model", $record);
        exit($this->fetch($this->_get_detail_template($record)));
    }
}