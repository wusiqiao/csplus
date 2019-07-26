<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\TreeController;

class WxMenuController extends TreeController
{
    protected $_material_options = ['news', 'image', 'video', 'voice'];
    protected $_material_default = 'news';
    protected $_material_prefix = 'material_';
    const APPLETS_URL = 'pages/eshop/eshop'; //小程序跳转链接

    protected function _before_write($type, &$data)
    {
        parent::_before_write($type, $data);
        $data['value'] = I("post.value_" . I("post.type"));
        $data['is_valid'] = 1;

        switch ($data['type']) {
            case 'view' :
                $data['value'] = I("post.value_" . I("post.type"));
                //2019-03-22 增加添加链接验证 必须携带 http 或者 https
                $position = strpos($data['value'], 'http');
                if (!empty($data['value']) && ($position === false || $position !== 0)) {
                    $this->responseJSON(buildMessage("链接请以http://或者https://", 1));
                }
                break;
            case 'click' :
                $data['value'] = I("post.value_" . I("post.type"));
                $data['content'] = I("post.value_" . I("post.type"));
                break;
            case 'media_id' :
                $data['value'] = I("post.value_" . I("post.type"));
                $data['media_id'] = I("post.value_" . I("post.type"));
                break;
            case 'view_limited' :
                $data['value'] = I("post.value_" . I("post.type"));
                $data['media_id'] = I("post.value_" . I("post.type"));
                break;
            case 'miniprogram' :
                $data['value'] = I("post.value_" . I("post.type"));
                if(empty($data['content'])){
                    $this->responseJSON(buildMessage("请输入正确的小程序APPID", 1));
                }
               // $data['url'] = self::APPLETS_URL;
//                $this->responseJSON(buildMessage("请输入正确的小程序链接", 1));
                break;
        }

        if (empty($data['value'])) {
            $children_count = 0;
            if ($data['id'] > 0 && $data['parent_id'] == 0) {
                $condition['parent_id'] = $data['id'];
                $condition['branch_id'] = getBrowseBranchId();
                $condition['is_valid'] = 1;
                $children_count = M(CONTROLLER_NAME)->where($condition)->count();
            }
            if (($data['parent_id'] > 0) || ($data['parent_id'] == 0 && $type == 1) || $children_count == 0) {
                switch ($data['type']) {
                    case 'view' :
                        $this->responseJSON(buildMessage("请输入正确的链接", 1));
                        break;
                    case 'click' :
                        $this->responseJSON(buildMessage("请输入正确的触发关键字", 1));
                        break;
                    case 'media_id' :
                        $this->responseJSON(buildMessage("图片不能为空", 1));
                        break;
                    case 'view_limited' :
                        $this->responseJSON(buildMessage("图文信息不能为空", 1));
                        break;
                    case 'miniprogram' :
                       // $data['value'] = self::APPLETS_URL;
                        $this->responseJSON(buildMessage("请输入正确的小程序链接", 1));
                        break;
                }
            }
        }

    }

    protected function _getLastData($id)
    {
        $record = array();
        if ($id) {
            $condition["a.id"] = $id;
            $record = D(CONTROLLER_NAME)->alias("a")->field("a.*")->where($condition)->find();
        }
        $this->_before_detail($record);
        return $record;
    }

    public function syncAction()
    {
        //查询 1级菜单
        $list = D(CONTROLLER_NAME)->setDacFilter("a")->field("a.*")->where("a.is_valid=1 and a.parent_id = 0")->order('parent_id asc,sort,id asc')->select();
        //查询 2级菜单
        $menus = D(CONTROLLER_NAME)->setDacFilter("a")->field("a.*")->where("a.is_valid=1 and a.parent_id > 0")->order('parent_id asc,sort desc,id asc')->select();
        //所以菜单合并
        foreach ($menus as $key => $value) {
            $list[] = $value;
        }
        $menu_list = array();
        $root_menus = array(); //主菜单
        $is_custom = false; //是否自定义
        $default_count = 0; //默认菜单个数，只对自定义菜单有效
        foreach ($list as $key => $value) {
            //新增
            if ($list[$key]['type'] == 'view_limited') {
                $list[$key]['type'] = 'media_id';
            }
            $menu_list[$value["id"]] = &$list[$key];
            $root_menus[$value["parent_id"]][] = &$list[$key];
            if (!$is_custom) {
                $is_custom = !empty($value['group_id']);
            }
            if ($value["is_default"]) {
                $default_count++;
            }
        }

        if ($root_menus) {
            if ($is_custom) {
                if ($default_count != 1) {
                    $this->responseJSON(buildMessage("自定义菜单必须有且只有一个默认菜单", 1));
                } else {
                    $this->syncCustomMenu($root_menus, $menu_list);
                }
            } else {
                $this->syncMenu($root_menus[0], $menu_list);
            }
        }
    }

