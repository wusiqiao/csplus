<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/5
 * Time: 14:43
 */

namespace ESAdmin\Controller;


use Common\Lib\Controller\DataController;

class ComProductController extends DataController
{
    public function batchAction($ids, $action){
        $ids = explode(',', $ids);
        if (count($ids) == 0) {
            return $this->ajaxReturn(['code' => 1, 'message' => '请选择要批量操作的服务']);
        }

        $data['updated_at'] = time();
        $data['state'] = $action == 'lower' ? 0 : 1;
        $where['id'] = ['in', $ids];
        if(!$this->_user_session->isAdmin){
            $where['branch_id'] = getBrowseBranchId();
        }

        if (!D(CONTROLLER_NAME)->where($where)->save($data)) {
            return $this->ajaxReturn(['code' => 1, 'message' => '操作失败!']);
        }

        return $this->ajaxReturn(['code' => 0, 'message' => '操作成功!']);
    }

    /*
     * 获取解析前端传入的条件
     * 加入限制条件,当前公司创建者可见
     */
    protected function _parseFilter(&$filter)
    {
        parent::_parsefilter($filter);
//        $filter["a.service_id"] = array("eq", $this->_user_session->userId);
    }

    protected function _parseOrder(&$order)
    {
        $order = "sort";
    }

    /*
     * 在模板渲染成功前载入所需数据
     */
    protected function _before_display_dataview(&$data)
    {
        parent::_before_display_dataview($data);
        if (ACTION_NAME == 'add') {
            $categorys = getCategory(1);
            $categorys_children = getCategory(2, $categorys[0]['id']);
            $this->assign("categorys", $categorys);
            $this->assign("categorys_children", $categorys_children);
        } else if (ACTION_NAME == 'detail') {

        }
    }

    public function getTwoLevelListsAction()
    {
        $pid = I('post.pid');
        if ($pid > 0) {
            $categorys_children = getCategory(2, $pid);
            $this->ajaxReturn(array('error' => 0, 'data' => $categorys_children));
        } else {
            $this->ajaxReturn(array('error' => 1, 'message' => '数据错误!'));
        }

    }

    /*
     * 载入列表前
     */
    protected function _before_list(&$list)
    {
        parent::_before_list($list);
        foreach ($list as $key => $value) {
            if ($value['price_type'] == PRODUCT_PRICE_TYPE_MARK) {
                $list[$key]['view_price'] = $value['real_cash'] . '元/' . $value['unit'] . ' 原价 <span style="text-decoration:line-through">' . $value['original_cash'] . '</span>';
            } else {
                $list[$key]['view_price'] = '面议';
            }
//            $list[$key]['created_at'] = date('Y年m月d日 h:i', $value['created_at']);
            $list[$key]['category'] = category($value['category_pid']) . '-' . category($value['category_id']);
            $list[$key]['assembly'] = $value['is_step'] == PRO_STEP_ON ? '开启' : '关闭';
        }
    }

    public function shelfHandleAction()
    {
        if (IS_POST) {
            $postdata = I('post.');
            $message = $postdata['type'] == 1 ? '上架' : '下架';
            if ($this->_user_session->userType == USER_TYPE_COMPANY_MANAGER) {
                $condition['id'] = $postdata['id'];
                $condition['branch_id'] = $this->_user_session->currBranchId;
                $save['state'] = $postdata['type'];
                $result = D(CONTROLLER_NAME)->where($condition)->save($save);
                if ($result) {
                    $this->ajaxReturn(buildMessage($save));
                } else {
                    $this->ajaxReturn(buildMessage($message . "失败!", 1));
                }
            } else {
                $this->ajaxReturn(buildMessage($message . "失败,没有操作权限!", 1));
            }

        }

    }

    /*
     * ADD/SAVE  Before
     */
    protected function _before_write($type, &$data)
    {
        parent::_before_write($type, $data);
        //判断是否有人购买
        if ($type == parent::ACTION_DETAIL) {
            //获取购买数量
            $orders = D('ComProduct')->getProductBuySingle($data['id']);
            if ($orders['state'] == 1) {
                $this->responseJSON(buildMessage("保存失败：请先下架后修改!!", 1));
                die;
            }
        }

        $data['updated_at'] = time();
        $data['created_at'] = time();
        $data['step_flow'] = $data['is_step'] == 1 ? str_replace('&amp;', '&', $data['step_flow']) : '';
        if (empty($data['category_id'])) {
            $this->responseJSON(buildMessage("业务类别不能为空", 1));
        }
        $category = M('ComCategory')->field('parent_id,name')->where('id = ' . $data['category_id'])->find();
        $data['category_pid'] = $category['parent_id'];
        $data['category_name'] = $category['name'];
    }

