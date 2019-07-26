function openEditor(title, content, callback,url){
    if(url == undefined){
        url = "Index/template";
    }
    var dlg = $.dialog({
        title: title,
        autoSize: true,
        content: "url:"+url,
        lock: false,
        max: true,
        min: false,
        cancel:false,
        init:function(){
             this.content.document.getElementById("source-content").innerHTML = content;
        },
        button: [
            {
                name: '确定',
                callback: function () {
                    var ret = dlg.content.document.getElementById("previews").innerHTML;
                    if (callback != undefined){
                        callback.call(this, ret);
                    }
                    return true;
                }
            },
            {
                name: '取消',
                callback: function () {
                    if (confirm("确定放弃编辑？")){
                        return true;
                    }else{
                        return false
                    }
                }
            }]
    });
    dlg.max();
}
function openMAP(title, ini_data ,callback){
    var dlg = $.dialog({
        title: title,
        autoSize: true,
        content: "url:Index/map",
        lock: false,
        max: true,
        min: false,
        cancel:true,
        init:function(){
            this.content.document.getElementById("address").value = ini_data.address;
            this.content.document.getElementById("location").value = ini_data.location;
        },
        ok: function(){
            var address = dlg.content.document.getElementById("address").value;
            var location = dlg.content.document.getElementById("location").value;
            if(location == ''){
                alert('请输入地址,我们将对其进行解析!!');
                return false;
            }
            var data ={
                address : address,
                location : location
            }
            if (callback != undefined){
                callback.call(this, data);
            }
            return true;
        },
        cancelVal: '关闭'
    });
    dlg.max();
}
function formatWithdrawalStatus(value ,row, index){
    value = String(value);
    switch (value){
        case "0":
            return "审核中";
        case "1":
            return "已同意";
        case "2":
            return "审核失败"
    }
}

function formatWithdrawalOperate(value ,row, index){
    if (row.status == "0"){
        return "<a href='javascript:;' onclick='withdrawalCheck(1,"+ index + "," + row.id + ")'>同意</a>&nbsp;&nbsp;&nbsp;<a href='javascript:;' onclick='withdrawalCheck(0,"+ index + "," + row.id + ")'>拒绝</a>";
    }else{
        return "";
    }
}

function withdrawalCheck(agree, row_index, id){
    var title = (agree == 1)?"确定同意此提现申请？":"确定拒绝此提现申请？"
    $.dialog.confirm(title, function () {
        $.post("/DistributionWithdrawal/check", {id: id, agree: agree}, function(result){
            if (result.code == 0){
                var _grid = getDataGrid("DistributionWithdrawal");
                _grid.datagrid("updateRow", {index: row_index, row:result.message});
                $.dialog.tips("审核完成！");
            }else{
                $.dialog.tips(result.message);
            }
        },"json");
    });
}

function formatIncomeOperate(value ,row, index){
    value = String(row.status);
    if (value == "0"){
        return "<a href='javascript:;' onclick='unfrozenCommision("+ index + "," + row.id + ")'>解冻</a>";
    }else{
        return "";
    }

}

function unfrozenCommision(row_index, id){
    var title = "确定解冻此奖金？"
    $.dialog.confirm(title, function () {
        $.post("/DistributionIncome/unfrozenCommision", {id: id}, function(result){
            if (result.code == 0){
                var _grid = getDataGrid("DistributionIncome");
                _grid.datagrid("updateRow", {index: row_index, row:result.message});
                $.dialog.tips("解冻成功！");
            }else{
                $.dialog.tips(result.message);
            }
        },"json");
    });
}

/**
 * 进入店铺
 * @param value
 * @param row
 * @param index
 */
function  formate_branch_operation(value ,row, index){
    //location.href="http://www.baidu.com"
    var result = "";
    if (row.leader_id != 0 && row.leader_id != null) {
        result = $.format("<a href='javascript:;' onclick='enterShop({0})'>登录后台</a>", [row.id]);
    }
    return result;
}

function enterShop(shop_id){
    $.post("/Login/enterShop", {shop_id: shop_id}, function(result){
        if (result.code == 0){
            $.dialog.tips("登录校验中...");
            setTimeout("location.href='/Index'", 2000);
        }
    },"json");
}

function showAttachment(attach_group){
    openAttachmentForm("沟通记录",[{text:"沟通记录",attach_group: attach_group}]);
}

function format_valid_field(value, row, index){
    if (value == "1"){
        return "<a href='javascript:;' onclick='set_valid_field(" + row.id + "," + index + ",0)'><i class='fa fa-lg fa-check-circle-o' style='padding:5px'></i></a>";
    }else{
        return "<a href='javascript:;' onclick='set_valid_field(" + row.id + ","+ index + ",1)'><i class='fa fa-lg fa-check-circle-o' style='color:#ccc;padding:5px'></i></a>";
    }
}

function set_valid_field(id, index, is_valid){
    $.post("/WxTemplateMessage/setValidField",{id:id, is_valid: is_valid}, function(result){
        if (result.code == 0){
            $("#WxTemplateMessage-datagrid").datagrid("updateRow", {index: index, row:{is_valid: is_valid}});
        }else {
            $.dialog.tips(result.message);
        }
    },"json");
}

function action_show_batch_add_dlg(frameId, controller) {
    if (controller == undefined){controller = frameId;}
    createDialog("/"+ controller +"/batchAdd", "批量添加");
}

function action_batch_add(frameId, controller) {
    var rows = $("input.easyui-validatebox");
    var i=rows.length;
    var id_values = [];
        $(rows).each(function(){
            if(!($.trim(this.value) == "" || $.trim(this.value) == null || $.trim(this.value) == undefined)){
                id_values.push({value: this.value});
                i--;
            }
        });
    if(i==rows.length){
        $.dialog.tips("不能为空！");
        return false;
    }
    if (controller == undefined){controller = frameId;}
    showMaskLayer();
    $.post(controller + "/batchAdd", {data:id_values}, function (result) {
        hideMaskLayer();
        if (result.code == 0) {
            closeDialog();
            $.dialog.tips("批量添加成功！");
            getDataGrid(frameId).datagrid("reload");
        }else{
            $.dialog.tips(result.message);
        }
    },"json").error(function(){
        hideMaskLayer();
    });
    
}
function openAttachmentForm(title, tabs, callback){
    //$.get("/Index/attachment",
    $.get("/ComAttachment/noterecord",
        function (result, status) {
            var active = 0;
            $(tabs).each(function (index) {
                if (this.active == true){
                    active = index;
                }
            });
            var data = {active: active, tabs: tabs, callback: callback};
            var options = {id: "attachment_dialog",title: title, content:result, autoSize: true,data: data,lock: true,max: false,min: false};
            $.dialog(options);
        }
    );
}



function openAttachForm(title, close_callback){
    $.get("/Index/attachment",
        function (result, status) {
            var options = {id: "attach_dialog",title: title, content:result, autoSize: true,lock: true,max: false,min: false,close:close_callback};
            $.dialog(options);
        }
    );
}

function easyui_combobox(target, data, value, onSelect){
    var options = {
        editable:false,
        valueField:'id',
        textField:'name',
        onLoadSuccess:function(){
            if (value !== undefined) {
                $(this).combobox("setValue", value);
            }else{
                var valDatas = $(this).combobox('getData');
                if (valDatas) {
                    $(this).combobox("setValue", valDatas[0]["id"]);
                }
            }
        }
    };
    if (onSelect != undefined){
        options["onSelect"] = onSelect;
    }
    if (typeof(data) == "string"){
        options["url"] = data;
    }else{
        options["data"] = data;
    }
    $(target).combobox(options);
}