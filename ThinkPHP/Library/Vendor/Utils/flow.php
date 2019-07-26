<?php

//流程审核0:保存，1审核中，2通过，3驳回，4, 完成 9终止
        const FLOW_SAVE = 0;
        const FLOW_CHECKING = 1;
        const FLOW_PASS = 2;
        const FLOW_REJECT = 3;
        const FLOW_COMPLETE = 4;
        const FLOW_STOP = 9;

//0:指定人员，1：职位，2角色, 3：部门
/**
 * 根据审核状态取出当前登录人员的审核中的任务列表
 */
function get_task($state = null, $page_index = 1, $paging = 10) {
    $result = array();
    $user_session = session(USER_SESSION_KEY);
    $sql = "select step.auditors,step.auditor_type,step.sub_form_id,step.sub_module,task.* from flow_task task "
            . " inner join flow_form form on form.id=task.form_id"
            . " inner join sys_branch branch on branch.id=form.branch_id"
            . " left join flow_step step on step.id=task.step_id"
            . " where branch.code like '" . $user_session->currBranchCode . "%'";
    if (isset($state)) {
        if (is_array($state)) {
            $sql.= " and task.state in(" . implode(",", $state) . ")";
        } else {
            $sql.= " and task.state=$state";
        }
    }
    $sql.= " order by task.id desc";    
    $sql.= " limit " . ($page_index - 1) * $paging ."," . $paging;
    $task_dataset = M()->query($sql);
    foreach ($task_dataset as $task) {
        if (is_myflow($task)) {
            $result[] = $task;
        }
    }
    return $result;
}

//我参与的任务
function get_my_join_task($page_index = 1, $paging = 10) {
    $result = array();
    $user_session = session(USER_SESSION_KEY);
    $sql = "select max(id) as task_id from flow_task where operater=" . $user_session->userId
            . " group by module,instance_id";
    $sql.= " limit " . ($page_index - 1) * $paging ."," . $paging;
    $task_dataset = M()->query($sql);
    if ($task_dataset) {
        $task_ids = array();
        foreach ($task_dataset as $task) {
            $task_ids[] = $task["task_id"];
        }
        $condition["id"] = array("in", $task_ids);
        $result = M("FlowTask")->where($condition)->order("operate_time desc")->select();
    }
    return $result;
}
//我发起的任务
function get_my_issue_task($page_index = 1, $paging = 10){
    $user_session = session(USER_SESSION_KEY);
    $proposer_id = $user_session->userId;
    $sql = "select a.* from flow_task a 
        inner join (select module,instance_id,max(id) as maxid from flow_task group by module,instance_id ) b on a.id=b.maxid
        inner join flow_bill fb on fb.module=a.module and fb.instance_id=a.instance_id
        where fb.proposer_id = $proposer_id
        order by a.id desc";
    $sql.= " limit ". ($page_index - 1) * $paging ."," . $paging;
    return M()->query($sql);    
}

//当前登录用户未开始的流程
function is_myflow($task) {
    $result = false;
    if ($task["state"] == FLOW_CHECKING) {
        $user_session = session(USER_SESSION_KEY);
        if ($user_session->userId == $task["operater"]) {
            return true;
        }
        if (empty($task["auditors"])) {
            $step_data = M("FlowStep")->where("id=" . $task["step_id"])->find();
            $task["auditors"] = $step_data["auditors"];
            $task["auditor_type"] = $step_data["auditor_type"];
        }
        $pos = stripos($task["auditors"], "SF:");
        if ($pos !== false) { //SF_表示来源字段值
            $field = str_ireplace("SF:", "", $task["auditors"]);
            $source_data = M($task["module"])->where("id=" . $task["instance_id"])->field($field)->find();
            $auditors = explode(",", $source_data[$field]);
        } else {
            $auditors = explode(",", $task["auditors"]);
        }
        switch ($task["auditor_type"]) {
            case 0:
                if (in_array($user_session->userId, $auditors)) {
                    $result = true;
                }
                break;
            case 1:
                if (in_array($user_session->posId, $auditors)) {
                    $result = true;
                }
                break;
            case 2:
                //交集等于用户的角色
                if ($user_session->roles == array_intersect($user_session->roles, $auditors)) {
                    $result = true;
                }
                break;
            case 3:
                if (in_array($user_session->departmentId, $auditors)) {
                    $result = true;
                }
                break;
        }
    }
    return $result;
}

function _get_flow_state_text($description, $state) {
    if ($description) {
        $state_text = explode("|", $description);
        if (count($state_text) == 3) {
            switch ($state) {
                case FLOW_CHECKING:
                    return $state_text[0];
                case FLOW_PASS:
                    return $state_text[1];
                case FLOW_REJECT:
                    return $state_text[2];
                default:
                    return $state_text[0];
            }
        }
    }
    return "";
}

function get_flow_state_text($step_id, $state) {
    if ($step_id) {
        $step_data = M("FlowStep")->where("id=$step_id")->find();
        if ($step_data) {
            return _get_flow_state_text($step_data["description"], $state);
        }
    }
    return "";
}
