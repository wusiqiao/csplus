var formCache = [];
 
function changeUserPwd(frameId) {
    var rows = getDataGrid(frameId).datagrid('getSelections');
    if (rows.length !== 1) {
        $.dialog.tips('请选择一条记录');
        return false;
    }
    location.href = frameId + "/AdminChangePwd/" + rows[0].id;
}
function getDataGrid(frameId) {
    return $("#" + frameId + "-datagrid");
}

function getDataForm(frameId) {
    return  $("#" + frameId + "-dataform");
}

function getMainContainer(frameId) {
    return  $("#" + frameId + "-maincontainer");
}

function getDetailContainer(frameId) {
    return  $("#" + frameId + "-detailcontainer");
}

function getSelectContainer(frameId) {
    return  $("#" + frameId + "-select-container");
}


function getGridToolbar(frameId) {
    return  $("#" + frameId + "-toolbar");
}

function getSelectToolbar(frameId) {
    return  $("#" + frameId + "-select-toolbar");
}

function getSearchPanel(frameId){
    return  $("#" + frameId + "-search-panel");
}
function getSelectGrid(frameId) {
    return $("#" + frameId + "-select-datagrid");
}

function getSelectDialog(frameId) {
    return $.dialog.list[constant.DIALOG_SELECT_TAG];
}

function getExportDialog(frameId) {
    var dlg_id = frameId + "-export";
    return $.dialog.list[dlg_id];
}
function getDepartmentTree(frameId){
   return  getMainContainer(frameId).find(".department-tree");
}
function createExportDialog(frameId, data){
   var dlg_id = frameId + "-export";
   return $.dialog({id: dlg_id, title: "资料导出", lock:true, autoSize: true, content: data, max: false, min: false}); 
}

// function getTitle(title) {
//     if (title) {
//         return title;
//     }
//     var tab = $('#tabMain').tabs('getSelected');
//     var title = "";
//     if (tab) {
//         title = tab.panel('options').title;
//     }
//     return title;
// }

function getTitle(title) {
    if (title) {
        return title;
    }
    //return $("#module-content .head span").text();
    return $("#module-content .head").find("span").eq(0).text();
}

function getController(frameId){
   var arr = frameId.split('-');
   if (arr.length > 1){
       return "/" + arr[0];
   }else{
       return "/" + frameId;
   }
}

function parseQueryParams(options) {
    var query = "";
    if (!$.isEmptyObject(options)) {
        if (!$.isArray(options)) {
            $.each(options, function (index, val) {
                query += index + "/" + val;
            })
        }
    }
    return query;
}

function getGridSelections(frameId) {
    var data_grid = getDataGrid(frameId);
    if (data_grid.hasClass("datagrid"))
    {
        return data_grid.datagrid('getSelections');
    } else {
        return data_grid.treegrid('getSelections');
    }
}

function createDialog(pageUrl, title, id, data, callback) {
    $.get(pageUrl,
        function (result, status) {
             //检查异常返回异常消息，JSON格式的对象字符串
             if (result.indexOf("{") === 0){
                var json_data = $.parseJSON(result);
                $.dialog.alert(json_data.message);
            }else{
                var options = {id: id,title: title, content:result, autoSize: true,data: data,lock: true,max: false,min: false};
                var $dialog = $.dialog(options);
                parseForm($dialog.DOM.content);
                if (callback){
                   callback.call(this, $dialog); 
                }  
            }
        }
    );
}

function createDialogWithButtons(content, title, id, okCallback, data) {
    var options = {id: id,title: title, content:content, autoSize: true,data: data,lock: true,max: false,min: false};
    var button = [
        {name: '确定',callback: function () { if (okCallback != undefined){return okCallback.call(this)};},focus: true},
        {name: '取消',callback: function () {return true;}},
    ];
    options.button = button;
    var $dialog = $.dialog(options);
    parseForm($dialog.DOM.content);
    return $dialog;
}

/**
 * 创建带确定取消按钮的弹窗，并带确定按钮回调
 * @param pageUrl 弹出内容url,如果需要带参数，可以在此携带
 * @param title 弹窗标题
 * @param id 弹窗id
 * @param okCallback 确定按钮回调
 */
/**
 * 创建带确定取消按钮的弹窗，并带确定按钮回调
 * @param pageUrl 弹出内容url,如果需要带参数，可以在此携带
 * @param title 弹窗标题
 * @param id 弹窗id
 * @param okCallback 确定按钮回调
 */
function createSimpleDialog(url, title, id, okCallback, cancelCallback) {
    $.get(url,
        function (content, status) {
            var options = {id: id,title: title, content:content, autoSize: true,lock: true,max: false,min: false};
            var button = [
                {name: '确定',callback: function () {
                        if (okCallback != undefined){
                            return okCallback.call(this)};
                    },
                    focus: true
                },
                {name: '取消',callback: function () {
                        if (cancelCallback !=undefined){
                            return cancelCallback.call(this);
                        }else{
                            return true;
                        }
                    }},
            ];
            options.button = button;
            var $dialog = $.dialog(options);
            parseForm($dialog.DOM.content);
        }
    );
}

function createDialogEx(pageUrl, title, id, data) {
    var options = {id: id,title: title,autoSize: true,data: data,lock: true,max: false,min: false};
    var $dialog = $.dialog(options);
    $.ajax({ url: pageUrl,
            success: function (result, status) {
             if (result.indexOf("{") === 0){
                var json_data = $.parseJSON(result);
                $.dialog.alert(json_data.message);
            }else{
               $dialog.content(result);
               parseForm($dialog.DOM.content);
            }
        }});
    return $dialog;
}

