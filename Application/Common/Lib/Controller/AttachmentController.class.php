<?php

namespace Common\Lib\Controller;
use Think\Controller;
class AttachmentController extends Controller {
    const TEMPLATE_ID = 'OPENTM202109783';
    protected function getUserId(){
        E("错误的用户ID");
    }

    protected function getBranchId(){
        E("错误的公司ID");
    }

    protected function getUserName(){
        return "";
    }

    public function appendAction(){
        $attach_group = I("post.attach_group");
        if (empty($attach_group)){
            // E("group不能为空!");
            $attach_group = genUniqidKey();
        }
        if (count($_FILES) > 10){
            $this->ajaxReturn(buildMessage('上传文件个数不能超过10个',1));
        }
        $config = C("Storage");
        $upload = new \Think\Upload($config);
        $file_infos = array();
		$images_infos = array();
		$files_infos = array();
        $size = 0;
        //$key为文件类型,和前端的一致,格式： word-file-0、excel-file-1...
        foreach ($_FILES as $key=>$file){
            $info = $upload->uploadOne($file);
            if ($info) {
                $file_keys = explode("-", $key);
                $file_info['url'] = $info['url'];
                $file_info["name"] = $file["name"];
                $file_info["type"] = $file_keys[0];
                $size += $info['size'];
                $file_infos[] = $file_info;
                if ($file_info["type"] == 'image'){
					$images_infos[] = $file_info;
				} else {
					$files_infos[] = $file_info;
				}
            } else {
                $this->ajaxReturn(buildMessage('上传文件失败',1));
            }
        }
        $content = I("post.content");
        if($content && $content != removeEmoji($content)){
            $this->ajaxReturn(buildMessage('暂不支持特殊符号（如表情包）',1));
        }

        $branchId = intval($this->getBranchId());
        $data["branch_id"] =  $branchId;
        $data["group"] =  $attach_group;
        $data["content"] = $content;
        $data["title"] = I("post.title");
        $data["create_time"] = time();
        $data["size"] = $size / 1000;
        $data["creater_id"] = $this->getUserId();//_user_session->userId;
        $data["user_name"] = $this->getUserName();//_user_session->userId;
        $data["images"] = json_encode($file_infos);
		$data["onlyimages"] = json_encode($images_infos);
		$data["onlyfiles"] = json_encode($files_infos);
        $creator = M("SysUser")->where("id = ".$data['creater_id'])->field("head_pic,staff_name,name,comments")->find();
        $data["user_head_pic"] = $creator['head_pic'];
        $data["staff_name"] = $creator['staff_name'];
        if ($creator['comments']) {
            $data["staff_name"] = $creator['name']."(".$creator['comments'].")";
        }else{
            $data["staff_name"] = $creator['name'];
        }

        $sysUser = M('SysUser')->where(['attach_group'=> $attach_group ])->find();
        if (!empty($sysUser)) {
            $this->isRead($sysUser['id'],0);
        }
        if ($lastId = M("ComAttachment")->add($data)) {
            $data["id"] = $lastId;
            $data["create_time_fmt"] = date("Y-m-d H:i:s", $data["create_time"] );
            if ($this->getUserId() == $data['creater_id']) {
                $data["direction"] = 2;
            } else {
                $data["direction"] = 1;
            }

//            if($this->getUserId() != $sysUser['id'] && $content){
//                $this->sendMessage($content, $sysUser['openid'], $attach_group);
//            }else if($content){
//                $where['role_ids'] = ROLE_ID_COMPANY_MANAGER;
//                $where['branch_id'] = getBrowseBranchId();
//                $openid = M('SysUser')->where($where)->getField('openid');
//                if($openid){
//                    $this->sendMessage($content, $openid, $attach_group);
//                }else{
//                    $this->ajaxReturn(buildMessage("上传成功，通知商户失败！", 1));
//                }
//            }

            $this->ajaxReturn(buildResult($data));
        }else{
            $this->ajaxReturn(buildMessage("上传失败！", 1));
        }
    }

    protected function sendMessage($reply, $openid, $attach_group){
        $message["template_id"] = getWxTemplateIdByStandardId(self::TEMPLATE_ID);
        $message["url"] = str_replace('shop', 'shop' . getBrowseBranchId(), SHOP_ROOT).'/Liuyan/index/attach_group/' . $attach_group;
        $body["first"]["value"]    = '您收到一条留言信息';
        $body["keyword1"]["value"] = '留言';
        $body["keyword2"]["value"] = $reply;
        $body["remark"]["value"] = '点击查看详情';
        $message["body"] = $body;
        $message["openid"] = $openid;
        send_wx_message($message);
    }

