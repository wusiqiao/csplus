$.format = function (source, params) {
    if (arguments.length == 1)
        return function () {
            var args = $.makeArray(arguments);
            args.unshift(source);
            return $.format.apply(this, args);
        };
    if (arguments.length > 2 && (!$.isArray(params))) {
        params = $.makeArray(arguments).slice(1);
    }
    if (!$.isArray(params)) {
        params = [params];
    }
    $.each(params, function (i, n) {
        source = source.replace(new RegExp("\\{" + i + "\\}", "g"), n);
    });
    return source;
};
Date.prototype.format = function (format) {
    var o = {
        "M+": this.getMonth() + 1,
        "d+": this.getDate(),
        "h+": this.getHours(),
        "m+": this.getMinutes(),
        "s+": this.getSeconds(),
        "q+": Math.floor((this.getMonth() + 3) / 3),
        "S": this.getMilliseconds()
    }
    if (/(y+)/.test(format)) {
        format = format.replace(RegExp.$1, (this.getFullYear() + "").substr(4 - RegExp.$1.length));
    }
    for (var k in o) {
        if (new RegExp("(" + k + ")").test(format)) {
            format = format.replace(RegExp.$1, RegExp.$1.length == 1 ? o[k] : ("00" + o[k]).substr(("" + o[k]).length));
        }
    }
    return format;
}
Date.prototype.addDays = function(d) {
    this.setDate(this.getDate() + parseInt(d));
};

Date.prototype.addWeeks = function(w) {
    this.addDays(parseInt(w) * 7);
};

Date.prototype.addMonths = function(m) {
    var d = this.getDate();
    this.setMonth(this.getMonth() + parseInt(m));

    if (this.getDate() < d)
        this.setDate(0);
};

Date.prototype.addYears = function(y) {
    var m = this.getMonth();
    this.setFullYear(this.getFullYear() + parseInt(y));

    if (m < this.getMonth()) {
        this.setDate(0);
    }
};
function formatDateTime(value, row, index)
{
    if(!isEmptyDateTime(value)) {
        var separator = $.fn.datebox.defaults.formatSeparator || "/";
        if ($.isNumeric(value)) {
            return new Date(parseInt(value) * 1000).format("yyyy" + separator + "MM" + separator + "dd hh:mm:ss");
        } else {
            return new Date(Date.parse(value)).format("yyyy" + separator + "MM" + separator + "dd hh:mm:ss");
        }
    }else{
        return "";
    }
}

function isEmptyDateTime(value){
    var result = false;
    if (value === null || value === undefined || value == "0" || value==="") {
        result = true
    }else{
        if (typeof(value) == "object"){
            if (value.getFullYear() == 1970){
                result = true;
            }
        }
    }
    return result;
}

function formatDate(value, row, index)
{
    var separator = $.fn.datebox.defaults.formatSeparator || "/";
    return formatDateInner(value, separator);
}

function formatYM(value, row, index) {
    if (value === null || value === undefined || value === 0 || value === "0") {
        return "";
    }
    if(!isEmptyDateTime(value)){
        var separator = $.fn.datebox.defaults.formatSeparator || "/";
        if ($.isNumeric(value)) {
            return new Date(parseInt(value) * 1000).format("yyyy" + separator + "MM");
        } else {
            return new Date(Date.parse(value)).format("yyyy" + separator + "MM");
        }
    }
}
function formatDateInner(value, separator)
{
    if(!isEmptyDateTime(value)){
        if ($.isNumeric(value)) {
            return new Date(parseInt(value) * 1000).format("yyyy" + separator + "MM" + separator + "dd");
        } else {
            return new Date(Date.parse(value)).format("yyyy" + separator + "MM" + separator + "dd");
        }
    }else{
        return "";
    }
}

function parseDate(s) {
    if (!s)
        return new Date();
    var ss = (s.split('/'));
    var y = parseInt(ss[0], 10);
    var m = parseInt(ss[1], 10);
    var d = parseInt(ss[2], 10);
    if (!isNaN(y) && !isNaN(m) && !isNaN(d)) {
        return new Date(y, m - 1, d);
    } else {
        return new Date();
    }
}

function formatCombobox(row) {
    var opts = $(this).combobox('options');
    var prefix = "";
    if (row["parent_name"]) {
        prefix = row["parent_name"] + "/";
    }
    return prefix + row[opts.textField];
}

function formatThumbImage(value, row, index) {
    var src = app_config.path + "Public/images/thumb.png";
    var ret = $.format("<image src='{0}' alt='{1}' value='{1}' class='thumb-image' onmouseover=\"showThumb(this)\" />", src, value);
    return ret;
}