function closeDialog(id) {
    if (id !== undefined){
        $.dialog.list[id].close()
    }else{
        $.dialog.focus.close();
    }
}

function action_add(frameId, title, options) {
    var data_grid = getDataGrid(frameId);
    if (data_grid.hasClass("treegrid"))
    {
        action_tree_add(frameId, title, options);
    } else {
        _add(frameId, title, options)
    }
}

function action_tree_add(frameId, title, options) {
    var data = options || {};
    var rows = getGridSelections(frameId);
    if (rows.length === 1) {
        //"q-"为查询字段前缀，系统自动把这类型的字段作为查询条件，如果不加的话，需要在frameId里面的_before_add自己添加查询
        $.extend(data, {"q-parent_id": rows[0].id}); 
    }
    _add(frameId, title, data);
}


function _add(frameId, title, options) {
    //var query = parseQueryParams(options);
    var controller = getController(frameId);
    $.get(controller + "/add/", options,
            function (data, status) {
                if (typeof(data) === "object"){
                    $.dialog.alert(data.message);
                }else{
                    $.dialog({
                        id: frameId,
                        title: getTitle(title) + '_新增',
                        autoSize: true,
                        content: data,
                        lock: false,
                        max: false,
                        min: false
                    });
                    parseForm();
                    formCache[frameId] = {data:data, action:"add"};
                }
            });
}

function add_with_frame(frameId, title, options) {
    var controller = getController(frameId);
    var dlg = $.dialog({
        id: frameId,
        title: getTitle(title) + '_新增',
        autoSize: true,
        content: "url:" + controller + "/add/",
        lock: false,
        max: true,
        min: false,
//        cancel:false
    });   
    dlg.max();
}

function detail_with_frame(frameId, title) {
    var controller = getController(frameId);
    var rows = getGridSelections(frameId);
    if (rows.length !== 1) {
        $.dialog.tips('请选择一条记录');
        return false;
    }
    var dlg = $.dialog({
        id: frameId,
        title: getTitle(title) + '_编辑',
        autoSize: true,
        content: "url:" + controller + "/detail/id/" + rows[0].id,
        lock: false,
        max: true,
        min: false,
//        cancel:false
    });
    dlg.max();
}

function action_detail(frameId, title, options) {
    if ($("#" + frameId + "-toolbar .detail-edit").attr("onclick")){
        _detail(frameId, title, options);
    }
}

function action_view(frameId, title, options){
    var rows = getGridSelections(frameId);
    try{
        getDataGrid(frameId).trigger("beforeView", [rows,"edit"]);
    }catch(e){
        $.dialog.alert(e);
        return false;
    }
    if(rows.length > 0){
        showDetailForm(frameId, rows[0].id, title, options, "view");
    }
}

function _detail(frameId, title, options) {
    var rows = getGridSelections(frameId);
    if (rows.length !== 1) {
        $.dialog.tips('请选择一条记录');
        return false;
    }
    try{
        //if(typeof(beforeDetail) == 'function')
        //beforeDetail(rows,"edit");
        getDataGrid(frameId).trigger("beforeDetail", [rows,"edit"]);
    }catch(e){     
        $.dialog.alert(e);
        return false;
    } 
    showDetailForm(frameId, rows[0].id, title, options);
}

function showDetailForm(frameId, id, title, options, action_mode) {
    var query = parseQueryParams(options);
    var controller = getController(frameId);
    var is_view = (action_mode == "view");
    $.get(controller + "/detail/id/" + id + "/" + query,
            function (data, status) {
                if (typeof(data) === "object"){
                    $.dialog.alert(data.message);
                }else{
                    var dlg = $.dialog({
                        id: frameId,
                        title: getTitle(title) + (is_view?'_查看':'_编辑'),
                        autoSize: true,
                        content: data,
                        lock: false,
                        max: false,
                        min: false,
                        parent: this
                    });
                    formCache[frameId] = {data:null, action:"edit"};
                    // if (action_mode == "view"){ //查看状态，设置隐藏控件
                    //     dlg.DOM.content.find(".btn-update").remove();
                    //     dlg.DOM.content.find(".view-disabled").hide();
                    //     dlg.DOM.content.find("input").attr("readonly","true");
                    //     dlg.DOM.content.find("input[type=checkbox]").attr("disabled","disabled");
                    //     dlg.DOM.content.find("textarea").attr("readonly","true");
                    //     dlg.DOM.content.find(".chosen-select").attr("disabled","disabled");
                    //     // $(dlg.DOM.content).click(function(){
                    //     //     if($(this).data("alert") == undefined){
                    //     //         $.dialog.alert("查看状态，修改将无法保存！");
                    //     //         $(this).data("alert", "true");
                    //     //     }
                    //     // });
                    // }
                    parseForm();
                }
            });
}

function action_delete(frameId, options) {
    _delete(frameId, options);
}

function deleteRows(frameId, list, options, tips){
    if (list.length > 0) {
        if (tips == undefined){
            tips = '确定要删除所选记录？';
        }
        var datagrid  = getDataGrid(frameId);
        $.dialog.confirm(tips, function () {
                var controller = getController(frameId);
                $.post(controller + '/delete',
                    {id: list, options: options},
                    function (result) {
                        if (result.code === 0) {
                            datagrid.trigger("afterDelete", list);
                            refreshGrid(frameId, list, "delete");
                            datagrid.trigger("afterRefresh", list);
                            $.dialog.tips("删除成功！");
                        } else {
                            $.dialog.alert(result.message);
                        }
                    },
                    "json"

                ).error(
                        function(XMLHttpRequest, textStatus, errorThrown){
                            $.dialog.alert("删除错误！");
                        }
                    );
            }
        );
    }
}

