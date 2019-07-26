/* 
 akun 2017/01/24
 */

function validateForm($form) {
    var validator = $form.validate();
    if (!validator.checkForm()) {
        $.each(validator.errorList, function (value) {
            var $sender = $(this.element);
            var errmsg = this.message;
            var msg_title = $sender.attr("msg-title");
            if (msg_title) {
                errmsg = msg_title + "：" + this.message;
            }
            var errtag = $sender;
            if ($sender.attr("errtag")) {
                errtag = $("#" + $sender.attr("errtag"));
            }
            layer.tips(errmsg, errtag, {tips: 3});
            //$sender.focus();
            validator.resetForm();
            return false;
        });
    } else {
        return true;
    }
}

function parseTemplate(target, jsobject){
    var tpl = $(target).prop("outerHTML").replace("display:none","");
    var reg = new RegExp("\{%([^%}]*)?%\}", "g");
    var matchs = tpl.match(reg);
    if (matchs != null) {
        for (var i = 0; i < matchs.length; i++) {
            var key = matchs[i].replace("{%", "").replace("%}", "");
            var val = $.isEmptyObject(jsobject[key]) ? "" : jsobject[key];
            tpl = tpl.replace(matchs[i], val);
        }
        return tpl;
    }
    return "";
}
/**
 * 异步加载模板函数，按固定格式定义后，调用自动加载。
 * <div class="hsui-sync-refresh" data-url="__MODULE__/Index/get_region_tasks" page-size="10">
 * <div class="hsui-sync-refresh-content"> 
 * <div class="single-require item-wrap" style="display:none">
 * <div><span>{%task_xx%}</span></div>
 * </div>
 * </div>
 * </div>
 * 后台josn格式：   
 * $json["count"] = count($list);
 * $json["list"] = $list;
 * @param {type} $target 加载对象
 * @param {type} option  url扩展参数，可以传入参数，排序等自由组合，后台自行分析
 * 
 */
function dropload($target, option, complete_callback, getOption_callback){
    var $contents = $target.find(".hsui-sync-refresh-content");
    var $tpl = $contents.find(".item-wrap");
    var page_size = $target.attr("page-size");
    if (!page_size){
        page_size = 10;
    }
    $target.data("paging",null);
    $target.find(".dropload-down").remove();
    $contents.find(".item-wrap:visible").remove();
    var url = $target.attr("data-url");
    $target.dropload({
        scrollArea : window,
        domUp : {
            domClass   : 'dropload-up',
            // 下拉过程显示内容
            domRefresh : '<div class="dropload-refresh">↓下拉刷新</div>',
            // 下拉到一定程度显示提示内容
            domUpdate  : '<div class="dropload-update">↑释放更新</div>',
            // 释放后显示内容
            domLoad    : '<div class="dropload-load"><span class="loading"></span>加载中...</div>'
        },
        domDown : {
            domClass   : 'dropload-down',
            // 滑动到底部显示内容
            domRefresh : '<div class="dropload-refresh">↑上拉加载</div>',
            // 内容加载过程中显示内容
            domLoad    : '<div class="dropload-load"><span class="loading"></span>加载中...</div>',
            // 没有更多内容-显示提示
            domNoData  : '<div class="dropload-noData"><div class="empty-voucher"><img src="/Application/EShop/Public/images/voucher/emptyPic.png" alt="" width="31%"/><div style="text-align: center;color:#cccccc;line-height: 2.67rem;">列表是空的哦</div></div></div>'
        },
        // 1 . 下拉刷新 回调函数
        loadUpFn : function(me){
            option = option||{};
            if (getOption_callback != undefined && getOption_callback != null){
                var data = getOption_callback();
                $.extend(option, data);
            }
            $.extend(option, {page_size: page_size, paging:1});
            var jqxhr = $.post(url, option, function(data){
                    if (data.count > 0){
                        var result = '';
                        for(var i = 0; i < data.count; i++){
                            result += parseTemplate($tpl, data.list[i]);
                        };
                        $contents.html(result);
                        if (complete_callback != undefined && complete_callback != null){
                            complete_callback();
                        }
                        $target.data("paging", 1);                        
                    }
                    me.resetload();
                },"json"                
            ).error(function(){
                me.noData();
                me.resetload();
            }); 
        },
        // 2 . 上拉加载更多 回调函数
        loadDownFn : function(me){
            var paging = $target.data("paging");
            if (!paging){
                paging = 0;
            }
            paging++;
            option = option||{};
            if (getOption_callback != undefined && getOption_callback != null){
                var data = getOption_callback();
                $.extend(option, data);
            }
            $.extend(option, {page_size: page_size, paging:paging});
            var jqxhr = $.post(url, option, function(data){
                    if (data.count > 0){
                        var result = '';
                        for(var i = 0; i < data.count; i++){
                            result += parseTemplate($tpl, data.list[i]);                  
                        }
                        $contents.append(result);
                        if (complete_callback != undefined  && complete_callback != null){
                            complete_callback();
                        }
                        if(data.list.length<page_size){
                            me.lock();
                            var dropload_down =  '<div class="col-xs-12" style="padding: 0 !important;text-align: center;">\n\
                                        <span style="display: inline-block; width: 13%; border-top: 1px solid #e4e4e4 ;"></span>\n\
                                        <span style="display: inline-block;width: 30%;color: #999999;vertical-align: -20%; ">\n\
                                        {#title}共有<strong>&nbsp;{#total}&nbsp;</strong>条记录\n\
                                        </span>\n\
                                        <span style="display: inline-block; width: 13%; border-top: 1px solid #e4e4e4 ;"></span>\n\
                                        </div>';
                            if (paging > 1){ //第二页后才显示
                                var total = ((paging -1) * page_size) + data.list.length;
                                $target.find(".dropload-down").html(dropload_down.replace("{#total}", total).replace("{#title}","我们也有底线，"));
                            }else{
                                $target.find(".dropload-down").html(dropload_down.replace("{#total}", data.list.length).replace("{#title}",""));
                            }
                            return false;
                        }else{

                            $target.data("paging", paging);
                        }
                    }else{
                        if(paging > 1){
                            me.lock();
                            var dropload_down =  '<div class="col-xs-12" style="padding: 0 !important;text-align: center;">\n\
                                        <span style="display: inline-block; width: 13%; border-top: 1px solid #e4e4e4 ;"></span>\n\
                                        <span style="display: inline-block;width: 65%;color: #999999;vertical-align: -20%; ">\n\
                                        {#title}共有<strong>&nbsp;{#total}&nbsp;</strong>条记录\n\
                                        </span>\n\
                                        <span style="display: inline-block; width: 13%; border-top: 1px solid #e4e4e4 ;"></span>\n\
                                        </div>';
                            var total = (paging -1) * page_size;
                            $target.find(".dropload-down").html(dropload_down.replace("{#total}", total).replace("{#title}","我们也有底线，"));
                            return false;
                        }else{
                           me.lock();
                           me.noData();  
                        } 
                    }
                    me.resetload();                    
                },"json"
            ).error(function(){
                me.noData();
                me.resetload();
            });            
        },
        threshold : 50 // 什么作用???
    });
}

//function add_