function showThumb(sender, reset) {
    var value = $(sender).attr("value");
    var parent = $(sender).parents().get(0);
    var content = "<image style='width:100px;text-align:center' src='" + value + "' />";
    if (reset) {
        $(parent).miniTip("reset");
    }
    $(parent).miniTip({content: content, width: "100px", height: "auto", direction: "left"});
}
function parseOptions(obj) {
    var s = $.trim($(obj).attr("data-options"));
    if (s) {
        if (s.substring(0, 1) != "{") {
            s = "{" + s + "}";
        }
        return eval('(' + s + ')');
    }
    return null;
}

function function_exists(fname, object) {
    object = !object || typeof object !== 'object' ? window : object;
    return typeof object[fname] === 'function';
}

function uuid(len, radix) {
    var chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'.split('');
    var uuid = [], i;
    radix = radix || chars.length;

    if (len) {
        // Compact form
        for (i = 0; i < len; i++)
            uuid[i] = chars[0 | Math.random() * radix];
    } else {
        // rfc4122, version 4 form
        var r;

        // rfc4122 requires these characters
        uuid[8] = uuid[13] = uuid[18] = uuid[23] = '-';
        uuid[14] = '4';

        // Fill in random data.  At i==19 set the high bits of clock sequence as
        // per rfc4122, sec. 4.1.5
        for (i = 0; i < 36; i++) {
            if (!uuid[i]) {
                r = 0 | Math.random() * 16;
                uuid[i] = chars[(i == 19) ? (r & 0x3) | 0x8 : r];
            }
        }
    }

    return uuid.join('');
}

function formatValidState(value, row, index) {
    if (value === "1") {
        return "<i class='icon-ok-sign icon-large' style='color:#00aaee' title='生效'></i>";
    } else {
        return "<i class='icon-ok-sign icon-large' style='color:#ccc' title='未生效'></i>";
    }
}

function formatTreeFileIcon(icon, value, row, index) {
    return "<i class='" + icon + " icon-large' style='color:#ccc;padding-right:16px'></i>" + value;
}

function formatCompanyName(value, row, index) {
    return formatTreeFileIcon("icon-building", value);
}

function formatYearMonth(value, row, index) {
    var monthTexts = ["一月", "二月", "三月", "四月", "五月", "六月", "七月", "八月", "九月", "十月", "十一月", "十二月"];
    if ($.isNumeric(value)) {
        var month = parseInt(value);
        if (month > 0 && month < 13) {
            return monthTexts[month - 1];
        }
    }
    return "";
}


function initialSearchYearMonth(selectTarget) {
    $(selectTarget).append("<option value=''>（全部）</option>");
    $(selectTarget).append("<option value='NULL'>未设置</option>");
    initialYearMonth(selectTarget);
}

function initialYearMonth(selectTarget, value) {
    for (var i = 1; i < 13; i++) {
        var option = "<option value=" + i + ">" + formatYearMonth(i) + "</option>";
        $(selectTarget).append(option);
    }
    if (value) {
        $(selectTarget).find("option[value=" + value + "]").attr("selected", true);
    }
    $(selectTarget).trigger("chosen:updated");
}

function baidumap() {
    $.get("ComCompany/baidumap",
            function (data, status) {
                $.dialog({
                    id: "baidumap",
                    title: "选取坐标",
                    autoSize: true,
                    content: data,
                    lock: true,
                    max: false,
                    min: false
                });
            });
}

function padLeft(str, length, pad_str) {
    if (str.length >= length)
        return str.substr(0, length);
    else
        if (pad_str == undefined){
            pad_str = "0";
        }
        return padLeft(pad_str + str, length, pad_str);
}
function padRight(str, length, pad_str) {
    if (str.length >= length)
        return str.substr(0, length);
    else
        if (pad_str == undefined){
            pad_str = "0";
        }
        return padRight(str + pad_str, length, pad_str);
}

function getWeekDate(value){
    var dayWeekTitle = ["星期天", "星期一", "星期二", "星期三", "星期四", "星期五", "星期六"];
    if ($.isNumeric(value)) {
        var dayofWeek = parseInt(value);
        if (dayofWeek >=0 && dayofWeek < 7) {
            return dayWeekTitle[dayofWeek];
        }
    }
    return "";
}
function getStringsValue(text, separator, index){
    if (!text) return '';
    var txt_array = text.split(separator);
    if (txt_array.length > index){
       return  txt_array[index];
    }else{
       return text;
    }  
}

function getEasyuiInputVal(tagId){
    if ($(tagId).data("textbox")){
        $(tagId).data("textbox").textbox.find(".textbox-value").val();
    }
    return "";
}