    public function removeAction($item_id){
        $c["branch_id"] =  $this->getBranchId();
        $c["id"] = $item_id;
        $data = M("ComAttachment")->where($c)->find();
        if ($data){
            $config = C("Storage");
            $upload = new \Think\Upload\Driver\Qiniu\QiniuStorage($config["driverConfig"]);
            $images = json_decode($data["images"],true);
            if ($images){
                foreach ($images as $img){
                    $key = $upload->getKeyFromUrl($img["url"]);
                    $upload->del($key);
                }
            }
            if (M("ComAttachment")->where($c)->delete() !== false) {
                $this->ajaxReturn(buildMessage("删除成功"));
            }
        };
        $this->ajaxReturn(buildMessage("删除失败！", 1));
    }

    public function getAttachGroupAction(){
        $this->ajaxReturn(genUniqidKey(),"EVAL");
    }

    public function listAction(){
        $attach_group = I("post.group");
        if (empty($attach_group)) {
            $filter["group"] = "0";
        }else{
            $filter["group"] = $attach_group;
        }
//        $filter["type"] = array('neq',1);	//类型，0-普通，1-沟通记录关联、2-备注记录 由于新增2把这里neq 1修改为 eq 0
		$filter["type"] = array('eq',0);
        $rows = M("ComAttachment")->field("*,FROM_UNIXTIME(create_time,'%Y-%m-%d %H:%i') as create_time_fmt")->where($filter)
        // ->order("id desc")
        ->order("id asc")
        ->select();

        foreach ($rows as $key=>$row){
            $rows[$key]["content"] = str_replace(["\n", "\n\r"], "<br/>", $row["content"]);
            $creator = M("SysUser")->where("id = ".$row['creater_id'])->field("head_pic,staff_name,name,comments")->find();
            $rows[$key]["user_head_pic"] = $creator['head_pic'];

            if ($this->getUserId() == $row['creater_id']) {
                $rows[$key]["direction"] = 2;
            } else {
                $rows[$key]["direction"] = 1;
            }

            // if (!empty($creator['staff_name'])) {
            //     $rows[$key]["staff_name"] = $creator['staff_name'];
            // }else{
            // }
            if ($creator['comments']) {
                $rows[$key]["staff_name"] = $creator['name']."(".$creator['comments'].")";
            }else{
                $rows[$key]["staff_name"] = $creator['name'];
            }
        }
        $is_delete = 1;
        $sysUser = M('SysUser')->where(['attach_group'=>$attach_group])->find();
        if (!empty($sysUser)) {
            $is_delete = 0;
        }

        $this->ajaxReturn(array("rows"=>$rows,"is_delete"=>$is_delete));
    }

    //修改已读数
    public function isRead($id = null,$type){
        $user_id = $this->getUserId();
        $branch_id = $this->getBranchId();
        if ($type == 0) {
            $condition = [];
            $condition["b.id"] = $id;
            // $condition["a.branch_id"] = $branch_id;
            $condition["a.type"] = 0;
            $condition["a.creater_id"] = array('neq',$user_id);
            $count = M('ComAttachment')
                ->alias('a')
                ->join('LEFT JOIN sys_user b ON b.attach_group = a.group')
                ->where($condition)
                ->field('b.id,b.name,b.head_pic,b.attach_group,a.content,a.create_time')
                ->count();
            $sysIsRead = M('ComIterationMessageRead')
            ->where(['user_id'=>$user_id,'object_id'=>$id,'type'=>0,'branch_id'=>$branch_id])
            ->find();
        }else{
            $count = M('ComIterationMessage')
            ->where(['status'=>1])
            ->count();
            $sysIsRead = M('ComIterationMessageRead')
            ->where(['user_id'=>$user_id,'type'=>1,'branch_id'=>$branch_id])
            ->find();
        }
        $data['object_id'] = $id;
        $data['user_id'] = $user_id;
        $data['type'] = $type;
        $data['branch_id'] = $branch_id;
        $data['count'] = $count;
        if (!empty($sysIsRead)) {
            if ($sysIsRead['count'] != $count) {
                M('ComIterationMessageRead')
                ->where(['id'=>$sysIsRead['id']])
                ->save($data);
            }
        } else {
            M('ComIterationMessageRead')->add($data);
        }
        return true;
        // $this->ajaxReturn(array('code' => 0,'message' => '操作成功'));
    }

