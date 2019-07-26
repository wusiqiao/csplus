;(function ($) {
    $.loading = function (options) {
        if ($('#loadingToast').length === 0) {
            var toast_content =
                    '<div id="loadingToast" class="weui_loading_toast" style="display:none;">' +
                    '<div class="weui_mask_transparent"></div>' +
                    '<div class="weui_toast">' +
                    '<div class="weui_loading">' +
                    '<div class="weui_loading_leaf weui_loading_leaf_0"></div>' +
                    '<div class="weui_loading_leaf weui_loading_leaf_1"></div>' +
                    '<div class="weui_loading_leaf weui_loading_leaf_2"></div>' +
                    '<div class="weui_loading_leaf weui_loading_leaf_3"></div>' +
                    '<div class="weui_loading_leaf weui_loading_leaf_4"></div>' +
                    '<div class="weui_loading_leaf weui_loading_leaf_5"></div>' +
                    '<div class="weui_loading_leaf weui_loading_leaf_6"></div>' +
                    '<div class="weui_loading_leaf weui_loading_leaf_7"></div>' +
                    '<div class="weui_loading_leaf weui_loading_leaf_8"></div>' +
                    '<div class="weui_loading_leaf weui_loading_leaf_9"></div>' +
                    '<div class="weui_loading_leaf weui_loading_leaf_10"></div>' +
                    '<div class="weui_loading_leaf weui_loading_leaf_11"></div>' +
                    '</div>' +
                    '<p class="weui_toast_content">数据加载中</p>' +
                    '</div>' +
                    '</div>';
            $(toast_content).appendTo($('body'));
        }
        if (typeof options === 'string') {
            switch (options) {
                case 'show':
                    $('#loadingToast').show();
                    break;
                case 'hide':
                    $('#loadingToast').hide();
                    break;
            }
        }
    }
})($);

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
Date.prototype.addDays = function (d) {
    this.setDate(this.getDate() + d);
};

Date.prototype.addWeeks = function (w) {
    this.addDays(w * 7);
};

Date.prototype.addMonths = function (m) {
    var d = this.getDate();
    this.setMonth(this.getMonth() + m);

    if (this.getDate() < d)
        this.setDate(0);
};

Date.prototype.addYears = function (y) {
    var m = this.getMonth();
    this.setFullYear(this.getFullYear() + y);

    if (m < this.getMonth()) {
        this.setDate(0);
    }
};

function parseTemplate(target, jsobject){
    var tpl = $(target).prop("outerHTML").replace("display:none","");
    var reg = new RegExp("\{%([^%}]*)?%\}", "g");
    var matchs = tpl.match(reg);
    for(var i=0;i<matchs.length;i++){
        var key = matchs[i].replace("{%","").replace("%}", "");
        var val = jsobject.hasOwnProperty(key)?jsobject[key] : "";
        tpl = tpl.replace(matchs[i], val);
    }
    return tpl;
}

/**非异步加载模板函数
<ul class="mui-table-view" id="table-list">
    <li class="mui-table-view-cell table-item-tpl">
        <a class="mui-navigate-right">{%table_name%}</a>
    </li>
</ul> 
parseTableTemplate("#table-list", datas);       
*/
function parseTableTemplate(targetId, objects){
    var tpl_list = "";
    var tpl = $(targetId).find(".tpl-table-item").prop("outerHTML");
    $(targetId).children().not(".tpl-table-item").remove();
    var reg = new RegExp("\{%([^%}]*)?%\}", "g");
    var matchs = tpl.match(reg);
    $.each(objects, function () { //数据
        var row = this;
        var tpl_item = tpl.replace("tpl-table-item", "");
        for (var i = 0; i < matchs.length; i++) {
            var key = matchs[i].replace("{%", "").replace("%}", "");
            if (row.hasOwnProperty(key)) {
                var val = (row[key] === null) ? "" : row[key];
                tpl_item = tpl_item.replace(matchs[i], val);
            }
        }
        tpl_list = tpl_list + tpl_item;  
    });
    $(targetId).append(tpl_list);
}


function parseTemplateV2(target, jsobject, jokey_prefix) {
    if ($(target).length == 0) {
        return "";
    }
    var tpl = $(target).prop("outerHTML").replace("display:none", "");
    var reg = new RegExp("\{%([^%}]*)?%\}", "g");
    var matchs = tpl.match(reg);
    for (var i = 0; i < matchs.length; i++) {
        var key = matchs[i].replace("{%", "").replace("%}", "");
        if (jokey_prefix != undefined) {
            key = key.replace(jokey_prefix.replace(".", "") + ".", "");
        }
        if (jsobject.hasOwnProperty(key)) {
            var val = (jsobject[key] === null) ? "" : jsobject[key];
            tpl = tpl.replace(matchs[i], val);
        }
    }
    ;
    var list = $(target).find("list");
    $.each(list, function () {
        var name = $(this).attr("name");
        var id = $(this).attr("id");
        var list_tpl = "";
        if (jsobject.hasOwnProperty(name) && jsobject[name] !== null) {
            $.each(jsobject[name], function () {
                list_tpl += parseTemplateV2(list.children(0), this, id);
            });
            tpl = tpl.replace($(this).prop("outerHTML"), list_tpl);
        }
    });
    return tpl;
}

