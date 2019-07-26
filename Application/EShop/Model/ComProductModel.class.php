<?php

namespace EShop\Model;

use Think\Model;

class ComProductModel extends Model {
    protected $_MODEL = 'ComProduct';
    /*
 * @param data 提供的匹配数据 - 找服务
 * sort region cate
 */
    public function getHomePageProductLists($data) {
        $_string = '';
        $order = "sort";
        $condition['branch_id']   =  getBrowseBranchId();
        $condition['state'] = PRODUCT_STATE_ON_GROUNDING;//出售中
        $field = ' (select min(real_cash) from com_order_attribute  where product_id=pro.id and real_cash<>0) as min_price,pro.*';
        $total = M("ComProduct pro")->where($condition)->count();
        $page_size  = $data['page_size'];
        $paging     = $data['paging'];
        $product_list = M("ComProduct pro")->field($field)->where($condition)->page($paging, $page_size)->order($order)->select();
        foreach ($product_list as $key => $value) {
//            if ($value['price_type'] == PRODUCT_PRICE_TYPE_MARK) {
//                $view_price = '<span>' . $value['real_cash'] . '</span>元/' . $value['unit'];
//            } else {
//                $view_price = '价格面议';
//            }
            $sayList['list'][] = array(
                'view_price' => $value['min_price'] > 0 ? $value['min_price'].'元' : '面议',
                'product_id' => $value['id'],
                'product_title' => $value['product_title'],
                'category' => category($value['category_pid']) . '-' . category($value['category_id']),
//                'view_price' => $view_price,
                'order_count' => $value['order_count'],
                'product_pic' => empty($value['product_pic']) || $value['product_pic'] =='' ? getCategoryListDefaultPic() : $value['product_pic'] ,
                'product_desc' => $value['product_desc'] == '' ? '暂无简介' : $value['product_desc'],
            );
        }
        $sayList['total_count'] = $total;
        $sayList['count'] = count($product_list);
        return $sayList;
    }
    public function getProductList()
    {
        $condition['branch_id']   =  getBrowseBranchId();
        $condition['state'] = PRODUCT_STATE_ON_GROUNDING;//出售中
        $order = "sort";
        $field = ' (select min(real_cash) from com_order_attribute  where product_id=pro.id and real_cash<>0) as min_price,pro.*';
        $product_list = M("ComProduct pro")
                            ->field($field)
                            ->where($condition)
                            ->order($order)
                            ->select();
        $sayList = [];
        foreach ($product_list as $key => $value) {
            $sayList[] = array(
                'view_price' => $value['min_price'] > 0 ? $value['min_price'].'元' : '面议',
                'product_id' => $value['id'],
                'product_title' => $value['product_title'],
                'product_pic' => empty($value['product_pic']) || $value['product_pic'] =='' ? getCategoryListDefaultPic() : $value['product_pic'] ,
                'product_desc' => $value['product_desc'] == '' ? '暂无简介' : $value['product_desc'],
                'url' => 'https://'.$_SERVER['HTTP_HOST'].'/Product/productDetail/product_id/'.$value['id'].'.html'
            );
        }
        return $sayList;
    }
    public function getProductData($product_id){
        $where['id']            = $product_id;
        $where['branch_id']     = getBrowseBranchId();
        $product = M('ComProduct')->where($where)->find();
        $product['category_view'] = category($product['category_pid']) . '-' . category($product['category_id']);
        return $product;
    }

    //获取第一个价格项目
    public function getFirstAttrs($product_id){
        //判断最大金额是否对应面议，返回最低金额项
        $max_cash = M('ComOrderAttribute')->where('product_id = '.$product_id)->order('real_cash desc')->getField('real_cash');
        if($max_cash!=0){
            return M('ComOrderAttribute')->where('product_id = '.$product_id.' and real_cash<>0')->order('real_cash asc,id asc')->getField('value');
        }else{
            return M('ComOrderAttribute')->where('product_id = '.$product_id)->order('real_cash asc,id asc')->getField('value');
        }
    }