	public function addNoteAction(){
		$attach_group = I("post.attach_group");
		if (empty($attach_group)){
			// E("group不能为空!");
			$attach_group = genUniqidKey();
		}
		if (count($_FILES) > 10){
			$this->ajaxReturn(buildMessage('上传文件个数不能超过10个',1));
		}
		$config = C("Storage");
		$upload = new \Think\Upload($config);
		$file_infos = array();
		$images_infos = array();
		$files_infos = array();
		$size = 0;
		//$key为文件类型,和前端的一致,格式： word-file-0、excel-file-1...
		foreach ($_FILES as $key=>$file){
			$info = $upload->uploadOne($file);
			if ($info) {
				$file_keys = explode("-", $key);
				$file_info['url'] = $info['url'];
				$file_info["name"] = $file["name"];
				$file_info["type"] = $file_keys[0];
				$size+= $info['size'];
				$file_infos[] = $file_info;
				if ($file_info["type"] == 'image'){
					$images_infos[] = $file_info;
				} else {
					$files_infos[] = $file_info;
				}
			} else {
				$this->ajaxReturn(buildMessage('上传文件失败',1));
			}
		}
		$content = I("post.content");
		if($content && $content != removeEmoji($content)){
			$this->ajaxReturn(buildMessage('暂不支持特殊符号（如表情包）',1));
		}

		$data["branch_id"] =  $this->getBranchId();
		$data["group"] =  $attach_group;
		$data["content"] = $content;
		$data["title"] = I("post.title");
		$data["type"] = 2; //备注记录
		$data["create_time"] = time();
		$data["size"] = $size / 1000;
		$data["creater_id"] = $this->getUserId();//_user_session->userId;
		$data["user_name"] = $this->getUserName();//_user_session->userId;
		$data["images"] = json_encode($file_infos);
		$data["onlyimages"] = json_encode($images_infos);
		$data["onlyfiles"] = json_encode($files_infos);
		$creator = M("SysUser")->where("id = ".$data['creater_id'])->field("head_pic,staff_name,name,comments")->find();
		$data["user_head_pic"] = $creator['head_pic'];
		$data["staff_name"] = $creator['staff_name'];
		if ($creator['comments']) {
			$data["staff_name"] = $creator['name']."(".$creator['comments'].")";
		}else{
			$data["staff_name"] = $creator['name'];
		}

		if ($lastId = M("ComAttachment")->add($data)) {
			$data["id"] = $lastId;
			$data["create_time_fmt"] = date("Y-m-d H:i:s", $data["create_time"] );
			if ($this->getUserId() == $data['creater_id']) {
				$data["direction"] = 2;
			} else {
				$data["direction"] = 1;
			}

			$this->ajaxReturn(buildResult($data));
		}else{
			$this->ajaxReturn(buildMessage("上传失败！", 1));
		}
	}

	public function noteListAction(){
		$attach_group = I("post.group");
		if (empty($attach_group)) {
			$filter["group"] = "0";
		}else{
			$filter["group"] = $attach_group;
		}
		$filter["type"] = array('eq',2); //类型，0-普通，1-沟通记录关联、2-备注记录
		$rows = M("ComAttachment")->field("*,FROM_UNIXTIME(create_time,'%Y-%m-%d %H:%i') as create_time_fmt")->where($filter)
			->order("id asc")
			->select();

		foreach ($rows as $key=>$row){
			$rows[$key]["content"] = str_replace(["\n", "\n\r"], "<br/>", $row["content"]);
			$creator = M("SysUser")->where("id = ".$row['creater_id'])->field("head_pic,staff_name,name,comments")->find();
			$rows[$key]["user_head_pic"] = $creator['head_pic'];

			if ($this->getUserId() == $row['creater_id']) {
				$rows[$key]["direction"] = 2;
			} else {
				$rows[$key]["direction"] = 1;
			}

			if ($creator['comments']) {
				$rows[$key]["staff_name"] = $creator['name']."(".$creator['comments'].")";
			}else{
				$rows[$key]["staff_name"] = $creator['name'];
			}
		}
		$is_delete = 1;
		$sysUser = M('SysUser')->where(['attach_group'=>$attach_group])->find();
		if (!empty($sysUser)) {
			$is_delete = 0;
		}

		$this->ajaxReturn(array("rows"=>$rows,"is_delete"=>$is_delete));
	}



}