function _delete(frameId, options, tips) {
    var list = new Array();
    var rows = getGridSelections(frameId);
    if (rows.length === 0) {
        $.dialog.tips('请选择删除项!');
    } else {
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            list.push(row.id);
        }
    }
    try{
        getDataGrid(frameId).trigger("beforeDelete", [rows]);
    }catch(e){
        $.dialog.alert(e);
        return false;
    }
    deleteRows(frameId, list, options, tips);
}

function action_copy(frameId, title, options) {
    var rows = getGridSelections(frameId);
    if (rows.length === 0 || rows.length > 1) {
        $.dialog.tips('请选择一条记录!');
    }
    try{
        getDataGrid(frameId).trigger("beforeCopy", [rows]);
    }catch(e){
        $.dialog.alert(e);
        return false;
    }
    if (rows.length === 1) {
//        $.dialog.confirm('确定要复制所选记录？', function () {
            $.get("/" + frameId + "/copy/id/" + rows[0].id + "/",
                function (data, status) {
                    if (typeof(data) === "object"){
                        $.dialog.alert(data.message);
                    }else{
                        var dialog = $.dialog({
                            id: frameId,
                            title: getTitle(title) + '_复制',
                            autoSize: true,
                            content: data,
                            lock: false,
                            max: false,
                            min: false,
                            parent: this
                        });
                        parseForm();
                        formCache[frameId] = {data:null, action:"copy"};
                    }
                });
//            }
//        );
    }
}

function action_update(frameId) {
    showMaskLayer();
    var dataForm = getDataForm(frameId);
    //可以在提交前修改或添加input值，在master-detail
    if (!formCache || !formCache[frameId]){
        formCache[frameId] = {data:null, action:""};
    }
    try{
        dataForm.trigger("beforeUpdate", formCache[frameId].action);
    }catch(e){
        hideMaskLayer();        
        $.dialog.alert(e);
        return false;
    }  
    dataForm.trigger("Update", formCache[frameId].action); //内部控件绑定，不建议外部bind
    dataForm.form('submit', {
        onSubmit:function(){
            var validate = $(this).form('validate');
            if (!validate){
                hideMaskLayer();
                var errinput = $(this).find('.validatebox-invalid');
                $(errinput.get(0)).focus();
            }
            return validate;
        },
        success: function (ret) {
            var pattern = /^\{.*code.*\}$/gi;
            var pattern1 = /^\{.*error.*\}$/gi;
            if (!pattern.test(ret) && !pattern1.test(ret)){
                hideMaskLayer();
                $.dialog.alert("保存错误！"+ ret);
                return false;
            }
            var result = $.parseJSON(removeJsonQuote(ret));
            if (result.code === 0) {
                //成功返回的message为本条记录
                $.dialog.tips("保存成功！");
                dataForm.trigger("afterUpdate", result.message.id); //提交成功后，触发
                if (formCache[frameId].action === "add"){ 
                    $.dialog.confirm('是否继续新增记录？',
                            function () {
                                $.dialog({id: frameId}).content(formCache[frameId].data);
                                parseForm();
                                dataForm.trigger("renewForm", result.message.id); //重新新增后触发
                            }
                    );
                }
                $.dialog({id: frameId}).close();
                console.log(formCache[frameId].action);
                refreshGrid(frameId, result.message, formCache[frameId].action);
                getDataGrid(frameId).trigger("afterRefresh");
            } else {
                $.dialog.alert(result.message);
            }
            hideMaskLayer();
        }
    });

}

//非集成简化版操作
function create_simple_dialog(frameId, title, content){
    var dlg = $.dialog({
        id: frameId,
        title: title,
        content: content,
        autoSize: true,
        lock: false,
        max: false,
        min: false
    });
    parseForm();
    return dlg;
}
function action_add_simple(frameId, title, options) {
    var controller = getController(frameId);
    $.get(controller + "/add/", options,
        function (data, status) {
            if (typeof(data) === "object"){
                $.dialog.alert(data.message);
            }else{
                create_simple_dialog(frameId, title, data);
            }
        });
}

function action_detail_simple(frameId, id, title, options) {
    var query = parseQueryParams(options);
    var controller = getController(frameId);
    $.get(controller + "/detail/id/" + id + "/" + query,
        function (data, status) {
            if (typeof(data) === "object"){
                $.dialog.alert(data.message);
            }else{
                create_simple_dialog(frameId, title, data);
            }
        });
}

/**
 * 简单保存
 * @param frameId
 * @param callback 保存后回调
 */
function action_update_simple(frameId, callback) {
    showMaskLayer();
    var dataForm = getDataForm(frameId);
    var idVal = dataForm.find("input[name=id]").val();
    dataForm.form('submit', {
        onSubmit:function(){
            var validate = $(this).form('validate');
            if (!validate){
                hideMaskLayer();
            }
            return validate;
        },
        success: function (ret) {
            var result = $.parseJSON(removeJsonQuote(ret));
            if (result.code === 0) {
                //成功返回的message为本条记录
                $.dialog.tips("保存成功！");
                $.dialog({id: frameId}).close();
                if (callback != undefined){
                    callback.call(this, result);
                }
                refreshGrid(frameId, result.message, idVal==""?"add":"edit");
            } else {
                $.dialog.alert(result.message);
            }
            hideMaskLayer();
        }
    });
}