    //总的有多少个价格项目
    public function getOrderAttrsCount($product_id){
        return M('ComOrderAttribute')->where('product_id = '.$product_id)->count();
    }
    /*
 * @param data 提供的搜索数据
 *
 */
    public function getTheRgionProductLists($data) {
        $_string = '';
        $branch_id = getBrowseBranchId();
        //判断是否有存在category_id  --  根据
        if (!empty($data['cate_id']) && $data['cate_id'] > 0) {
            $cate_level = M('ComCategory')->where("id = " . $data['cate_id'])->getField('level');
            if ($cate_level == 1) {
                $condition['pro.category_pid'] = $data['cate_id'];
            } else {
                $condition['pro.category_id'] = $data['cate_id'];
            }
        }
        //判断是否有存在keyword
        if (!empty($data['keyword']) && $data['keyword'] != ''
            or $data['keyword'] == '0' ) {
            if(strpos($data['keyword'],'%') == 0){
                $data['keyword']=str_replace('%','\%',$data['keyword']);
            }

            //增加多功能查找
            if(strpos($data['keyword'],'@') == 5){
                $this->handlerMultiKeywork($data['keyword'],$condition);
            }else{
                //产品标题的查找
                $_string .= "(pro.product_title like '%" . $data['keyword'] . "%' "
                    . " or pro.category_name like '%" . $data['keyword'] . "%'";
                $_string .=')';
            }
        }
//        if (!empty($data['screen_type']) && $data['screen_type'] != '') {
//            $order_data = explode('-', $data['screen_type']);
//            $order = $order_data[0] . " " . $order_data[1];
//        } else {
            $order = "order_count desc";
//        }
        if ($_string != '') {
            $condition['_string'] = $_string.'and branch_id = '.$branch_id;
        }else{
            $condition['_string'] = 'branch_id = '.$branch_id;
        }
        $condition['pro.state'] = PRODUCT_STATE_ON_GROUNDING;
        $total = M($this->_MODEL)->alias("pro")->where($condition)->count();
        $page_size = I("post.rows");
        $paging = I("post.page");
        $product_list = M($this->_MODEL)->alias("pro")->field("pro.*")->where($condition)->page($paging, $page_size)->order($order)->select();
        foreach ($product_list as $key => $value) {
//            if ($value['price_type'] == PRODUCT_PRICE_TYPE_MARK) {
//                $view_price = '<span>' . $value['real_cash'] . '</span>元/' . $value['unit'];
//            } else {
//                $view_price = '价格面议';
//            }
            $sayList['rows'][] = array(
                'product_id' => $value['id'],
                'product_title' => $value['product_title'],
                'category' => category($value['category_pid']) . '-' . category($value['category_id']),
//                'view_price' => $view_price,
                'order_count' => $value['order_count'],
                'product_pic' => empty($value['product_pic']) || $value['product_pic'] =='' ? getCategoryListDefaultPic() : $value['product_pic'] ,
                'product_desc' => $value['product_desc'] == '' ? '暂无简介' : $value['product_desc'],
            );
        }
        $sayList['total_count'] = $total;
        $sayList['total'] = count($product_list);
        return $sayList;
    }
    //取出服务热度
    public function getCategoryInstallHot(){
        $max       = 5;
        $branch_id = getBrowseBranchId();
//        $list      =     M($this->_MODEL)    ->alias('pro')
//                                             ->field('pro.category_id,cate.name,cate.icon,count(*) as total')
//                                             ->join('com_category cate on cate.id = pro.category_id')
//                                             ->where('pro.branch_id = '.$branch_id)
//                                             ->group('pro.category_id')
//                                             ->order('total desc')
//                                             ->limit('0,'.$max)
//                                             ->select();
        //排序错误进行修复 2019-06-12
//        $field = 'cate.*,(select count(*) from com_product where category_id = cate.id) as count';
//        $list = M("ComCategory cate") ->field($field)
//                                      ->where("cate.branch_id=$branch_id and cate.parent_id>0 and cate.is_hot=1")
//                                      ->order("sort asc, count desc")
//                                      ->limit(0,$max)
//                                      ->select();
        //排序错误进行修复 2019-06-12  new
        $list = M("ComCategory")
            ->where(['branch_id' => $branch_id, 'is_hot' => 1, 'parent_id' => ['gt', 0] ])
            ->order(['sort' => 'ASC'])
            ->limit(0, $max)
            ->select();

        foreach ($list as $key => $val){
            if(!empty($val['icon'])){
                $list[$key]['icon'] = substr($val['icon'],1);
            }
            $list[$key]['location'] = ($val['count'] == 1) ? '/Product/productDetail/category_id/'.$val['id'].'.html' : '/index/search_result/keyword/'.$val['name'].'.html';
        }
        return $list;
    }
    //获取各大类排行
    public function getTopCategoryHot(){
        $single_max = 64;
        $branch_id = getBrowseBranchId();
        $field = 'cate.*,(select count(*) from com_product where state = 1 AND category_id = cate.id) as count';
        $sql = ' select '.$field.' from com_category as cate where  cate.is_valid = 1 AND cate.branch_id='. $branch_id . ' ORDER BY sort ASC';
        $list = M()->query($sql);
        $top_List = array();
        $top      = array();
        foreach($list as $key=>$val){
            if($val['parent_id'] > 0 && count($top_List[$val['parent_id']]) < $single_max){
                $temp = $val;
                $temp['location'] = $val['count'] == 1 ? '/Product/productDetail/category_id/'.$val['id'].'.html' : '/index/search_result/keyword/'.$val['name'].'.html';
                $top_List[$val['parent_id']][] = $temp;
            }
            if($val['parent_id'] == 0){
                $top[$val['id']] = $val;
                $top[$val['id']]['icon'] = substr($val['icon'],1);
            }
        }
//        ksort($top_List);
//        ksort($top);
        return array($top_List,$top);
    }
    //多功能搜索
    public function handlerMultiKeywork($keyword,&$data){
        $keys = explode('@',$keyword);
        $temp = strtolower($keys[0]);
        switch (strtolower($keys[0])){
            case 'scope':
                $res = M('SpTicketStock')   ->alias('ts')
                                            ->field('ac.scope')
                                            ->join('sp_activity as ac on ac.id = ts.activity_id')
                                            ->where('ts.code = \''.$keys[1].'\'')
                                            ->find();
                $data['pro.id'] = array('in',$res['scope']);
                break;
            default:
                break;
        }
    }
    //获取多选项列表
    public function getProductTopics($pid){
        $topics  = M('ComProductOptions')->where('product_id = '.$pid)->order('parent_id asc, sort asc')->select();
        $attrs   = M('ComOrderAttribute')->where('product_id = '.$pid)->getField('value',true);
        $attr_ids = [];
        foreach ($attrs as $key=>$val){
            $temp = strpos($val,',') === false ? (array)$val : explode(',',$val);
            foreach ($temp as $k=>$v){
                if(!in_array($v,$attr_ids)){
                    $attr_ids[] = $v;
                }
            }
        }
        $lists   = [];
        foreach($topics as $key => $val){
            if($val['parent_id'] == 0){
                $lists[$val['id']] =  $val;
            }else{
                if(in_array($val['id'],$attr_ids)){
                    $val['checked'] = 1;
                }else{
                    $val['checked'] = 0;
                }
                $lists[$val['parent_id']]['children'][] = $val;
            }
        }
        return $lists;
    }
    //获取可选列表
    public function getAttrsChecked($pid,$tid){
        $where = 'is_open = 1 ';
        $where.= 'and product_id = '.$pid;
        if (strpos($tid,',')){
            $tids = explode(',',$tid);
            foreach ($tids as $key=>$val){
                $where .= ' and FIND_IN_SET('.$val.',value) ';
            }
        }else{
            $tids = $tid;
            $where .= ' and FIND_IN_SET('.$tids.',value) ';
        }
        //获取全部信息
        $topics_attrs = M('ComOrderAttribute')->field('value')->where($where)->select();
        $attrs = [];
        foreach($topics_attrs as $key => $val){
            $temp = explode(',',$val['value']);
            foreach ($temp as $k => $v){
                if(!in_array($v,$tids) && !in_array($v,$attrs)){
                    $attrs[] = $v;
                }
            }
        }
        $options['id'] = array('in',$attrs);
        if($attrs){
            $atts_p        = M('ComProductOptions')->where($options)->distinct(true)->getField('parent_id',true);
            return ['attrs'=>$attrs,'attrs_p'=>$atts_p];
        }else{
            return [];
        }

    }
    //获取价格
    public function getAttrsCase($data){
        $pid = $data['pid'];
        $tips= $data['tips'];
        $where = 'product_id = '.$pid.' and value = \''.$tips.'\' ';//and real_cash<>0
        $topics_case = M('ComOrderAttribute')->field('id,real_cash,original_cash,value')->where($where)->find();
        if($topics_case){
            if($topics_case['real_cash'] > 0 ){
                $topics_case['cash'] = '¥'.$topics_case['real_cash'];
            }else{
                $topics_case['cash'] = '面议';
            }
        }
        return $topics_case ? ['error'=>0,'attrs'=>$topics_case] : ['error'=>1];
    }
    //获取价格
    public function getAttribute($data,$type = "product"){
        if($type == 'product'){
            $aid  = $data['aid'];
            $pid  = $data['id'];
            $where = 'product_id = '.$pid.' and id = '.$aid;
        }else{
            $where = is_array($data) ? 'id = '.$data['attribute']: 'id = '.$data;
        }
        //.'and real_cash<>0'
        $topics_case = M('ComOrderAttribute')->field('id,real_cash,original_cash,value')->where($where)->find();
        if($topics_case){
            $topics_case['cash'] = '';
            if($topics_case['real_cash'] > 0 ){
                $topics_case['r_cash'].= $topics_case['real_cash'];
                $topics_case['o_cash'].= $topics_case['original_cash'];
            }else{
                $topics_case['r_cash'].= '面议';
            }
            //获取
//            if($type == 'product'){
//                $options['id'] = array('in',$topics_case['value']);
//                $result = M('ComProductOptions')->where($options)->distinct(true)->getField('name',true);
//            } else {
                $options['a.id'] = array('in',$topics_case['value']);
                $result = M('ComProductOptions')
                    ->alias('a')
                    ->where($options)
                    ->join('com_product_options as b on b.id = a.parent_id')
                    ->field('b.name as parent_name,a.name')
                    ->distinct(true)
                    ->select();
//            }
            $topics_case['tips'] = $result;
        }
        return $topics_case;
    }
    //商品上下架
    public function product_edit_state($data) {
            $edit_state = $data['product_edit_state'];
            $product_id = $data['id'];
            $save['id'] = $product_id;
            if ($edit_state == 'grounding') {
                $save['state'] = PRODUCT_STATE_ON_GROUNDING;
                $msg = '商品重新上架成功!!';
            } elseif ($edit_state == 'undercarriage') {
                $save['state'] = PRODUCT_STATE_DOWN_GEOUNDING;
                $msg = '商品下架成功!!';
            }
            M($this->_MODEL)->save($save);
            return array("error" => "0", "msg" => $msg, "view_state" => product_state($save['state']));
    }
    public function product_delete($data){
        $order_count = M('ComOrder')->where("product_id = " . $data['product_id'])->count();
        if ($order_count > 0) {
            return array("error" => "1", "msg" => "服务删除失败,该服务已经有订单存在!");
        }
        M($this->_MODEL)->where("id = " . $data['product_id'])->delete();
        return array("error" => "0", "msg" => "服务删除成功!!");
    }
    public function getIsCustomTitle($data)
    {
       $step =      M($this->_MODEL) ->alias('pro')
                                     ->field('pro.step_flow,pro.is_step')
                                     ->join('com_order as co on co.product_id = pro.id')
                                     ->where('co.id = '.$data['order_id'])
                                     ->find();
       if ($step['is_step'] == 0){
           return true;
       }else{
           $step_list = explode('&,',$step['step_flow']);
           return in_array($data['title'],$step_list) ? false : true;
       }

    }
}
