<?php

namespace Common\Lib\Controller;

use Think\Controller;
use Think\Exception;
use Yanhui\Model\UserSessionModel;

class ControllerBase extends Controller {

    protected $_user_session = null;
    protected $_permission_name = null; //功能权限关键字
    protected $userId; //登录用户ID
    protected $userName; //登录用户名
    protected $companyId; //登录用户公司ID
    protected $companyName; //登录用户公司
    protected $isAdmin; //超级用户
    protected $isPlatformUser;

    public function _initialize() {
        $allow_pass = false;
        $this->_user_session = session(USER_SESSION_KEY);
        if ($this->_user_session) {
            $this->userId = $this->_user_session->userId;
            $this->userName = $this->_user_session->userName;
            $this->companyId = $this->_user_session->currBranchId;
            $this->companyName = $this->_user_session->currBranchName;
            $this->isAdmin = $this->_user_session->isAdmin;
            $this->isPlatformUser = $this->_user_session->isPlatformUser;
            $allow_pass = $this->isAdmin;
            if (empty($this->_permission_name)) {
                $this->_permission_name = CONTROLLER_NAME;
            }
            if (!$this->isAdmin) {

                if (in_array(ACTION_NAME,explode('|',$this->_user_session->permissionList[ACCESS_SYS_ACTIONS_KEY]))=== false) { //不在权限范围，放行
                    $allow_pass = true;
                } else {
                    $menuList = $this->_user_session->permissionList[ACCESS_MENUS_KEY];
                    if ($menuList[$this->_permission_name] && $menuList[$this->_permission_name]["allow"]) {
                        if ($menuList[$this->_permission_name][ACCESS_MENU_ACTIONS_KEY][ACTION_NAME]) {
                            $allow_pass = true;
                        }
                    }else{
                        $allow_pass = $this->_checkAllowPermits(ACTION_NAME); 
                    }
                }
            }
        }
        if (!$allow_pass) {
            if (IS_AJAX && !IS_GET) {
                if ($this->_user_session){
                    $this->responseJSON(buildMessage("无此功能操作权限！", 1));
                } else {
                    $this->responseJSON(buildMessage("登陆超时,请重新登陆！", 1));
                }
            } else {
                if ($this->_user_session){
                    die("无此功能操作权限");
                } else {
                    die("登陆超时,请重新登陆！");
                }
            }
        }
    }
    
    //除了设置权限，还可以在controller里面定时特殊情况,比如可以取数据，但是不能查看编辑的情况
    protected function _checkAllowPermits($action){
        return false;
    }
    
    public function assignPermissions($controller = CONTROLLER_NAME) {
        $permissions = array();
        $menuList = $this->_user_session->permissionList[ACCESS_MENUS_KEY];
        if ($menuList[$this->_permission_name] && ($this->_user_session->isAdmin || $menuList[$this->_permission_name]["allow"])) {
            $permissions = $menuList[$controller][ACCESS_MENU_ACTIONS_KEY];
        }
        if ($this->_user_session->isAdmin) {
            $permissions["_IS_ADMIN_"] = 1;
        }
        $this->assign("permissions", $permissions);
    }

    protected function responseJSON($data) {
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (!$jsonData){
            $jsonData = json_encode(try_convert_to_utf8($data),JSON_UNESCAPED_UNICODE);
            if (!$jsonData) {
                $this->ajaxReturn(json_encode(buildMessage(json_last_error_msg()), 1), "JSON");
            }
        }
        $this->ajaxReturn($data, "JSON");
    }

    protected function _parsefilter(&$filter) {
        parseQueryParams($filter);
    }

    protected function _parseOrder(&$order) {
        parseQueryOrder($order);
    }

    public function getQrcodeAction($code = null) {
        if ($code) {
            qrcode($code);
        }
    }