function action_approval(frameId){
    var dataForm = getDataForm(frameId);
    dataForm.form('submit', {
        url: frameId + "/approval",
        onSubmit: function(param){
            param.action = formCache[frameId].action; 
        },
        success: function (ret) {
            var result = $.parseJSON(removeJsonQuote(ret));
            if (result.code === 0) {
                $.dialog.tips("保存成功！");
                $.dialog({id: frameId}).close();
                refreshGrid(frameId, result.message, formCache[frameId].action);                
            } else {
                $.dialog.tips(result.message);
            }
        }
    });
}

function postForm(dataForm, dialog_id){
    dataForm.form('submit', {
        success: function (ret) {
            var result = $.parseJSON(ret);
            if (result.code === 0) {
                 dataForm.trigger("afterPost", 0);
                 $.dialog({id: dialog_id}).close();              
            }else{
               dataForm.trigger("afterPost", 1);
            }
            $.dialog.tips(result.message);
        }
    });    
}

function parseForm(target) {
    if (!target){
        $.parser.parse(".detailcontainer");
    }else{
        $.parser.parse(target);
    }
    // $(".combo .textbox-text").attr("readonly","readonly");
    parseExternalComponents(target);
}

function refreshTreegrid(_grid, row, action){
    switch (action){
        case "add":
        case "copy":
            var parent_id = 0;
            if (!$.isEmptyObject(row) && row.hasOwnProperty("parent_id")){
                parent_id = row.parent_id;
            }
            _grid.treegrid("append", {parent:parent_id, data:[row]});
            break;
        case "edit":
            var data ={id: row.id, row:row};
            var parent_id = 0, select_parentId = 0;
            var selectRow = _grid.treegrid('getSelected');
            if (selectRow != null){
                select_parentId = selectRow.parent_id;
            }
            if (!$.isEmptyObject(row) && row.hasOwnProperty("parent_id")){
                parent_id = row.parent_id;
            }
            if (parent_id != select_parentId){ //当前选择的父节点和返回的不一致
                _grid.treegrid('remove', row.id);
                var destParentRow = _grid.treegrid('find', parent_id);//id是关键字值
                if(destParentRow != null) {
                    if (destParentRow.children) {
                        _grid.treegrid("append", {parent: parent_id, data: [row]});
                    } else {
                        _grid.treegrid("expand", parent_id);
                    }
                }
            }else{
                _grid.treegrid("update", data);
            }
            break
        case "update": //只做更新，不做移动
                var data ={id: row.id, row:row};
                _grid.treegrid("update", data);
            break;
        case "delete": //row为Id列表
            if (row.length !== 0){
                $.each(row, function(index, value){
                    _grid.treegrid('remove', value);
                });
            }
            break;
        default:
           _grid.treegrid("reload");
           break;
    }   
}

function refreshDatagrid(_grid, row, action){
  switch (action){
        case "add":
            _grid.datagrid('options').pageNumber = 1; //设置在第一页，否则会出现负数的RowNumber
            _grid.datagrid("insertRow", {index:0, row:row});
            break;
        case "edit":
            var selectRow = _grid.datagrid('getSelected');
            if (selectRow != null) {
                var index = _grid.datagrid('getRowIndex', selectRow);
                _grid.datagrid("updateRow", {index: index, row: row});
            }
            break
        case "delete":
            var rows = _grid.datagrid('getSelections');
            if (rows.length !== 0){
                $(rows).each(function(){
                    var index = _grid.datagrid('getRowIndex', this);
                    _grid.datagrid('deleteRow', index);
                });
            }
            break;
        default:
            _grid.datagrid("reload");
            break;
    }  
}

function refreshGrid(frameId, row, action) {
    var _grid = getDataGrid(frameId);
    if (_grid.length > 0){
        if (_grid.hasClass("datagrid")){
            if (!$.isEmptyObject(row) && row.hasOwnProperty("id")) {
                refreshDatagrid(_grid, row, action);
            }else{
                refreshDatagrid(_grid);
            }
        } else {
            refreshTreegrid(_grid, row, action);
        }
    }
}

function gridLoadSuccess(row, data) {
    var _this = $(this);
    var rows = (row === null) ? data.rows : data;
    $(rows).each(function (index, val) {
        var child_count = parseInt(val.child_count);
        if (isNaN(child_count) || (child_count === 0)) {
            _this.treegrid('update',
                    {id: val.id, row: {iconCls: 'icon-area'}}
            );
        }
    });
}

