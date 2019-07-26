function create_ccounting_section($selectTarget, default_value) {
    if ($selectTarget.data("loaded") == undefined) {
        var currDate = new Date();
        var month = currDate.getMonth() + 1;
        var year = currDate.getFullYear();
        // $selectTarget.html("");
        for (var i = 1; i < 18; i++) {
            var year_month = year + "/" + ((month < 10) ? ("0" + month) : month);
            var $option = "<option value='" + year_month + "'>" + year_month + "</option>";
            $selectTarget.append($option);
            month = month - 1;
            if (month <= 0) {
                year--;
                month += 12;
            }
        }
        if (default_value != undefined && default_value != "" && default_value != null){
            default_value = default_value.replace("-","/");//防止出现传进来的分隔符是“-”
            $selectTarget.find("option[value='"+ default_value +"']").attr("selected","selected");
        }
        $selectTarget.chosen("chosen:updated");
        $selectTarget.data("loaded", "1");
    }

}

//自动生成凭证选择会计期间
function create_accounting_section_combox(target, value ,type){
    var currDate = new Date();
    var month = currDate.getMonth() + 1;
    var year = currDate.getFullYear();
    var year_months = [];
    if(type == "year"){
        for (var i = 0; i < 5; i++) {
            var _yearmonth = year - i;
            year_months.push({id: _yearmonth, name: _yearmonth});
        }
        value = year;
    }else if(type == "month"){
        for (var i = 1; i < 13; i++) {
            var _yearmonth = String((i < 10) ? ("0" + i) : i);
            year_months.push({id: _yearmonth, name: _yearmonth});
        }
        value = String((month < 10) ? ("0" + month) : month);
    }else{
        for (var i = 1; i < 13; i++) {
            var _yearmonth = year + "/" + String((month < 10) ? ("0" + month) : month);
            year_months.push({id: _yearmonth, name: _yearmonth});
            month = month - 1;
            if (month <= 0) {
                year--;
                month += 12;
            }
        }
    }
    easyui_combobox(target, year_months, value);
}


function create_year_combox(target, onSelect){
    var currDate = new Date();
    var year = currDate.getFullYear();
    var year_values = [];
    for (var i = 0; i < 5; i++) {
        year_values.push({id: year, name: year});
        year = year - 1;
    }
    easyui_combobox(target, year_values, undefined, onSelect);
}

function formatOtherSide(value, row, index) {
    if (row._flag == 0) {
        return "销售方-" + value;
    }
    return "购买方-" + value;
}

function formatBankBillType(value, row, index) {
    return "";
}

function formatBillImage(value, row, index) {
    if (value > 0) {
        return "<i class='fa fa-image'></i>"
    } else {
        return ""
    }
}

/**
 * 初始化银行新增页面
 */
function initBillBankView() {
    max_dialog($("#BillBank-detailcontainer"));
    create_ccounting_section($("select[name=accounting_section]"));
    $.post("/BillBank/getAutoCompleteDatas", {include: 5}, function (result) {
        if (result.code == 0) {
            autocomplete($("input[name='name']"), result.message.names);
            autocomplete($("input[name='goods_name[]']"), result.message.goods_names);
        }
    }, "json");
}


function formatBillUnCheckDetail(value, row, index) {
    var btnHtml = "<i style=\"width:auto;cursor:pointer;color:#00aaee;\" class=\"fa fa-list-alt fa-lg\" onclick=\"showDetailForm('BillUnCheck'," + row.id + ")\"></i>";
    return btnHtml;
}

function getBillContentByFlagForNav(bill_flag, action, data) {
    getBillContentByFlag(bill_flag, action, data, true);
}

/**
 * 单证新增页面
 * @param bill_flag 类型
 * @param action  add or update
 * @param id 编号
 * @param data 传入的数据
 * @param is_batch 是否批量操作
 * @returns {boolean}
 */