    //普通菜单
    private function syncMenu(&$root_menus, &$menu_list)
    {

        $wechat = getWeChatInstance();
        $wechat->deleteMenu(); //删除所有菜单
        $menu_buttons = array();
        foreach ($root_menus as $root_menu) { //用户，服务商,通用
            $menu_buttons["button"][] = $this->createWechatMenu($root_menu, $menu_list);
        }

        if (!$wechat->createMenu($menu_buttons)) {
            $this->responseJSON(buildMessage($wechat->errMsg, 1));
            \Think\Log::write($wechat->errMsg);
        }

        $this->responseJSON(buildMessage("OK"));
    }

    //自定义菜单
    private function syncCustomMenu(&$root_menus, &$menu_list)
    {
        $wechat = getWeChatInstance();
        //第一级菜单，用户菜单（gorup_id=106,default）|服务商菜单（gorup_id=103）|通用菜单（gorup_id=0）,子数组为二级菜单（我的，我要...)
        $group_menu_list = array();
        foreach ($menu_list as $menu) {
            if (empty($menu['parent_id'])) {
                $group_menu_list[intval($menu['group_id'])] = $menu;
            }
        }
        $menu_buttons = array();
        foreach ($group_menu_list as $group_id => $group_menu) { //用户，服务商,通用            
            $group_childs = $root_menus[$group_menu["id"]];
            foreach ($group_childs as $child) {
                $button = $this->createWechatMenu($child, $menu_list);
                $menu_buttons[$group_id]['button'][] = $button;
            }
        }
        $wechat->deleteMenu(); //删除所有菜单
        $common_menus = $menu_buttons[0];
        unset($menu_buttons[0]);
        foreach ($menu_buttons as $group_id => $value) {
            foreach ($common_menus["button"] as $common_button) {
                $menu_buttons[$group_id]["button"][] = $common_button;
            }
            if ($group_menu_list[$group_id]["is_default"]) { //默认菜单，只有一个，如果多个，就覆盖前面
                if (!$wechat->createMenu($menu_buttons[$group_id])) {
                    $this->responseJSON(buildMessage($wechat->errMsg, 1));
                    \Think\Log::write($wechat->errMsg);
                }
            }
            $matchrule["tag_id"] = strval($group_id);
            $menu_buttons[$group_id]['matchrule'] = $matchrule;
            if (!$wechat->createConditionalMenu($menu_buttons[$group_id])) {
                $this->responseJSON(buildMessage($wechat->errMsg, 1));
                \Think\Log::write($wechat->errMsg);
            }
        }
        $this->responseJSON(buildMessage("OK"));
    }

    /**
     * 对菜单进行组装
     * @param array $root_menu 一级菜单样式
     * @param array $menu_list 所以菜单
     * */
    private function createWechatMenu($root_menu, $menu_list)
    {
        $button = $this->setWechatMenuProperties($root_menu);
        if ($button['type'] == 'miniprogram') {
            return $button;
        }

        $sub_button = array();
        foreach ($menu_list as $menu) {
            if ($menu['parent_id'] == $root_menu["id"]) { //子菜单
                $sub_button[] = $this->setWechatMenuProperties($menu);
                //2019-03-22  主菜单是 view 时删除多余数据
                if ($button['type'] == 'view') {
                    unset($button['type']);
                    unset($button['url']);
                }
            }
        }

        $button['sub_button'] = $sub_button;
        return $button;
    }

    private function setWechatMenuProperties($menu)
    {
        $wechat_menu = array();
        $wechat_menu['type'] = $menu['type'];
        $wechat_menu['name'] = $menu['name'];
        switch ($menu['type']) {
            case 'view':
                $wechat_menu['url'] = $menu['value'];
                break;
            case 'media_id':
            case 'view_limited':
                $wechat_menu['media_id'] = $menu['media_id'];
                break;
            case 'miniprogram':
                //配置自己商城的小程序
//                $wxConfig = getWxOptions();
//                $wechat_menu['url'] = $menu['value'];
//                $wechat_menu['appid'] = $wxConfig['xcx_appid'];
//                $wechat_menu['pagepath'] = "pages/eshop/eshop";

                //2019-03-29 自定义小程序
                $wechat_menu['appid'] = $menu['content'];
                $wechat_menu['pagepath'] = $menu['value'];
                $wechat_menu['url'] = $menu['value'];
                break;
            default:
                $wechat_menu['key'] = $menu['value'];
                break;
        }
        return $wechat_menu;
    }