function data_changed(name) {
    $("input[name='" + name + "']").val(1); //设置改变标志，后台有改变才处理
}
function do_check_treeItem(node, checked, prfix, icon) {
    if (parseInt(node.tree_node) === 0) {
        var idTag = "id=\"" + prfix + "_li_" + node.id + "\"";
        var listTag = "#" + prfix + "_list";
        var obj = $(listTag).find("li[" + idTag + "]");
        if (checked) {
            if (obj.length === 0) {
                var btn = "<a href=\"javascript:void(0)\" class=\"easyui-linkbutton fontawesome-icon-button\" style=\"width:100%\" icon=\"" + icon + " icon-large\" plain=\"true\" onclick=\"\">" + node.text + "</a>";
                var input = "<input type=\"hidden\" name=\"" + prfix + "_inputs[]\" value=\"" + node.id + "\" />";
                $("<li " + idTag + " onclick=\"$(this).remove()\" />").html(btn + input).appendTo($(listTag));
            }
        } else {
            $(obj).remove();
        }
    } else {
        $(node.children).each(function (index, val) {
            do_check_treeItem(val, checked, prfix, icon);
        });
    }

}
function initial_easyui_grid_notbar(frameId, title, queryParams) {
    initial_easyui_grid(frameId, title, queryParams);
    var data_grid = getDataGrid(frameId);
    if (data_grid.hasClass("datagrid")){
        data_grid.datagrid({
            onDblClickRow: function (index, row) {
                ;
            }
        })
    };
}
function initial_easyui_grid(frameId, title, queryParams) {
    var data_grid = getDataGrid(frameId);
    var options = {};
    if (!$.isEmptyObject(queryParams)){
        $.extend(options, queryParams);
    }
    
    if (data_grid.hasClass("datagrid"))
    {
        var dataOptions = {
            onDblClickRow: function (index, row) {
                //action_detail(frameId, title);
                var config = getMainContainer(frameId).data("config")
                if (config != undefined && config != null){
                    if (config.hasOwnProperty("onDblClickRow")){
                        config.onDblClickRow.call(this, frameId);
                    }else {
                        action_view(frameId, title);
                    }
                }else{
                    action_view(frameId, title);
                }
            },
            onLoadSuccess: function(data){
               data_grid.trigger("onLoadSuccess", [data]);
            },
            onClickCell: function(index, field, value){
               data_grid.trigger("onClickCell", [index, field, value]); 
            },
            onSortColumn: function(sort, order){
                var queryParams = data_grid.datagrid('options').queryParams;
                queryParams.select_order = sort + " " + order;
                data_grid.datagrid('options').queryParams = queryParams;
                data_grid.datagrid('reload');
            },
            onResizeColumn:function(field, width){
                //alert(field);
            },
            queryParams: options
        }
        //如果有”-“号，不马上获取数据
        if (frameId.indexOf("-") !==-1){
            dataOptions["url"] = "";
        }
        data_grid.datagrid(dataOptions);
    } else {
        var dataOptions = {
            onDblClickRow: function (row) {
                //action_detail(frameId, title);
                action_view(frameId, title);
            },
            onLoadSuccess: function(data){
               data_grid.trigger("onLoadSuccess", [data]);
            }
        }
        if (frameId.indexOf("-") !==-1){
            dataOptions["url"] = "";
        }
        data_grid.treegrid(dataOptions);
    }

   //功能菜单项自定义
    var $diy_actions = $("#actions-" + frameId).children();
    if ($diy_actions.length > 0) {
        getGridToolbar(frameId).find(".actions").html("");
        $("#box_"+frameId).html("");
        $($diy_actions).each(function(){
            if ($(this).hasClass("right-menu")){
                $(this).appendTo($("#box_"+frameId));
            }else{
                $(this).appendTo(getGridToolbar(frameId).find(".actions"));
            }
        })
        //getGridToolbar(frameId).find(".actions").html($diy_actions.html());
    }
    //新增功能菜单项
    var $ext_actions = $("#"+frameId+"-action-extend").children();
    if ($ext_actions.length > 0) {
        $($ext_actions).each(function(){
            if ($(this).hasClass("right-menu")){
                $(this).appendTo($("#box_"+frameId));
            }else{
               $(this).appendTo(getGridToolbar(frameId).find(".actions"));
            }
        })
    }
    //right-menu

    //找不到查找框，隐藏查找按钮
    if (getSearchPanel(frameId).length === 0) {
        getGridToolbar(frameId).find("a.action-search").hide();
        getGridToolbar(frameId).find("div.btn-search-separator").hide();
    }else{
        var $actions = getGridToolbar(frameId).find(".actions").find("a");
        if ($actions.length == 1 && $actions.hasClass("action-search")){
           getGridToolbar(frameId).find(".actions").hide();
        }
        action_search(getGridToolbar(frameId).find("a.action-search"), frameId);
    }
    //配置选项设置
    var config = getMainContainer(frameId).data("config");
    if (config != null){
        setToolbarStyle(frameId, config, "add");
        setToolbarStyle(frameId, config, "edit");
        setToolbarStyle(frameId, config, "delete");
        setToolbarStyle(frameId, config, "copy");
    }
}

function setToolbarStyle(frameId, config, action){
    var toolbar = getGridToolbar(frameId);
    if (config.hasOwnProperty(action)){
        var btn = $(toolbar).find(".actions .detail-"+action);
        btn.text(config[action].text);
        if (config[action].icon){
            btn.attr("icon", config[action].icon + " fa-lg");
        }
        if (config[action].click){
            btn.removeAttr("onclick").unbind("click").bind("click", function () {
                config[action].click.call(this, frameId);
            });
        }
    }
}

function action_search(sender, frameId) {
    var $default_search_panel = getGridToolbar(frameId).find(".search-panel");
    var $search_table = getGridToolbar(frameId).find(".search-table");
    if ($search_table.length === 0) {
        getSearchPanel(frameId).children(".search-table").prependTo($default_search_panel).show();
        parseExternalComponents();
    }
    $default_search_panel.toggle();
    $(sender).find(".l-btn-icon").toggleClass("icon-caret-down").toggleClass("icon-caret-right");
}