function getBillContentByFlag(bill_flag, action, data, is_batch) {
    if (bill_flag === "" || action == "") {
        return false;
    }
    showMaskLayer();
    var dataForm = getDataForm("ComBill");
    dataForm.find("input[name=bill_flag][value='" + bill_flag + "']").prop("checked", "checked");
    //把保存在当前选中的影像ID设置到提交的数据里面
    var $wrap = $(".preview-image-nav").data("target");
    if ($wrap != null) {
        dataForm.find("input[name=image_id]").val($wrap.attr("data-id"));
    }
    var id = dataForm.find("input[name=id]").val();
    var post_data = {"bill_flag": bill_flag, "id": id};
    if (data != undefined) {
        post_data = $.extend(data, post_data);
    }
    $.post("ComBill/getContent", post_data, function (result) {
        var moudle = result.message.module; //根据flag返回不同的module
        var $bill_content = $("#ComBill-detailcontainer").find(".bill-content");
        $bill_content.html("");
        $bill_content.append(result.message.content);
        parseForm($bill_content);
        $bill_content.find(".btn-update").removeAttr("onclick").unbind("click").bind("click", function () {
            showMaskLayer();
            if (!dataForm.form('validate')) {
                hideMaskLayer();
                return false;
            }
            ;
            var frameId = "ComBill";
            $.post(moudle + "/" + action, dataForm.serialize(), function (ret) {
                var pattern = /^\{.*code.*\}$/gi;
                if (!pattern.test(ret)) {
                    $.dialog.alert("保存错误！" + ret);
                    hideMaskLayer();
                    closeDialog();
                    return false;
                }
                var result = $.parseJSON(removeJsonQuote(ret));
                if (result.code === 0) {
                    //成功返回的message为本条记录
                    setSelectImageData(result.message);
                    if (is_batch == true) {
                        $.dialog.tips("保存成功,自动跳到下一张影像");
                        setRelativeFlag();
                        resetBillContent();
                        setTimeout("thumb_nav_next()", 1000);
                    } else {
                        $.dialog.tips("保存成功");
                    }
                    refreshGrid(frameId, result.message);
                } else {
                    $.dialog.tips(result.message);
                }
                hideMaskLayer();
            });
        });
        hideMaskLayer();
    }, "json").error(function () {
        hideMaskLayer();
    })
}

/**
 * 认证比对显示列表
 * @param list
 */
function buildUnAuthListview(list) {
    $(".compare-detail tbody").html("");
    $(list).each(function (index) {
        var _this = this;
        var auth_text = _this.authed == 0 ? "<i style='color:red' class='fa fa-warning' title='未认证'></i>" : "<i style='color:dodgerblue' class='fa fa-check-square-o' title='已认证'></i>";
        var btn_text = _this.authed == 1 ? "" : "确认认证";
        var $content = $("<tr><td>" + (index + 1) + "</td><td>" + _this.source_no + "</td><td>" + _this.name +
            "</td><td>" + _this.total_amount + "</td><td>" + _this.total_tax + "</td><td><span class='auth-text'>" + auth_text +
            "</span></td></tr>");
        // "</span></td><td><a href='javascript:;' style='color:deepskyblue'><span class='btn-text'>" + btn_text + "</span></a></td></tr>");
        $content.find("a").click(function () {
            $.dialog.confirm("确认手工认证？", function () {
                $.post("BillCompare/updateAuthState", {
                    id: _this.id,
                    authed: _this.authed
                }, function (result) {
                    if (result.code == 0) {
                        $content.find("span.auth-text").text("已认证");
                        $content.find("span.btn-text").text("");
                    }
                    $.dialog.tips(result.message);
                }, "json");
            });
        });
        $(".compare-detail tbody").append($content);

    });
}

function resetBillContent() {
    $(".bill-content").html("");
    $("#ComBill-dataform input[name=id]").val("");
}

function findCurrentBill() {
    var data = getSelectedImageData();
    if (data != null && (data.bill_id > 0)) {
        setDataFormId("ComBill", data.bill_id);
        getBillContentByFlagForNav(data.bill_flag, "update");
    } else {
        resetBillContent();
    }
}