/**
 * 异步加载模板函数，按固定格式定义后，调用自动加载。
<div id="pullrefresh" class="mui-content mui-scroll-wrapper"  data-url="" page-size=10>
        <div class="mui-scroll">
                <div class="item-tpl" style="display:none">
                  
                </div>
                <!--数据列表-->
                <ul class="mui-table-view mui-table-view-chevron pullrefresh-list">

                </ul>
        </div>
</div>
 * 后台josn格式：   
 * $json["total"] = count($list);
 * $json["rows"] = $list;
 * @param {type} $target 加载对象
 * @param {type} option  url扩展参数，可以传入参数，排序等自由组合，后台自行分析
 * 
 */
function pullRefresh(selector, option, complete_callback){
    var result = {container: selector};
    var $target = $(selector);
    var id = $target.attr("data-pullToRefresh");
    if (id){
       mui.data[id].refresh(true);
    }    
    var $contents = $target.find("ul");
    var $tpl = $target.find(".item-tpl"); 
    var url = $target.attr("data-url");
    var has_tpl = $tpl.length > 0;//是否有模板,没有模板，就默认返回模板内容，直接添加
    var rows = $target.attr("rows");
    if (!rows){
        rows = 10;
    }
    $target.data("page",null);
    if ($.isFunction(option)){
        var data = option.call(this);
        $target.data("option", data);
    }else{
        $target.data("option",option);
    }
    $contents.find("li").remove();
    result.up = {
        auto: true,
        contentrefresh : "正在刷新...",//可选，正在加载状态时，上拉加载控件上显示的标题内容
        contentnomore:'没有更多数据了',//可选，请求完毕若没有更多数据时显示的提醒内容；
        callback : function(){
            var _this = this;
            var page = $target.data("page");
            if (!page){
                page = 0;
            }
            page++;
            option = $target.data("option")||{};
            $.extend(option, {rows: rows, page:page});
            $.post(url, option, function(data){
                    if (data.total > 0){
                        for(var i = 0; i < data.total; i++){
                            var li = document.createElement('li');
                            li.className = 'mui-table-li';
                            li.innerHTML = has_tpl?parseTemplate($tpl, data.rows[i]):data.rows[i];
                            $contents.append($(li));              
                        }
                        if (complete_callback !== undefined){
                            complete_callback();
                        }
                        if(data.rows.length < rows){ //最后一页
                            if (page > 1){ //第二页后才显示
                                var total = ((page -1) * rows) + data.rows.length;
                                // $target.find(".dropload-down").html(dropload_down.replace("{#total}", total).replace("{#title}","我们也有底线，"));
                            }else{
                                // $target.find(".dropload-down").html(dropload_down.replace("{#total}", data.rows.length).replace("{#title}",""));
                            }
                            _this.endPullUpToRefresh(true);
                        }else{
                            $target.data("page", page);
                            _this.endPullUpToRefresh(false);
                        }
                    }else{
                        if(data.rows == undefined ||　data.rows.length == 0 　){
                            $contents.append('<li class="empty-voucher"><img src="/Application/EShop/Public/images/voucher/emptyPic.png" alt="" width="31%"/><div style="text-align: center;color:#cccccc;line-height: 2.67rem;">列表是空的哦</div></li>');
                        }
                        _this.endPullUpToRefresh(true);
                    }                  
                },"json"
            );
        }
    }
    // result.down = {
    //     contentrefresh : "正在刷新...",//可选，正在加载状态时，上拉加载控件上显示的标题内容
    //     contentnomore:'没有更多数据了',//可选，请求完毕若没有更多数据时显示的提醒内容；
    //     callback :function(){
    //         var _this = this;
    //         option = option||{};
    //         $.extend(option, {rows: rows, page:1});
    //         $.post(url, option, function(data){
    //                 $contents.find("li").remove();
    //                 if (data.total > 0){
    //                     for(var i = 0; i < data.total; i++){
    //                         var li = document.createElement('li');
    //                         li.className = 'mui-table-view-cell';
    //                         li.innerHTML = has_tpl?parseTemplate($tpl, data.rows[i]):data.rows[i];
    //                         $contents.append($(li));
    //                     };
    //                     if (complete_callback !== undefined){
    //                         complete_callback();
    //                     }
    //                     $target.data("page", 1);
    //                 };
    //                _this.endPullDownToRefresh(data.total < rows); //refresh completed
    //             },"json"
    //         );
    //     }
    // }
    return result;
}


function viewCellClick(cell) {
    $(cell).siblings('.mui-table-view-cell').removeClass('checked').addClass('unchecked');
    $(cell).siblings('.mui-table-view-cell').find("i").hide();
    $(cell).removeClass('unchecked').addClass('checked');
    $(cell).find("i").show();
};

function confirmBack(){
    mui.confirm('是否返回？','',function(event){
        if (event.index == 1){
            history.back();
        }
    });
}

