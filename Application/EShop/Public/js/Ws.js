/**
 * Created by Administrator on 2019-07-24.
 */


var Socket = function (branchid, userid, callback) {
    var url = 'wss://120.26.97.87:19999?';
    var timeoutId = null;
    var branchId = branchid;
    var userId = userid;
    const EVENT_ERROR = 'error';  //错误事件
    const EVENT_MSG = 'msg';    //消息通知事件
    const EVENT_GROUP_ADD = 'groupAdd'; //群聊人员新增
    const EVENT_GROUP_DEL = 'groupDel'; //群聊人员删除
    const EVENT_NOTICE = 'notice'; //消息通知
    const EVENT_MEMBER_LOGIN = 'memberLogin'; //会员登录通知
    const EVENT_MEMBER_QUIT = 'memberQuit';   //会员退出

    var dataWait = []; //未发送的数据
    var startUp = function () {
        if (window.socket == null || !window.socket || window.socket == undefined) {
            try {
                var wsUrl = url + 'branch_id=' + branchId + '&user_id=' + userId;
                var socket = new WebSocket(wsUrl);
                console.log(socket)
                socket.onopen = onOpen;
                socket.onmessage = onMessage;
                socket.onclose = onClose;
                socket.onerror = onError;
                window.socket = socket;
            } catch (e) {
                console.log(e)
                window.socket = null;
            }
        }
    }

    var onError = function (e) {
        console.log(e)
    }

    var onOpen = function () {
        if (timeoutId != null) {
            clearTimeout(timeoutId);
            timeoutId = null;
        }
    }

    var onClose = function (e) {
        console.log(e)
        window.socket = null;
        reStart();
    };

    var onMessage = function (data) {
        data = JSON.parse(data.data);
        if (callback == undefined) {
            return;
        }
        var event = data.event;
        data = data.data.data;
        switch (event) {
            case EVENT_ERROR :  //错误事件
                if (typeof  callback.error != 'function') {
                    return;
                }
                callback.error(data)
                break;
            case EVENT_MSG :     //消息通知事件
                if (typeof  callback.msg != 'function') {
                    return;
                }
                callback.msg(data);
                break;
            case EVENT_GROUP_ADD: //群聊人员新增
                if (typeof  callback.groupAdd != 'function') {
                    return;
                }
                callback.groupAdd(data)
                break;
            case EVENT_GROUP_DEL: //群聊人员删除
                if (typeof  callback.groupDel != 'function') {
                    return;
                }
                callback.groupDel(data)
                break;
            case EVENT_NOTICE: //消息通知
                if (typeof  callback.notice != 'function') {
                    return;
                }
                callback.notice(data)
                break;
            case EVENT_MEMBER_LOGIN: //会员登录通知
                if (typeof  callback.memberLogin != 'function') {
                    return;
                }
                callback.memberLogin(data);
                break;
            case EVENT_MEMBER_QUIT:  //会员退出
                if (typeof  callback.memberQuit != 'function') {
                    return;
                }
                callback.memberQuit(data)
                break;
        }
    }

    var init = function () {
        startUp(branchid, userid);
    }

    var sendWaitData = function () {
        if (dataWait.length > 0) {
            var data = dataWait.shift();
            send('msg', data);
            if (dataWait.length > 0 && window.socket != null) {
                window.setTimeout(sendWaitData, 3000);
            }
        }
    }

    var sendWechatMsg = function(data){
        if (window.$) {
            $.post('/Talks/sendWechatMsg', {
                type: data.contents_type,
                groupId: data.msg_group_id,
                branchId: data.branch_id,
                wait: data.acceptIds,
                contents: data.contents,
            });
        } else {
            dataWait.push(data);
        }
    }
    var send = function (event, data) {
        console.log(window.socket)
        if (window.socket == null) {
            sendWechatMsg(data)
        } else {
            var list = {
                event: event,
                data: data
            };

            try {
                window.socket.send(JSON.stringify(list));
            } catch (e) {
                sendWechatMsg(data);
            }
        }
    }

    var reStart = function () {
        if (window.socket == null) {
            timeoutId = setTimeout(startUp, 5000);
        }
    }

    init();

    return {
        send: send
    }
}