function formate_goto_shop(value, row, index) {
    //location.href="http://www.baidu.com"
    return "<a href='javascript:;' onclick='enterShop(" + row.id + ")'>管理</a>";
}

function enterShop(shop_id) {
    $.post("/Login/enterShop", {shop_id: shop_id}, function (result) {
        if (result.code == 0) {
            $.dialog.tips("登录校验中...");
            setTimeout("location.href='/Index'", 2000);
        }
    }, "json");
}

function strval(val) {
    if (val == undefined || val == null) {
        return ""
    }
    return val;
}

function numberval(val, precision) {
    if (val == undefined || val == null || val == "") {
        val = 0;
    }
    return parseFloat(val).toFixed(precision);
}

//格式标准科目的是否映射字段
function format_needmap(value, row, index){
    if (value == "1"){
        return "<a href='javascript:;' onclick='set_needmap(" + row.id + ",0)'><i class='fa fa-check'></i></a>";
    }else{
        return "<a href='javascript:;' onclick='set_needmap(" + row.id + ",1)'><i class='fa fa-check' style='color:#ccc'></i></a>";
    }
}

function set_needmap(subject_id, need_map){
    $.post("/SysSubject/setNeedMap",{id:subject_id, need_map: need_map}, function(result){
        if (result.code == 0){
            $("#SysSubject-datagrid").treegrid("update", {id: subject_id, row:{need_map: need_map}});
        }else {
            $.dialog.tips(result.message);
        }
    },"json");
    refreshTreegrid();
}