    //返回查询条件中是否有关联表的查询字段，查看key是否包含除了a.开头的其他字段
    protected function  hasRelationCondition($_filter){
        foreach ($_filter as $key=>$field){
            if ($key == "_complex"){ //混合查询
                foreach ($field as $ck=>$cf){
                    $dot_char_position = stripos($ck, ".");
                    if ($dot_char_position && substr($ck, 0, $dot_char_position) !== "a"){
                        return true;
                    }
                }
            }else{
                $dot_char_position = stripos($key, ".");
                if ($dot_char_position && substr($key, 0, $dot_char_position) !== "a"){
                    return true;
                }
            }
        }
        return false;
    }

    public function exportAction() {
        Vendor('PHPExcel18.PHPExcel');
        Vendor('PHPExcel18.PHPExcel.Writer.Excel2007');
        $objPHPExcel = new \PHPExcel();
        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $file_name = $this->doExportAction($objPHPExcel);
        $userBrowser = $_SERVER['HTTP_USER_AGENT'];
        if (preg_match('/MSIE/i', $userBrowser)) {
            $file_name = urlencode($file_name);
        }
        $file_name = iconv('UTF-8', 'GBK//IGNORE', $file_name);
        $this->setExcelHeader($file_name);
        $objWriter->save('php://output');
        unset($objWriter);
        unset($objPHPExcel);
    }

    protected function setExcelHeader($fileName) {
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename="' . $fileName . '"');
        header("Content-Transfer-Encoding:binary");
    }

    /**交换顺序(sort)
     * @param $id_front 排前面的id
     * @param $id_behind 排后面的id
     */
    public function exchangeOrderAction($id_front, $id_behind){
        if ($id_front && $id_behind){
            //如果有sort=0的行，重新排序一次
            D(CONTROLLER_NAME)->initialSortField($this->_user_session->currBranchId);
            $list = M(CONTROLLER_NAME)->where(array("id"=>array("in", array($id_front,$id_behind))))->select();
            if (count($list) == 2){
                $sort_values = array();
                foreach ($list as $value){
                    if ($value["id"] == $id_front){
                        $sort_values["front"] = intval($value["sort"]);
                    }else{
                        $sort_values["behind"] = intval($value["sort"]);
                    }
                }
                if ($sort_values["front"] == $sort_values["behind"]){
                    $sort_values["front"] = $sort_values["front"] - 1;
                    if ($sort_values["front"]  < 0){
                        $sort_values["front"]  = 0;
                        $sort_values["behind"] = 1;
                    }
                }else{
                    $sort_behind = $sort_values["behind"];
                    $sort_values["behind"] = $sort_values["front"];
                    $sort_values["front"] = $sort_behind;
                }
                M(CONTROLLER_NAME)->where("id=$id_front")->setField("sort", $sort_values["front"]);
                M(CONTROLLER_NAME)->where("id=$id_behind")->setField("sort", $sort_values["behind"]);
            }
            $this->ajaxReturn(array("code"=>0));
        }else{
            $this->ajaxReturn(array("code"=>1));
        }
    }

    /**
	 * 文件上传
	 */
	public function uploadFieldsAction()
	{
		try{
			if (empty($_FILES)){
				throw new Exception('请选择文件!');
			}
			$config = C("Storage");
			$upload = new \Think\Upload($config);

			$lists = $upload->upload();

			$data = array();
			if ($lists){
				foreach($lists as $val){
					$data[] = array('name'=>$val['key'],'url'=>$val['url']);
				}
			} else {
				throw new Exception($upload->getError());
			}
		    $this->ajaxReturn(buildMessage($data));
		} catch (Exception $e) {
		    $this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}
    }
    
    /**
	 * 获取省市区列表
	 */
	public function getPCQListAction()
	{
		$parent_id = I('id',0);
		$level = M('SysRegion')->where(array('id'=>array('eq',$parent_id)))->getField('level');
		if ($parent_id == 0){
			$level = 1;
		} else {
			$level +=1;
		}
		$list = M('SysRegion')->field('id,name,code')->where(array('parent_id'=>array('eq',$parent_id)))->select();
		$this->ajaxReturn(array('level'=>$level,'list'=>$list));
    }
}