function select_return(frameId) {
    var rows = getSelectGrid(frameId).datagrid('getSelections');
    if (rows.length !== 0) {
        var $select = getSelectDialog(frameId).data.sender;
        $select.extChosen("appendAll", rows);
        $select.trigger("selectReturn", {rows:rows}); //trigger如果参数是数组，会分解成参数1、参数2...所以必须封装成对象
        getSelectDialog(frameId).close();
    } else {
        $.dialog.tips("至少选择一条记录！");
    }
}

function select_all(sender, frameId) {
    var rows = getSelectGrid(frameId).datagrid('getSelections');
    if (rows.length === 0) {
        $(sender).find("span.fa-square-o").removeClass("fa-square-o").addClass("fa-check-square");
        getSelectGrid(frameId).datagrid("selectAll");
    } else {
        $(sender).find("span.fa-check-square").removeClass("fa-check-square").addClass("fa-square-o");
        getSelectGrid(frameId).datagrid("clearSelections");
    }
}
//点击导出按钮，弹出导出向导界面
function action_export(frameId) {
    var $query = getSearchPanel(frameId).data("query");
    $.get(frameId + "/export", $query, 
        function(data){
            var $dialog = createExportDialog(frameId, data)
            var $export_panel = $dialog.DOM.content.find(".export-panel");
            var $export_fields = $("#" + frameId + "-export-panel").find(".export-fields")
            $export_fields.clone().appendTo($export_panel);
            parseForm();
        }
    );
}
//点击开始导出按钮，下载导出结果
function exportResult(frameId) {
    var $dialog = getExportDialog(frameId);
    $dialog.DOM.content.find("form[name='export-form']").submit();
    $dialog.close();
}

function resetQueryInput(frameId){
    var target = getGridToolbar(frameId).find(".filter-field");
    $(target).each(function () {
        var $input = $(this);
        if ($(this).hasClass("easyui-datebox")) {
            $input = $(this).data("textbox").textbox.find(".textbox-value");
        }
        if ($input) {
            $input.val("");            
        }
    }); 
}

function getQueryParams(target){
    var query = {};
    $(target).each(function () {
        var value = "";
        var $input = $(this);
        if ($(this).hasClass("easyui-datebox") || $(this).hasClass("easyui-datetimebox")) {
            $input = $(this).data("textbox").textbox.find(".textbox-value");
        }
        if ($input) {
            var value = $input.val();
            var name = $input.attr("name");
            if (value && name) {
                if (query[name]){  //多选，如name=xxx[]
                   if ($.isArray(query[name])){
                      query[name].push(value);
                   } else{
                      query[name] = [query[name],value]; 
                   }
                }else{
                   query[name] = value;
                }
            }
        }
    }); 
    return query;
}

//通用生成查询条件进行查询函数，后台对传入的参数通过_parsefilter重载进行解析
function doSearchQuery(frameId, initialParams) {
    var target = getGridToolbar(frameId).find(".filter-field"); 
    var queryParams = {};
    if (initialParams){ //保存初始参数
        getSearchPanel(frameId).data("initialParamsCache", initialParams);
        $.extend(queryParams, initialParams);
    }else{
       initialParams = getSearchPanel(frameId).data("initialParamsCache");
       var inputParams = getQueryParams(target); 
       $.extend(queryParams, initialParams, inputParams);
    }
    var _grid = getDataGrid(frameId);
    if (_grid.hasClass("datagrid")){
        _grid.datagrid("load", queryParams);
    }else{
        _grid.treegrid("load", queryParams); 
    }
    getSearchPanel(frameId).data("query", queryParams);//保存查询条件，导出时使用
}
   
function doRefresh(targetId){
    getDataGrid(targetId).datagrid("load", {});
}

//通用生成查询条件进行查询函数，后台对传入的参数通过_parsefilter重载进行解析
function doSelectQuery(frameId) {
    var target = getSelectToolbar(frameId).find(".filter-field");
    var query = getQueryParams(target);    
    getSelectGrid(frameId).datagrid("load", query);
}

function initailAttachmentComponent(resourcePath, frameId, id){
    $(".tab-attachment-content").scroll(function(){
           $(".folder-address-nav").css("bottom", -$(this).scrollTop());
    });
    var controller = getController(frameId);
    $("#" + frameId + "_folder").folder({
       get_url: controller + "/getAttachmentFolders/r_id/"+ id,
       update_url: controller + "/updateAttachmentFolder/r_id/" + id,
       remove_url: controller + "/removeAttachmentFolder/r_id/" + id,
       onClick: function (category, id, name) {
           $("#" + frameId + "-uploader").album("load", {category: category, currentFolder: id, folder_name: name});
       }
    });

    $("#" + frameId + "-uploader").album({
       prefix: frameId + "",
       upload_url: controller + "/uploadAttachment",
       get_url: controller + "/getAttachmentFiles/r_id/" + id,
       update_url: controller + "/updateAttachmentFile",
       remove_url: controller + "/removeAttachmentFile",
       bas_url: resourcePath + "/Public/webuploader"
    });

    getDataForm(frameId + "").bind("afterUpdate", function (event, id) {
       $("#" + frameId + "-uploader").album("upload", {formData: {r_id: id}});
    });
}

function changePassword(id, name){
     createDialog("SysUser/changePassword/id/"+ id, "修改密码（"+ name +"）", "dlg_user_changepwd"); 
}