    /*
     * ADD/SAVE  Alfet and Detail Before
     */
    protected function _before_detail(&$data)
    {
        parent::_before_detail($type, $data);

//        if ($data['price_type'] == PRODUCT_PRICE_TYPE_MARK) {
//            $data['view_price'] = $data['real_cash'] . '元/' . $data['unit'] . ' 原价' . $data['original_cash'];
//        } else {
//            $data['view_price']  = '面议';
//        }
        $data['created_at'] = date('Y年m月d日 h:i', $data['created_at']);
        $data['category'] = category($data['category_pid']) . '-' . category($data['category_id']);
        $data['countStep'] = 0;
        if (strlen(trim($data['step_flow'])) > 0) {
            $step_flow = explode('&,', $data['step_flow']);
            foreach ($step_flow as $key => $val) {
                $data['step_view'][$key] = array(
                    'value' => $val,
                    'name' => '第' . int_trans_ch($key + 1) . '步',
                    'level' => ($key + 1)
                );
            }
            $data['countStep'] = count($step_flow);
        }
        $categorys = getCategory(1);
        $categorys_children = $data['category_pid'] > 0 ? getCategory(2, $data['category_pid']) :
            getCategory(2, $categorys[0]['id']);
        $this->assign("categorys", $categorys);
        $this->assign("categorys_children", $categorys_children);
        $this->getTopicsLists($data['id']);
//        var_dump($data);die;
    }

//    获取选项列表
    public function getTopicsLists($product_id)
    {
        $topics = M('ComProductOptions')->where('product_id = ' . $product_id)->order('parent_id asc,sort asc')->select();
        $topics_arr = array();
        $topics_id = array();
        foreach ($topics as $key => $val) {
            $topics_id[$val['id']] = $val;
            if ($val['parent_id'] == 0) {
                $topics_arr[$val['id']]['name'] = $val['name'];
                $topics_arr[$val['id']]['id'] = $val['id'];
            } else {
                $topics_arr[$val['parent_id']]['children'][] = $val;
                $topics_arr[$val['parent_id']]['vr'] = strlen($topics_arr[$val['parent_id']]['vr']) > 0 ?
                    $topics_arr[$val['parent_id']]['vr'] . '&|' . $val['name'] :
                    $val['name'];
            }
        }
        $attrs = M('ComOrderAttribute')->where('product_id = ' . $product_id)->select();
        $attrs_binds = array();
        foreach ($attrs as $key => $val) {
            $temp_value = explode(',', $val['value']);
            $temp = [];
            foreach ($temp_value as $k => $v) {
                $temp[] = [
                    'title' => $topics_id[$topics_id[$v]['parent_id']]['name'],
                    'value' => $topics_id[$v]['name'],
                ];
            }
            $attrs_binds[] = [
                'content' => $temp,
                'ids' => $val['value'],
                'discount' => $val['real_cash'],
                'origina' => $val['original_cash']
            ];
        }
        $this->assign('attrs', json_encode($attrs_binds));
        $this->assign('topics', $topics_arr);
    }

    /**
     * 获取每个业务对应的步骤
     */
    public function getCateStepListAction()
    {
        if (IS_POST) {
            $category_id = I("post.category_id");
            $step_cate = M("ComCategoryStep")->where("step_open = 1 and category_id = " . $category_id)->find();
            if (!$step_cate) {
                //获取上级业务
                $cate_pid = M("ComCategory")->where("id = " . $category_id)->find();
                $step_cate = M("ComCategoryStep")->where("step_open = 1 and  category_id = " . $cate_pid['parent_id'])->find();
            }
            if ($step_cate) {
                //有
                $tag = explode('&,', $step_cate['step_tag']);
                foreach ($tag as $key => $value) {
                    $tag_list[$key]['index_num'] = (string)($key + 1);
                    $tag_list[$key]['step_num'] = '第' . int_trans_ch($key + 1) . '步';
                    $tag_list[$key]['step_view'] = $value;
                }
                $step_cate['step_tag'] = $tag_list;
                $this->responseJSON(array('error' => 0, 'list' => $step_cate['step_tag']));
            } else {
                $this->responseJSON(array('error' => 1));
            }
        }
    }

    //新增，更新后返回数据，一般返回全部，特殊需要处理的就是有大数据字段，没必要返回
    protected function _getLastData($id)
    {
        return D(CONTROLLER_NAME)->field("content", true)->where("id=$id")->find();
    }