function removeJsonQuote(json){
   var ret = json;//.replace(/([^:]")/g, '\\\"');  
   return ret;
}

function formatTF(value, row, index) {
    if (value == "1") {
        return "<i class='fa fa-check fa-lg' style='color:#00aaee' title='是'></i>";
    } else {
        return "";
    }
}

//凭证状态
function formatVoucherState(value){
    var color = "";
    if(value == "已完成"){
        color = "green";
    }else if(value == "待复核"){
        color = "red";
    }else if(value == "待审核"){
        color = "blue";
    }
    var html = "<span style='color:"+color+"'>"+value+"</span>";
    return html;
}

function toString(value){
    if (value == undefined || value == null || value == ""){
        return "";
    }
    return value;
}


function formatUpDown(controller, gridId, row){
    var upClick = "moveRowUp('" + controller + "','" + gridId + "'," + row.id + ")";
    var downClick = "moveRowDown('" + controller + "','" + gridId + "'," + row.id + ")";
    return "<a href='javascript:;' onclick=" +  upClick + "><i class='fa fa-arrow-up' style='color:#00aaee;padding: 0px 5px' title='上移'></i></a>" +
        "<a href='javascript:;' onclick=" +  downClick + "><i class='fa fa-arrow-down' style='color:#00aaee' title='下移'></i></a>";
}

function moveRowUp(controller, gridId, id){
    if (id != undefined) {
        var target = $(gridId);
        if (target.hasClass("treegrid")) {
            var selectRow = $('.datagrid-row[node-id="' + id + '"]');
            var preNode = selectRow.prev();
            if (!preNode.hasClass("datagrid-row")) {
                preNode = preNode.prev();
            }
            if (preNode.hasClass("datagrid-row")){
                var preId = preNode.attr("node-id");
                var n2 = $(target).treegrid("pop", id);
                $.post(controller + "/exchangeOrder", {id_front: id, id_behind: preId}, function(result){
                    if (result.code == 0){
                        $(target).treegrid("insert", {before: preId, data: n2});
                        $(target).treegrid("select",id);
                    }
                },"json");
            }
        }else{
            var rowSelected = $(target).datagrid("getSelected");
            var index = $(target).datagrid("getRowIndex", rowSelected);
            var toup = $(target).datagrid('getData').rows[index];
            var todown = $(target).datagrid('getData').rows[index - 1];
            $.post(controller + "/exchangeOrder", {id_front: toup.id, id_behind: todown.id}, function(result){
                if (result.code == 0){
                    $(target).datagrid('getData').rows[index] = todown;
                    $(target).datagrid('getData').rows[index - 1] = toup;
                    $(target).datagrid('refreshRow', index);
                    $(target).datagrid('unselectRow', index);
                    $(target).datagrid('refreshRow', index - 1);
                    $(target).datagrid('selectRow', index - 1);
                }
            },"json");
        }
    }
}

function moveRowDown(controller, gridId, id){
    if (id != undefined) {
        var target = $(gridId);
        if (target.hasClass("treegrid")){
            var selectRow = $('.datagrid-row[node-id="' + id + '"]');
            var nextNode = selectRow.next();
            if (!nextNode.hasClass("datagrid-row")) {
                nextNode = nextNode.next();
            }
            if (nextNode.hasClass("datagrid-row")) {
                var nextId = nextNode.attr("node-id");
                var n2 = $(target).treegrid("pop",nextId);
                $.post(controller + "/exchangeOrder", {id_front: nextId, id_behind: id}, function(result){
                    if (result.code == 0){
                        $(target).treegrid("insert",{before:id, data:n2});
                    }
                },"json");
            }
        }else{
            var rowSelected = $(target).datagrid("getSelected");
            var index = $(target).datagrid("getRowIndex", rowSelected);
            var rows = $(target).datagrid('getRows').length;
            var todown = $(target).datagrid('getData').rows[index];
            var toup = $(target).datagrid('getData').rows[index + 1];
            $.post(controller + "/exchangeOrder", {id_front: toup.id, id_behind: todown.id}, function(result){
                if (result.code == 0){
                    $(target).datagrid('getData').rows[index + 1] = todown;
                    $(target).datagrid('getData').rows[index] = toup;
                    $(target).datagrid('refreshRow', index);
                    $(target).datagrid('unselectRow', index);
                    $(target).datagrid('refreshRow', index + 1);
                    $(target).datagrid('selectRow', index + 1);
                }
            },"json");
        }
    }
}
function tryParseFloat(str, defaultValue) {
    var value = parseFloat(str);
    return Number.isNaN(value) ? defaultValue : value;
}