function custom_get(action, title, options) {
    $.get(action, options,
            function (data, status) {
                if (typeof(data) === "object"){
                    $.dialog.alert(data.message);
                }else{
                    $.dialog({
                        id: action,
                        title: title,
                        autoSize: true,
                        content: data,
                        lock: false,
                        max: false,
                        min: false
                    });
                    parseForm();
                }
            });
}

function showMediaForm(sender){
    createDialog("./WxMediaLib/select", "选择素材", "WxMediaLib", {
        callback: function (data) {
            if (data) {
                $("input[name=media_id]").val(data.media_id);
                $(sender).find("input").val(data.media_title);
            }
        }
    });
}

function load_branch_tree(action, $tree_target) {
    $.getJSON(action, {}, function (result) {
        $tree_target.tree({
            data: result,
            onLoadSuccess: function (node, data) {
                if (data.length > 0) {
                    var n = $(this).tree('find', data[0].id);
                    $(this).tree('select', n.target);
                    $(this).find("div.tree-node").attr('title', function () {
                        return $(this).find("span.tree-title").text();
                    });
                }
            }
        })
    });
}
//{"ql-code": node.code+"_", "q-branch_id":node.id}
function initial_branch_tree(frameId, action, getQueryParamsCallback){
    if (!action){
        action = frameId + "/tree";
    }
    if (!getQueryParamsCallback){
        getQueryParamsCallback = getBranchTreeQueryParamsByCode;
    }
    var $tree_target = getDepartmentTree(frameId);
    if ($tree_target.length > 0){
        var $datagrid = getDataGrid(frameId);
        load_branch_tree(action, $tree_target);
        $tree_target.tree({
            onClick: function (node) {
                if ($datagrid.hasClass("treegrid")){
                    $datagrid.treegrid({queryParams: getQueryParamsCallback(node)});
                }else {
                    $datagrid.datagrid({queryParams: getQueryParamsCallback(node)});
                }
            }
        });
        $datagrid.bind("afterDelete", function () {
           load_branch_tree(action, $tree_target);
        }); 
    }
}
function getBranchTreeQueryParamsByCode(node){
    return {"ql-code": node.code+"_"};
}
function getBranchTreeQueryParamsById(node){
    //return {"q-branch_id":node.id};
    return {"ql-DAC*code": node.code}; //在DataModel->defaultDacFilter特殊处理
}
function initial_project_tree(frameId, getQueryParamsCallback){
    initial_branch_tree(frameId, "ComProject/tree", getQueryParamsCallback); 
}

function add_branch_x(branch_type, branch_field, frameId, controller){
    if (controller === undefined){
        controller = frameId;
    }
    var $tree_target = getDepartmentTree(frameId);
    if ($tree_target.length === 1)
    {
        var branch_type_name = "组织机构或部门";
        switch(branch_type){
            case 2:
                branch_type_name = "部门";
                break;
            case 3:
                branch_type_name = "项目";
                break;
            case 255:
                branch_type_name = "所属单位"; //人员
                break;
            default:
                break;
        }
        var node = $tree_target.tree("getSelected");
        if (!$.isEmptyObject(node))
        {
            if ((parseInt(node.type) === branch_type) || (255 === branch_type)) {//不限制
                var obj = {};
                obj["q-" + branch_field] = node.id;
                obj["q-company_name"] = node.text;
                _add(controller, "", obj);
            }else{
                $.dialog.alert('所选节点类型必须为' + branch_type_name); 
            }
        }else {
            $.dialog.alert('请先选择' + branch_type_name); 
        }
    }else{
        _add(controller);
    }
}

function formatsetUserPwd(value, row, index) {
    var btnHtml = "<i style=\"width:auto;height:24px;cursor:pointer\" class=\"fa fa-refresh fa-lg\" onclick=\"resetUserPwd("+row.id+")\" title=\"重置密码为初始密码\"></i>";
    return btnHtml;
}

function resetUserPwd(user_id){
   $.dialog.confirm('确定重置当前用户密码？', function () { 
       $.post("/SysUser/resetUserPwd/id/"+user_id, function(result){
           $.dialog.tips(result.message);
       },"json")
   });
}

function max_dialog(target){
    $(target).css({width: $(document).width() - 5 + 'px', height: $(window).height() - 20 + 'px'});
    $(".ui_title_bar").css({width: '98%', padding: '0px'}); //微调
    $(".ui_border").css({"border-radius": "0px"});
}

function re_bind_action_add(frameId, func){
    $("#" + frameId + "-toolbar .detail-add").removeAttr("onclick").unbind("click").bind("click", function () {
        func.call(this); 
    });
}

function re_bind_action_detail(frameId, func){
    $("#" + frameId + "-toolbar .detail-edit").removeAttr("onclick").unbind("click").bind("click", function () {
        func.call(this); 
    });
}

function showMaskLayer(){
   $(".mask-layer").show();
}
function hideMaskLayer(){
   $(".mask-layer").hide();
}

