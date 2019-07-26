<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/24
 * Time: 14:37
 */
namespace EShop\Controller;

use Think\Controller;
class ToolController extends BaseController {
    protected $_default_count = [66,66];
    public function filesAction(){
        $encs = D('ToolQuery')->getEnclosureLists();
        $this->assign('encs',$encs);
        $this->display();
    }
    public function tool_detailAction(){
        if(IS_GET){
            checkLogin();
            $id = I('get.id');
            if(!handleIsManager() || !D('ToolQuery')->isToolManager($id)){
                $this->error('你没有查看的权限!!');
            }
            $result = D('ToolQuery')->getToolDetail($id);
            $this->tool = $result;
            $this->display();
        }

    }
    public function handlerToolManageAction(){
        if(IS_POST){
            $id = I('post.id');
            if(!handleIsManager() || !D('ToolQuery')->isToolManager($id)){
                $this->ajaxReturn(['error'=>1,'msg'=>'您没有操作的权限!!']);
                die;
            }
            $result = D('ToolQuery')->handlerToolManage($id);
            $this->ajaxReturn($result);
        }

    }
    public function nuclear_nameAction(){
        if (IS_GET){
            $this->assign('count',$this->getToolCount());
            $trades = $this->getTradeLists();
            $this->assign('trades',$trades);
            $forms = $this->getFormLists();
            $this->assign('forms',$forms);
            $this->display();
        }
    }
    public function search_trademarksAction(){
        $this->display();
    }
    public function verificationAction(){
        if (IS_GET){
            $type = I('get.t');
            $type_array = ['nuclear','trademarks'];
            if (!in_array($type,$type_array)){
                $this->error('参数错误','/');
                exit;
            }
            $this->assign('tool_counts',$this->getToolTotalCount());
            $this->assign('type',$type);
            $this->display();
        }else{
            $postdata = I('post.');
            if ($postdata['phonecode'] != session('msgcode')){
                $this->ajaxReturn(['error'=>1,'msg'=>'您输入的验证码不正确!!'.$postdata['phonecode']]);
            }
            if ($postdata['nickname']){
                $condition['nickname'] = $postdata['nickname'];
            }
            $condition['mobile'] = $postdata['mobile'];
            $condition['value'] = json_encode($postdata['value']);
            switch ($postdata['type']){
                case'nuclear':
                    $condition['type'] = 0;
                    break;
                case'trademarks':
                    $condition['type'] = 1;
                    break;
                default:
                    return false;
                    break;
            }
            $condition['user_id'] = session('user_id');
            //添加
            $res = D('ToolQuery')->addQuery($condition);
            if ($res){
                $condition['id'] = $res;
                D('ComOrder')->sendWXTool($condition);
                D('ComComment')->sendSysTool($condition);
                $this->ajaxReturn(['error'=>0,'msg'=>'提交成功!!']);
            }else{
                $this->ajaxReturn(['error'=>1,'msg'=>'提交失败!!']);
            }
        }

    }
    protected function getTradeLists(){
        $array = [
            ['公司类型','有限责任公司','股份有限公司','合伙企业','个体户','私营企业','股份合作制企业',
                '联营企业','集体企业','国有企业','一人制公司','集团公司','有限公司','国有独资公司']
        ];
        $res = [];
        foreach ($array as $key=>$val){
            $temp = [];
            $temp['name'] = $val[0];
            for ($i = 1;$i < count($val);$i++ ){
                $temp['children'][] = ['name'=>$val[$i]];
            }
            $res[] = $temp;
        }
        return $res;
    }
    protected function getFormLists(){
        $array = [
            ['科技类','网络科技','电子商务','信息技术','游戏','电子','软件',
                '新材料','生物科技','教育科技'],
            ['许可类','投资管理','金融','资产','商业保理','融资租凭','医疗器械',
                '人力资源','食品','劳务派遣'],
            ['服务类','广告','文化传播','建筑装潢','设计','美容美发','房地产中介',
                '物业管理','商务咨询','企业管理'],
            ['其他','贸易','实业','制造','服饰','化妆品','工程',
                '农业','餐饮管理','物流']
        ];
        $res = [];
        foreach ($array as $key=>$val){
            $temp = [];
            $temp['name'] = $val[0];
            for ($i = 1;$i < count($val);$i++ ){
                $temp['children'][] = ['name'=>$val[$i]];
            }
            $res[] = $temp;
        }
        return $res;
    }
    public function wrongAction($mess='数据丢失,访问失败!',$url = '/'){
        $this->error($mess,$url);
    }
    protected function getToolCount($type = 0){
        return  D('ToolQuery')->getRelatedCount($type) + $this->_default_count[$type];
    }
    protected function getToolTotalCount(){
        $res =  D('ToolQuery')->getToolTotalCount();
        $res['total'] = $res['total'] + array_sum($this->_default_count);
        return $res;
    }

    public function consultAction(){
        $total_count = $this->getConsultantCount();
        $this->assign("total_count",$total_count);
        $this->display();
    }

    public function saveConsultantAction(){
        $data['value'] = $_POST['value'];
        $data['nickname'] = $_POST['nickname'];
        $data['mobile'] = $_POST['mobile'];
        $data['type'] = 2;
        $data['branch_id'] = getBrowseBranchId();
        $data['created_at'] = time();
        $data['user_id'] = $_SESSION['user_id'];
        $result = M("ToolQuery")->add($data);
        if($result !== false){
            $condition['nickname'] = $data['nickname'];
            $condition['mobile'] = $data['mobile'];
            $condition['consultant'] = $_POST['consultant'];
            $condition['type'] = 2 ;
            $condition['id'] = $result;
            D('ComOrder')->sendWXTool($condition);
            //发送给客户
            D('ComOrder')->sendWXConsult($condition,1);
            //D('ComComment')->sendSysTool($condition);
            $this->ajaxReturn(array("err"=>0,"msg"=>"提交成功"));
        }else{
            $this->ajaxReturn(array('err'=>1,"msg"=>"提交失败"));
        }

    }

    protected function getConsultantCount(){
        $condition['type'] = 2;
        $condition['branch_id'] = getBrowseBranchId();
        $total_count['total'] = M("ToolQuery")->where($condition)->count();
        $condition['_string'] = ' created_at > '.strtotime(date('Y-m-d',time())).' and created_at < '.strtotime(date('Y-m-d',strtotime('+1 day')));
        $total_count['today'] = M("ToolQuery")->where($condition)->count();
        return $total_count;
    }


}