    public function downloadAction()
    {
        $wi = getWeChatInstance();
        $wx_menu = $wi->getMenu();
        echo $wi->errMsg;
        die(json_encode($wx_menu, JSON_UNESCAPED_SLASHES));
    }

    //新增 2019 2 27
    public function materialAction()
    {
        if (IS_GET) {
            if (!isset($_GET['material']) || in_array($_GET['material'], $this->_material_options) === false) {
                $material = $this->_material_default;
            } else {
                $material = $_GET['material'];
            }
            $this->assign('material', $material);
            $this->assign('obj', I("get.obj"));
            $this->display($this->_material_prefix . $material);
        }
    }

    public function deleteMenuAction()
    {
        if (IS_POST) {
            $postdata = I('post.');
            $condition['branch_id'] = getBrowseBranchId();
            $condition['_string'] = 'id = ' . $postdata['id'] . ' or parent_id = ' . $postdata['id'];
            $result = M(CONTROLLER_NAME)->where($condition)->delete();
            return $this->responseJSON($result ? buildMessage('删除成功!') : buildMessage('删除失败!', 1));
        }
    }

    public function showLinkAction()
    {
        $products = D('EShop/ComProduct')->getProductList();
        if ($products) {
            foreach ($products as $key => $value) {
                $products[$key]['url'] = str_replace('shop', 'shop' . $this->_user_session->currBranchId, SHOP_ROOT) . '/Product/productDetail/product_id/' . $value['product_id'] . '.html';
            }
        }
        $this->products = $products;
        $tweets = D('EShop/ComTweets')->getSpreadList();
        if ($tweets) {
            foreach ($tweets as $key => $value) {
                $tweets[$key]['url'] = str_replace('shop', 'shop' . $this->_user_session->currBranchId, SHOP_ROOT) . '/Spread/tweets/id/' . $value['id'] . '.html';
            }
        }
        $this->tweets = $tweets;
        $this->others = $this->getLinkShowOther();
        $this->display('show_link');
    }

    //输出默认其他列表 - 功能链接
    protected function getLinkShowOther()
    {
        $branch_id = getBrowseBranchId();
        $tmp = SHOP_ROOT;
        $tmp1 = strstr($tmp, '.caisuikx.com', true);
        $url = $tmp1 . $branch_id . strstr($tmp, '.caisuikx');
        $res = [
            [
                'title' => '商城首页',
                'url' => $url . "/Index/index"
            ],
            [
                'title' => '公司介绍',
                'url' => $url . "/Store/my"
            ],
            [
                'title' => '服务分类',
                'url' => $url . "/Index/classification"
            ],
            [
                'title' => '领券中心',
                'url' => $url . "/User/myVoucher/location/4.html"
            ]
        ];
        return $res;
    }

    public function assignPermissions($controller = CONTROLLER_NAME)
    {
        parent::assignPermissions($controller); // TODO: Change the autogenerated stub
        $this->assign('appletsUrl', self::APPLETS_URL);
        $config = D("WxConfig")->where("branch_id=" . $this->_user_session->currBranchId)->find();
        $this->assign('appletsAppid', $config['xcx_appid']);
        //获取所有的菜单设置信息
        $condition['branch_id'] = getBrowseBranchId();
        $condition['is_valid'] = 1;
        $condition['parent_id'] = 0;
        $menus = M(CONTROLLER_NAME)->where($condition)->order('parent_id asc,sort,id asc')->select();
        $condition['parent_id'] = array('gt', 0);
        $menus_childrens = M(CONTROLLER_NAME)->where($condition)->order('parent_id asc,sort desc,id asc')->select();
        $menu_arrangement = [];
        foreach ($menus as $key => $val) {
            if ($val['parent_id'] == 0) {
                $menu_arrangement[$key] = $val;
                $menu_arrangement[$key]['childrens'] = [];
                foreach ($menus_childrens as $k => $v) {
                    if ($v['parent_id'] == $val['id']) {
                        $menu_arrangement[$key]['childrens'][] = $v;
                    }
                }
            }
        }

        $this->assign('menu_json', json_encode($menu_arrangement));
    }
}