    private function createCategory($category_id, $source_branch, $dest_branch, $code = null, $parent_id = 0)
    {
        $last_id = 0;
        if ($category_id) {
            $condition["branch_id"] = $source_branch;
            $condition["id"] = $category_id;
            $category = M("ComCategory")->where($condition)->find();
            $dest_condition["branch_id"] = $dest_branch;
            $dest_condition["name"] = $category["name"];
            $dest_category = M("ComCategory")->where($dest_condition)->find();
            if (empty($dest_category)) {
                unset($category["id"]);
                $category["parent_id"] = $parent_id;
                $category["querykey"] = firstPinyin($category["name"]);
                $code = empty($code) ? str_pad($dest_branch, 6, '0', STR_PAD_LEFT) : $code;
                $category["code"] = $code;//str_pad($dest_branch, 6, '0', STR_PAD_LEFT);
                $category["branch_id"] = $dest_branch;
                $last_id = M("ComCategory")->add($category);
            } else {
                $last_id = $dest_category["id"];
            }
        }
        return $last_id;
    }

    private function copyOptions($new_data, $source_id, $dest_branch)
    {
        $condition["product_id"] = $source_id;
        $source_dataset = M("ComProductOptions")->where($condition)->select();
        $childrens = array();
        foreach ($source_dataset as $key => $data) {
            $source_dataset[$key]["product_id"] = $new_data["id"];
            $source_dataset[$key]["branch_id"] = $dest_branch;
            $source_dataset[$key]["created_at"] = time();
            if ($data["parent_id"]) {
                $childrens[$data["parent_id"]][] = &$source_dataset[$key];
            }
        }
        foreach ($source_dataset as $key => $data) {
            $id = $data["id"];
            if ($childrens[$id]) {
                unset($data["id"]);
                $max_id = M("ComProductOptions")->add($data);
                foreach ($childrens[$id] as $pk => $parent) {
                    unset($parent["id"]);
                    $parent["parent_id"] = $max_id;
                    M("ComProductOptions")->add($parent);
                }
            }
        }
    }
//
//    private function copyOrderAttribute($new_data, $source_id, $dest_branch){
//        $condition["product_id"] = $source_id;
//        $source_dataset = M("ComOrderAttribute")->where($condition)->select();
//        if ($source_dataset){
//            foreach($source_dataset as $key=>$data){
//                $source_dataset[$key]["product_id"] = $new_data["id"];
//                $source_dataset[$key]["branch_id"] = $dest_branch;
//                $source_dataset[$key]["created_at"] =  time();
//                unset( $source_dataset[$key]["id"]);
//            }
//            M("ComOrderAttribute")->addAll($source_dataset);
//        }
//    }

    protected function _before_copyTo(&$source_data, $dest_branch)
    {
        $category_pid = intval($source_data["category_pid"]);
        $category_id = intval($source_data["category_id"]);
        //必须清空，否则会复制到目标公司，变无效ID
        $source_data["category_id"] = 0;
        $source_data["category_pid"] = 0;
        $source_data["order_count"] = 0; //销售量清零
        $last_pid = 0;
        //来源服务都没设置类别，就直接跳出
        if (empty($category_id) && empty($category_pid)) {
            return true;
        }
        $last_pid = $this->createCategory($category_pid, $source_data["branch_id"], $dest_branch);
        $source_data["category_pid"] = $last_pid;
        $dest_code = str_pad($dest_branch, 6, '0', STR_PAD_LEFT) . "_" . \Org\Util\Strings::randString();;
        $last_id = $this->createCategory($category_id, $source_data["branch_id"], $dest_branch, $dest_code, $last_pid);
        $source_data["category_id"] = $last_id;
    }

    protected function _after_copyTo(&$new_data, $source_id, $dest_branch)
    {
        $this->copyOptions($new_data, $source_id, $dest_branch);
        // $this->copyOrderAttribute($new_data, $source_id, $dest_branch);
    }

    public function getChosenNameField()
    {
        return 'a.product_title';
    }
//    public function addAction(){
//        parent::addAction();
//        DIE;
//    }

    protected function _before_add(&$data)
    {
        parent::_before_add($data);
        if ($this->_user_session->branchRole == ROLE_ID_COMPANY_FREE) {
            $count = M("ComProduct")->where("branch_id = " . $this->_user_session->currBranchId)->count();
            if ($count >= 6) {
                die("免费版用户最多创建6个服务！");
            }
        }
    }

    protected function _before_copy(&$data)
    {
        parent::_before_copy($data);
        if ($this->_user_session->branchRole == ROLE_ID_COMPANY_FREE) {
            $count = M("ComProduct")->where("branch_id = " . $this->_user_session->currBranchId)->count();
            if ($count >= 6) {
                die("免费版用户最多创建6个服务！");
            }
        }
    }
}