function action_do_renew(bill_falg){
    getBillContentByFlagForNav(bill_falg, "add");
    closeDialog();
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

function format_subject_mapping_status(value, row){
    if (row["std_subject_id"] == "" || row["std_subject_id"] == null || row["std_subject_id"] == 0){
        return "<span style='color:red'>未匹配</span>"
    }else{
        return "<span style='color:blue'>已匹配</span>";
    }
}
function format_subject_operate(value, row){
    var result = "";
    if (row["std_subject_id"] == "" || row["std_subject_id"] == null || row["std_subject_id"] == 0){
        result = "<a style='color:blue' href='javascript:;' onclick='showMappingDialog("+ row.id +")'>手动匹配</a>"
    }else{
        result = "<a style='color:blue' href='javascript:;'  onclick='showDisMappingDialog("+ row.id +")'>取消匹配</a>";
    }
    result = result + $.format("<a style='color:blue;padding-left: 10px' href='javascript:;'  onclick='showDetailForm(\"{0}\",{1}, \"{2}\")'>查看</a>", ["VcrSubject", row.id, "企业科目"]);
    result = result + $.format("<a style='color:red;padding-left: 10px' href='javascript:;'  onclick='deleteRows(\"{0}\",[{1}])'>删除</a>",["VcrSubject", row.id]);
    return result;
}
function format_subject_mapping(value, row){
    var result = "";
    if (row["std_subject_id"] == "" || row["std_subject_id"] == null || row["std_subject_id"] == 0){
        result = "<a style='color:blue' href='javascript:;' onclick='showMappingDialog("+ row.id +")'>手动匹配</a>"
    }else{
        result = "<span style='color:grey'></span>"
    }
    return result;
}
function format_subject_unmapping(value, row){
    var result = "";
    if (row["std_subject_id"] == "" || row["std_subject_id"] == null || row["std_subject_id"] == 0){
        result = "";
    }else{
        result = "<a style='color:blue' href='javascript:;'  onclick='showDisMappingDialog("+ row.id +")'>取消匹配</a>";
    }
    return result;
}

function blobFont(value, row){
    if (value) {
        return "<span style='font-weight: bold'>" + value + "</span>"
    }
}
function format_salebill_operate(value, row){
    if(row.id == undefined){
        return "";
    }
    var  result = $.format("<a style='color:blue;padding-left: 10px' href='javascript:;'  onclick='showDetailForm(\"{0}\",{1}, \"{2}\")'>查看</a>", ["VcrBill-sale", row.id, "自开票"]);
    result = result + $.format("<a style='color:red;padding-left: 10px' href='javascript:;'  onclick='deleteRows(\"{0}\",[{1}])'>删除</a>",["VcrBill-sale", row.id]);
    return result;
}
function format_buybill_operate(value, row){
    var  result = $.format("<a style='color:blue;padding-left: 10px' href='javascript:;'  onclick='showDetailForm(\"{0}\",{1}, \"{2}\")'>查看</a>", ["VcrBill-buy", row.id, "外取票"]);
    result = result + $.format("<a style='color:red;padding-left: 10px' href='javascript:;'  onclick='deleteRows(\"{0}\",[{1}])'>删除</a>",["VcrBill-buy", row.id]);
    return result;
}

function formatShowImageSelect(value, row){
    if (row.image_id == undefined || row.image_id == null || row.image_id == ""){
        return $.format("<a style='color:blue;padding-left: 10px' href='javascript:;'  onclick='VcrBillFunctions.showImageSelectDialog(\"{0}\")'>上传</a>", [row.id]);
    }else{
        return "";
    }
}
function formatDrafNeverCreateOperation(value, row){
    return $.format("<a style='color:blue;padding-left: 10px' href='javascript:;'  onclick='VcrVoucherDeafFunctions.showVoucherDrafEditDialog(\"{0}\")'>人工生成凭证</a>", [row.id]);
}

function formatDrafHasCreateOperation(value, row){
    var  result = "";
    if (row.error == "" || row.error == null){
        result = $.format("<a style='color:blue;padding-left: 10px' href='javascript:;'  onclick='showDetailForm(\"{0}\",{1}, \"{2}\")'>查看</a>", ["VcrVoucherDraf", row.id, "记账凭证"]);
    }else{
        result = $.format("<a style='color:red;padding-left: 10px' title='{3}' href='javascript:;'  onclick='showDetailForm(\"{0}\",{1}, \"{2}\")'>修正</a>", ["VcrVoucherDraf", row.id, "记账凭证", row.error]);
    }
    result = result + $.format("<a style='color:blue;padding-left: 10px' href='javascript:;' onclick='deleteRows(\"{0}\",[{1}])'>删除</a>",["VcrVoucherDraf", row.id]);
    return result;
}
function formatCompareBill(value, row){
   return $.format("<a style='color:blue;padding-left: 10px' href='javascript:;'  onclick='showDetailForm(\"{0}\",{1}, \"{2}\")'>{3}</a>", ["VcrBill", row.bill_id, "外取票", row.bill_no]);
}

function formatAuthState(value, row){
    return (value == 0 || value == "")?"<span style='color:red'>未认证</span>":"<span style='color:blue'>已认证</span>"
}
//传入："#VcrBill-toolbar .choose-month a"
function monthChoose($targets, onChoose){
    $.each($targets, function(i, o){
        $(o).on("click", function(){
            var year = $(this).siblings("select").combobox("getValue");
            if (year != "0") {
                var isChecked = $(this).hasClass("month-on");
                $(this).siblings("a").removeClass("month-on");
                $(this).toggleClass("month-on");
                if (onChoose != undefined) {
                    onChoose.call(this, i, !isChecked);
                }
            }else{
                $.dialog.tips("请选择对应年份");
            }
        })
    })
}
//传入："#VcrBill-toolbar .choose-month"
function getMonthChooseValue($target, separator){
    if (separator == undefined){separator = "/";}
    var year = $target.find("select").combobox("getValue");
    var month = $target.find("a").index($target.find("a.month-on"));
    if (month == -1){
        return year;
    }
    return (year + separator + padLeft(""+month, 2, "0"));
}

function formatVcrBillOrigin(value,row){
    return value == 2 ? "商户录入":"客户录入";
}

function formatBillOrigin(value,row){
    if(row.id == undefined){
        return "";
    }
    var origin = row.origin == 2 ? "商户":"客户";
    var html = "";
    if(value == "" || value == null || value == undefined){
        html = `<span style="color:green;">${origin}录入</span>`;
    }else{
        html = `<span>${origin}导入</span>`;;
    }
    return html;
}