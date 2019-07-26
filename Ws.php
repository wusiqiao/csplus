<?php

class Ws{
    const EVENT_ERROR = 'error';  //错误事件
    const EVENT_MSG = 'msg';    //消息通知事件
    const EVENT_GROUP_ADD = 'groupAdd'; //群聊人员新增
    const EVENT_GROUP_DEL = 'groupDel'; //群聊人员删除
    const EVENT_NOTICE = 'notice'; //消息通知
    const EVENT_MEMBER_LOGIN = 'memberLogin'; //会员登录通知
    const EVENT_MEMBER_QUIT = 'memberQuit';   //会员退出
    const WEHCAT_NOTICE_URL = 'http://yyzs.caisuikx.com/MsgGroupMember/sendWechatMsg';

    const HOST = '0.0.0.0';
    const PORT = 19999;
    protected $ws = null;

    public function __construct(){
        try {
            $this->ws = new swoole_websocket_server(self::HOST, self::PORT, SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);
            $this->ws->set([
                'worker_num' => 2,
                'task_worker_num' => 4,
                'ssl_cert_file'=> '/usr/local/nginx/conf/cert/cskx.pem',
                'ssl_key_file'=> '/usr/local/nginx/conf/cert/cskx.key'
            ]);

            $this->ws->on('workerStart', [$this, 'onWorkerStart']);
            $this->ws->on('open', [$this, 'onOpen']);
            $this->ws->on('message', [$this, 'onMessage']);
            $this->ws->on('close', [$this, 'onClose']);
            $this->ws->on('task', [$this, 'onTask']);
            $this->ws->on('finish', [$this, 'onFinish']);
            $this->ws->start();
            Cache::getInstance()->flushDB();
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function onWorkerStart(swoole_websocket_server $serv, $worker_id){

    }

    public function onOpen(swoole_websocket_server $server, $request){
        $fd  = $request->fd;
        $get = $request->get;
        $userId   = intval((isset($get['user_id']) ? $get['user_id'] : 0));
        $branchId = intval((isset($get['branch_id']) ? $get['branch_id'] : 0));
        if($userId <= 0){
            $server->close($fd);
            return;
        }

        if($branchId > 0){
            $this->addMember($get['user_id'], $fd, $branchId);
            $data['event']  = self::EVENT_MEMBER_LOGIN;
            $data['userId'] = $get['user_id'];
            $data['branchId'] = $branchId;
            $data['fd'] = $fd;
            $server->task($data);
        }
    }

    public function onMessage(swoole_websocket_server $server, $frame){
        $data = $frame->data;
        $data = $data ? json_decode($data, true) : $data;
        $fd = $frame->fd;
        if (empty($data)) {
            return $this->sendError($server, $fd, '请求格式错误!');
        }

        $data['fd'] = $fd;
        $server->task($data);
    }

    public function onClose(swoole_websocket_server $server, $fd){
        $this->delMember($fd);
    }

    public function onTask(swoole_websocket_server $serv, $taskId, $srcWorkerId, $data){
        $fd = $data['fd'];
        $event = isset($data['event']) ? $data['event'] : null;
        switch ($event) {
            case self::EVENT_MSG :
                if (!$this->eventMsg($serv, $data, $message)) {
                    $this->sendError($serv, $fd, 'msg消息通知失败!');
                }

                break;
            case self::EVENT_GROUP_ADD :
                break;
            case self::EVENT_GROUP_DEL :
                break;
            case self::EVENT_MEMBER_LOGIN :
                $this->eventMemberLogin($serv, $data);
                break;
            case self::EVENT_MEMBER_QUIT :
                break;
            default :
                $this->sendError($serv, $fd, '未知事件');
                break;
        }

        return $data;
    }

    public function onFinish(swoole_websocket_server $serv, $taskId, $data){
        //写任务日志;
    }

    private function sendError($server, $fd, $message = '请求失败!'){
        $this->sendData($server, $fd, null, self::EVENT_ERROR, $message);
    }

    private function eventMemberQuit($serv, $data){

    }

    private function eventMemberLogin($serv, $data){
        $userId   = isset($data['userId']) ? $data['userId'] : 0;
        $branchId = isset($data['branchId']) ?$data['branchId'] : 0 ;
        if($userId < 1 || $branchId < 1){
            return false;
        }

        foreach($serv->connections  as $fd){
            $uid = $this->getMemberUserId($fd);
            if($userId == $uid){
                continue;
            }

            if($this->getMemberBranchId($uid) != $branchId){
                continue;
            }

            $this->sendData($serv, $fd, $data, self::EVENT_MEMBER_LOGIN);
        }
    }

    private function validateFds($serv, $fd, $newId, $logId){
        if (!$serv->exist($fd)) {
            $this->delMember($fd, $newId);
            return false;
        }

        if($this->existsClient($logId, $fd, $newId)){
           return false;
        }

        return true;
    }

    private function eventMsg($serv, $list, &$msg){
        $data = isset($list['data']) ? $list['data'] : null;
        if (empty($data)) {
            $msg = '数据丢失';
            return;
        }

        $acceptIds = $data['acceptIds'];
        if(isset($acceptIds[0]['id'])){
            $acceptIds = array_column($acceptIds, 'id');
        }
        $userId = $data['send_id'];
        foreach ($acceptIds as $key => $acceptId) {
            $fds = $this->getMemberFds($acceptId);
            foreach ($fds as $fd) {
                if(! $this->validateFds($serv, $fd, $acceptId, $userId)){
                    continue;
                }

                $acceptIds[$key] = null;
                $this->sendData($serv, $fd, $list, self::EVENT_MSG);
            }
        }

        $acceptIds = array_filter($acceptIds);
        $curlData['type'] = $data['contents_type'];
        $curlData['groupId'] = $data['msg_group_id'];
        $curlData['branchId'] = $data['branch_id'];
        $curlData['wait'] = array_filter($acceptIds);
        $curlData['contents'] = $data['contents'];
        $result = $this->curl($curlData);
        return true;
    }

    private function curl($data){
        $ch = curl_init(self::WEHCAT_NOTICE_URL);
        if (stripos(self::WEHCAT_NOTICE_URL, "https://") === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在
            //curl_setopt($ch, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }

        curl_setopt($ch, CURLOPT_POST, true); // 发送一个常规的Post请求
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, true)); // Post提交的数据包
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 设置超时限制防止死循环
        curl_setopt($ch, CURLOPT_HEADER, false); // 显示返回的Header区域内容
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 获取的信息以文件流的形式返回
        $result = curl_exec($ch); // 执行操作
        curl_close($ch); // 关闭CURL会话
        if ($result !== false) {
            $result = json_decode($result, true);
        }

        return $result;
    }

    private function existsClient($userId, $fd, $acceptId){
        $cache = Cache::getInstance();
        if($cache->get('fd_' . $fd) == $userId){
            $cache->srem('member_' . $acceptId, $fd);
            return true;
        }

        return false;
    }

    private function sendData($server, $fd, $data = null, $event = self::EVENT_MSG, $message = '请求成功!'){
        $list['event'] = $event;
        $list['message'] = $message;
        $list['data'] = $data;
        if($server->exist($fd)){
            $server->push($fd, json_encode($list, true));
        }
    }

    private function getBranchMember($branchId){
        $cache = Cache::getInstance();
        $userIds = $cache->smembers('branchId_'. $branchId);

        return empty($userIds) ? [] : $userIds;
    }
    private function getMemberUserId($fd){
        $cache = Cache::getInstance();
        return $cache->get('fd_' . $fd);
    }

    private function getMemberBranchId($userId = null){
        $cache = Cache::getInstance();
        return $cache->get('member_branchId_' . $userId);
    }

    private function getMemberFds($userId){
        $cache = Cache::getInstance();
        $fds = $cache->smembers('member_' . $userId);
        return empty($fds) ? [] : $fds;
    }

    private function addMember($userId, $fd, $branchId){
        $cache = Cache::getInstance();
        $cache->sadd('member_' . $userId, $fd);
        $cache->sadd('branchId_' . $branchId, $userId);
        $cache->set('fd_' . $fd, $userId);
        $cache->set('member_branchId_' . $userId, $branchId);
    }

    private function delMember($fd, $userId = null){
        $cache =Cache::getInstance();
        $userId = $userId ? $userId : $cache->get('fd_' . $fd);
        $cache->srem('member_' . $userId, $fd);
        $cache->del('fd_' . $fd);
        if(empty($this->getMemberFds($userId))){
            $branchId = $cache->get('member_branchId_' . $userId);
            $cache->srem('branchId_' . $branchId, $userId);
        }
    }
}

new Ws();

class Cache{
    const HOST = '127.0.0.1';
    const PORT = 6379;
    const DB = 1;
    private $redis = null;

    protected function __construct(){
        try {
            $this->redis = new Redis();
            $this->redis->connect(self::HOST, self::PORT);
            $this->redis->select(self::DB);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function __call($name, $arguments){
        $count = count($arguments);
        if ($count == 1) {
            return $this->redis->$name($arguments[0]);
        }

        if ($count == 2) {
            return $this->redis->$name($arguments[0], $arguments[1]);
        }

        if ($count == 3) {
            return $this->redis->$name($arguments[0], $arguments[1], $arguments[2]);
        }

        if ($count == 4) {
            return $this->redis->$name($arguments[0], $arguments[1], $arguments[2],$arguments[3]);
        }
    }

    public static function getInstance(){
        if (!(self::$instance instanceof self)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private static $instance = null;
}