function  action_import(fileId, url, comfirm_title, callback){
    var $file = $("#" + fileId);
    if ($file.val() == "") {
        alert("导入文件不能为空！");
        return false;
    }
    var dlg_active = $.dialog.focus;
    $.dialog.confirm(comfirm_title,
        function () {
            var formData = new FormData();
            var formInputs = $file.parents("form").serializeArray();
            $(formInputs).each(function(){
                formData.append(this.name, this.value);
            });
            formData.append("file_key", fileId);
            formData.append(fileId, document.getElementById(fileId).files[0])
            $.ajax({url: url, type: "POST", data: formData, contentType: false, processData: false, dataType: "json",
                beforeSend: function () {
                    showMaskLayer();
                },
                success: function (result) {
                    if (result.code == 0){
                        if (callback !== undefined) {
                            callback.call(this, result);
                        }
                        $.dialog.tips(result.message);
                    }else{
                        $.dialog.alert(result.message + "<br>问题已经反馈，平台将尽快处理并告知处理进度！");
                    }
                    dlg_active.close();
                    hideMaskLayer();
                },
                error: function (XMLHttpRequest, textStatus, errorThrown) {
                    alert("导入失败！" + textStatus + errorThrown);
                    hideMaskLayer();
                }
            });
        });
}
//动态设置form的ID
function setDataFormId(frameId, id){
    getDataForm(frameId).find("input[name=id]").val(id);
}

/**设置浏览窗体的Toolbar配置，配置格式
 * {add:{text:"新增", click:event},edit:{...},copy{...},delete{...}}
 */


function setMainContainerConfig(frameId, $config){
    getMainContainer(frameId).data("config", $config);
}

//跨公司复制选择公司弹出框
function action_show_copydlg(frameId, controller) {
    if (controller == undefined){controller = frameId;}
    var rows = getGridSelections(frameId);
    if (rows.length == 0) {
        $.dialog.tips('请至少选择一条记录');
        return false;
    }
    createDialog("/"+ controller +"/copyTo", "复制服务");
}

//跨公司复制
function action_copy_start(frameId, controller) {
    if (controller == undefined){controller = frameId;}
    var dest_branch = $("#copy-to-form select[name=dest_branch]").val();
    var rows = getGridSelections(frameId);
    if (dest_branch && rows.length > 0) {
        var id_values = [];
        $(rows).each(function(){
            id_values.push(this.id);
        });
        showMaskLayer();
        $.post(controller + "/copyTo", {dest_branch: dest_branch, source_id: id_values}, function (result) {
            hideMaskLayer();
            if (result.code == 0) {
                closeDialog();
                $.dialog.tips("复制成功！");
                refreshGrid(frameId, result.message);
            }else{
                $.dialog.tips('复制失败！' + result.message);
            }
        },"json").error(function(){
            hideMaskLayer();
        });
    }else{
        $.dialog.tips('目标公司不能为空');
    }
}

//*自动完成
//target目标页面元素
//url方法url
function autocompleteAjax(target, url, selected_callback, options){
    if (options == undefined || options == null){
        options = {name: "name", search: ["querykey", "name"]};
    }
    $(target).autocomplete(url, {
        width: 0, //自动获取
        matchContains: true,
        scroll: true,
        scrollHeight: 100,
        delay: 500,
        matchCase:true,
        parse : function(data) {
            var rows = [];
            if(typeof(data) == "string") {
                data = eval('(' + data + ')');
            }
            for (var i in data) {
                rows[i] = { 
                    data : data[i], 
                    value : data[i].id,
                    result : data[i].name
                } 
            }
            return rows; 
        },
        formatItem: function (row, i, max) {
            return row.name;
        },
        formatResult: function (row, i, max) {
            return row.value;
        },
        formatMatch: function (row, i, max) {
            return row.name;
        }
    }).result(function(handler, item){
        // $(target).attr("data-id",item.id);
        var name = target.attr("data-name") ? target.attr("data-name")  : target.attr("name")+"_id";
        console.info($('input[name="'+name+'"]'));
        if ($('input[name="'+name+'"]').length > 0) {
            $('input[name="'+name+'"]').val(item.id);
        }else{
            if ((target).hasClass("filter-field")) {
                $(target).after('<input name="'+name+'" class="filter-field" type="hidden" value="'+item.id+'" />');
            }else{
                $(target).after('<input name="'+name+'" type="hidden" value="'+item.id+'" />');
            }
        }
        if (selected_callback != undefined){
            selected_callback(item);
        }
    });
}

function autocompleteAjaxEx(target, url, options){
    function _autocomplete(){
        $(target).trigger("unautocomplete");
        $(target).autocomplete(url, {
            width: 0,
            matchContains: true,
            scroll: true,
            scrollHeight: 120,
            delay: 500,
            dataType:"json",
            extraParams: options.extentQuery||{},
            parse : function(rows) {
                var result = [];
                $(rows).each(function(){
                    result.push({data : this, value : this.id, result : this.name});
                })
                return result;
            },
            formatItem: function (row, i, max) {
                if (options && options.formatItem != undefined){
                    return  options.formatItem(row, i, max);
                }else{
                    return row["name"];
                }
            },
            formatResult: function (row, i, max) { //显示
                if (options && options.formatResult != undefined){
                    return  options.formatResult(row, i, max);
                }else{
                    return row["name"];;
                }
            },
            formatMatch: function (row, i, max) {
                if (options && options.formatMatch != undefined){
                    return  options.formatMatch(row, i, max);
                }else{
                    return row["name"];;
                }
            }
        }).result(function(handler, item){
            if (options && options.onSelected!= undefined){
                options.onSelected.call(this, item);
            }
        });
    }
    //var width = $(target).width();
    $(target).on("keydown",function(event){
       if (event.keyCode == 8 || event.keyCode == 46){ //删除键
           $(this).val("");
           if (options.onSelected!= undefined){
               options.onSelected.call(this, {id:""});
           }
       }
    });
    _autocomplete();
}

//----------------------------一下测试------------------------------//
