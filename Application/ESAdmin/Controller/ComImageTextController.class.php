<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class ComImageTextController extends DataController {

    public function templateAction($id = null) {
        $model = M(CONTROLLER_NAME)->where("id=$id")->find();
        if ($model) {
            $list = M(CONTROLLER_NAME)->field("id,name,case when id=$id then 'active' else '' end as active")->order("sort")->select();
            $this->assign("list", $list);
        }
        $this->display();
    }

    public function getDetailAction($id = null) {
        $detail_list = M("ComImageTextDetail")->where("parent_id=$id")->order("id desc")->select();
        foreach ($detail_list as $key => $value) {
            $detail_list[$key]["content"] = html_entity_decode($value["content"]);
        }
        $this->responseJSON(buildResult($detail_list));
    }

    public function removeDetailAction($detail_id = null) {
        if ($detail_id) {
            M("ComImageTextDetail")->where("id=$detail_id")->delete();
            $this->responseJSON(buildMessage("删除成功"));
        } else {
            $this->responseJSON(buildMessage("错误编号", 1));
        }
    }

    //根据传进来的内容分析，如果是模板,id标示未hs-imagetext-id
    //
    public function updateDetailAction($parent_id, $content) {
        if (empty($content)) {
            $this->responseJSON(buildMessage("数据不能为空！", 1));
        }
        $content_list =  array();
        $details = array();
        $preg = "/<script[\s\S]*?<\/script>/i";
        $content = preg_replace($preg, "", $content);
        Vendor("phpQuery.phpQuery");
        $doc = \phpQuery::newDocumentHTML($content);
        \phpQuery::selectDocument($doc);
        foreach (pq("section.fzneditor") as $value) {
            $hs_id = $value->getAttribute("hs-imagetext-id"); //如果有带/hs-imagetext-id表示编辑
            if ($hs_id) {
                $details[] = $hs_id;
            }
            foreach (pq('img') as $img) {
                $src  = $img -> getAttribute('src');
                if (stripos($src, "http") === 0){ //远程文件
                    $file = $this->downloadRemoteFile($src);
                    $img->setAttribute("src", $file);
                }
            }
            $content_list[] = pq($value)->html();
        }
        //有detail表示是编辑
        if ($details) {
            $condition["id"] = array("in", $details);
            $condition["parent_id"] = $parent_id;
            M("ComImageTextDetail")->where($condition)->delete();
        }
        $is_merge = I("post.is_merge");
        if ($is_merge == 1){      //合并               
        //自行编辑，不是从http://editor.fzn.cc/复制的。
            if (empty($content_list)){
                $data["content"] = htmlentities($content);
            }else{
               $data["content"] = htmlentities(implode("<p><br></p>", $content_list)); 
            }
            $data["parent_id"] = $parent_id;
            if (M("ComImageTextDetail")->add($data) !== false) {
                $this->responseJSON(buildMessage("保存成功！"));
            }
        }else{
            if ($content_list){
                $datas = array();
                foreach ($content_list as $content){
                    $data["parent_id"] = $parent_id;
                    $data["content"] = htmlentities($content);//"<section class='fzneditor'>".$content."</section>"
                    $datas[] = $data;
                }
                if (M("ComImageTextDetail")->addAll($datas) !== false) {
                    $this->responseJSON(buildMessage("保存成功！"));
                }
            }
        }
    }

    private function downloadRemoteFile($url) {
        $file_info = explode(".", $url);
        $path = dirname(dirname(__FILE__)) . "/Upload/ueditor/image/" . date("Ymd");
        $local = $path . "/" . md5($url) . "." . $file_info[count($file_info) - 1];
        mkdir($path, 0777, true);
        if (!is_file($local)) {
            $cp = curl_init($url);
            $fp = fopen($local, "w");
            curl_setopt($cp, CURLOPT_FILE, $fp);
            curl_setopt($cp, CURLOPT_HEADER, 0);
            curl_exec($cp);
            curl_close($cp);
            fclose($fp);
        }
        $local = str_replace(realpath("./"), "", $local);
        return str_replace("\\","/", $local);
    }
    
     protected function _checkAllowPermits($action){
        return $action == "list";
    }
    

}
