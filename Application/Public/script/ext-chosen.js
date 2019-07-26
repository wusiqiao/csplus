(function ($) {
    function initial(target) {
        var state = $.data(target, 'extChosen');
        var options = state.options;
        loadData(target);
        var show_select_btn = (options.select_key_url !== undefined);         //多选
        if (options.search_async) {
            $(target).ajaxChosen(
            {
                type: 'POST',
                url: options.search_key_url,
                data: {params: options.params, fields:options.fields},
                dataType: 'json',
                minTermLength: 1
            },
            function (data) {
                var terms = {};
                $.each(data, function (i, val) {
                    terms[i] = val;
                });
                return terms;
            },
            {
                show_select_btn: show_select_btn
            });
        } else {
            $(target).chosen({disable_search_threshold: 10, show_select_btn: show_select_btn});
        }
        if (options.select_key_url) {
            $(target).bind("wakeup", function (event) {
                var title = "选择" + options.title || "";
                createDialog(options.select_key_url, title, constant.DIALOG_SELECT_TAG, {sender: $(target), params: options.params});
            });
        }
    }

    /*修改了内部chosen
     * 1、添加"_input_xxx"(xxx为select的name)的input,Option有变化时候，value=1，方便数据处理
     * 2、多选框添加查询按钮，通过option["display_search_btn"]控制显示，实现在bind("wakeup")去实现。
     * 3、全取选项：all设置为true时，忽略value选项，取回全部资料，否则取回value对应的记录
     * 4、取回全部或取回当前记录对应的选择项，一般数据量大时，select_all必须设置false
     * 5、异步查询选项：search_async设置true时，all强制设置为false,初始取回当前记录或前10条记录
     * 6、当设置parent时，会自动缓存
     */
    function loadData(target) {
        function setValue(data) {            
            $.each(data, function (i, element) {
                var value = String(element[options.idField]);
                var text = element[options.textField];
                var option = $("<option />").attr('value', value).html(text);
                if (options.value) {
                    if ($.inArray(value, val_arr) !== -1) {
                        $(option).attr('selected', 'selected');                        
                    }
                }
                if (element['disabled']){
                   $(option).attr('disabled', 'disabled'); 
                } 
                $.data(option.get(0), "option_data", element);
                $(option).appendTo($(target));
            });
//            if (data && data.length > 0 && !options.value){
//                $(target).get(0).selectedIndex = 0;
//            }
            //触发change事件
            var options_selected = $(target).find("option:selected");
            if (options_selected.length > 0){
                var option_data = $.data(options_selected.get(0), "option_data");
                if (option_data){
                    $(target).trigger("change", {'selected': option_data["value"]});
                }
            }
            $(target).trigger("chosen:updated");
        }
        var options = $.data(target, 'extChosen').options;
        var val_arr = options.value ? options.value.split(",") : [];
        var cacheKey = _getCacheKey(options);
        var cacheData = $.data(document.body, cacheKey);
        if (cacheData && options.cache) {
            setValue(cacheData);
        } else {
            var selected = options.value;//options.all ? "" : options.value;
            if (options.search_key_url && (options.search_async || options.all || selected)) { //只有在全选或需要异步的情况下才填充列表              
                $.ajax({
                    url: "/" + options.search_key_url,
                    data: {selected: selected, select_all: options.all, params: options.params, fields:options.fields},
                    dataType: "json",
                    async: options.asyncLoad,
                    type: "POST",
                    success: function (data, textStatus, jqXHR) {
                        if (options.all && options.cache) {  //基本资料都是默认取回全部的，数据比较小，可以缓存。                         
                            $.data(document.body, cacheKey, data);
                        }
                        if (options.empty_line){
                            $("<option value=''>&nbsp;&nbsp;</option>").appendTo($(target)); //默认空选项
                        }
                        setValue(data);
                    }
                });
            }
            ;
        }
    }

    function appendAll(target, rows) {
        var options = $.data(target, 'extChosen').options;
        //如果有设置selectXXField
        var textField = options.selectTextField?options.selectTextField:options.textField;
        var idField = options.selectIdField?options.selectIdField:options.textField;
        $.each(rows, function (i, element) {
            var option_select = $(target).find("option[value='" + element[idField] + "']");
            if (option_select.length === 0) {
                var option = $("<option />").attr('value', element[idField]).html(element[textField]);
                $(option).appendTo($(target)).attr("selected", "selected");
                $(target).trigger("change", {'selected': element[idField]});
            } else {
                if (!option_select.attr("selected")) {
                    $(target).trigger("change", {'selected': element[idField]});
                }
                option_select.attr("selected", "selected");
            }
        });
        $(target).trigger("chosen:updated");
    }
    
    //获取其他字段值
    function getRow(target, value){
        var option = $(target).find("option[value="+ value +"]");
        if (option.length > 0){
            return $.data(option.get(0), "option_data");
        }
        return null;
    }
    
    //获取其他字段值
    function getValue(target){
        var result = new Array();
        var option = $(target).next().find("a.search-choice-close");
        if (option.length > 0){
            $.each(option, function(){
                var index = $(this).attr("data-option-array-index");
                result.push($(target).find("option").eq(index).val());
            });
            return result.join(",");
        }
        return null;
    }
    
    //获取
    function getText(target){
        var result = new Array();
        var option = $(target).next().find("li.search-choice");
        if (option.length > 0){
            $.each(option, function(){
                result.push($(this).attr("title"));
            });
            return result.join(",");
        }
        return null;
    }
    
    function _getCacheKey(options){
        return 'extChosenCache' + options.search_key_url;
    }
    $.fn.extChosen = function (options, param) {
        if (typeof options === 'string') {
            var method = $.fn.extChosen.methods[options];
            if (method) {
                return method(this, param);
            }
            ;
        }
        options = options || {};
        return this.each(function () {
            var state = $.data(this, 'extChosen');
            if (state) {
                $.extend(state.options, options);
            } else {
                $.data(this, 'extChosen', {options: $.extend({}, $.fn.extChosen.defaults, parseOptions(this), options)});
                initial(this);
            }
        });
    };

    $.fn.extChosen.defaults = $.extend({}, {
        cache: false,
        asyncLoad: true, //加载数据方式，默认异步 
        params: {}, //附加条件
        textField: "text", //名称字段
        idField: "value",
        selectIdField:"id", //选择取回字段
        selectTextField:"",
        fields:""  //其他字段列表用","分开，取回后存放在data的fields关键字下。
    });

    $.fn.extChosen.methods = {
        appendAll: function (jq, rows) {
            return jq.each(function () {
                appendAll(this, rows);
            });
        },
        reload: function (jq, options) {
            return jq.each(function () {
                $(this).removeData("extChosen");
                //保留原有的固定项
                var select_option = $(this).find("option[value='']");
                $(this).empty();
                if (select_option.length > 0){
                  $(select_option).appendTo($(this)).attr("selected", "selected");  
                }
                $(this).extChosen(options);
            });
        },
        disabled: function(jq, value){
           return jq.each(function () {
                $(this).attr('disabled', value).trigger("chosen:updated");
           }); 
        },
        getRow: function(jq, value){
           return getRow(jq[0], value);
        },
        getValue:function(jq){
            return getValue(jq[0]);
        },
        getText:function(jq){
            return getText(jq[0]);
        },
        setValue:function(jq, value){
           $(jq[0]).val(value).trigger("chosen:updated"); 
        }
    };
})(jQuery);