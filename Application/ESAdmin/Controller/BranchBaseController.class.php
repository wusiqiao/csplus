<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class BranchBaseController extends DataController {
    
    protected function _before_write($type, &$data) {
        parent::_before_write($type, $data);
        $data["leaders"] = implode(",", I("post.leaders"));
        //界面已经没有选择上级，所以必须赋值
        if (empty($data["parent_id"])){
            if ($this->_user_session->isPlatformUser){
                $data["parent_id"] = 1;
            }else{
                $data["parent_id"] = $this->_user_session->currBranchId;
            }
        }
    }

    /*默认取得是所有的资料*/
    public function treeAction() {
        $condition = array();
        $departments = D(CONTROLLER_NAME)->getUserBranchTree($condition, $this->_user_session);
        $this->ajaxReturn($departments);
    }

    /*按类别过滤*/
    public function keyNameListAction($selected = "", $term = "", $select_all = false) {
        $condition = $this->getChosenSearchCondition($selected, $term);
        $tree_list = D(CONTROLLER_NAME)->getUserBranchList($condition, $this->_user_session);
        $this->ajaxReturn($tree_list);
    }
    
    public function deleteAction($id) {
        $where["id"] = array("in", $id);
        $where["child_count"] = array("gt", 0);
        $count = D(CONTROLLER_NAME)->where($where)->count();
        if ($count > 0) {
            $this->ajaxReturn(buildMessage("部门下面含有子部门，请先删除子部门！", 1));
        }
        parent::deleteAction($id);
    }
    
    protected function _parseOrder(&$order) {
        $order = "code";
    }

    public function leaderListAction(){
        $str = I('q');
        $branch_id = I("branch_id");
        if ($this->isPlatformUser || $branch_id == $this->companyId) {
            $condition = [];
            if (!empty($str)) {
                $where['a.name'] = array('like', '%' . $str . '%');
                $where['a.querykey'] = array('like', '%' . $str . '%');
                $where['a.mobile'] = array('like', $str . '%');
                $where['_logic'] = 'or';
                $condition['_complex'] = $where;
            }
            if ($branch_id){
                $condition["a.branch_id"] = $branch_id;
            }
            $condition["a.user_type"] = USER_TYPE_COMPANY_MANAGER;
            $customer_data = M('SysUser a')
                ->join("sys_branch b on a.branch_id=b.id")
                ->where($condition)
                ->field("a.id,a.name,a.querykey,a.mobile,b.name as branch_name")->limit(20)->select();//
            $this->responseJSON($customer_data);
        }
    }
}
