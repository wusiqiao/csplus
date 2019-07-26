constant = {
    SYSTEM_ID: "Admin",
    DATAGRID_TAG_ID: "#datagrid",
    DATAFORM_TAG_ID: "#dataform",
    DIALOG_TAG: "dialog_main",
    DIALOG_SELECT_TAG: "dialog-select"
};

$.extend($.fn.datagrid.defaults, {
    animate: true,
    method: 'post',
    border: false,
    striped: true,
    fit: true,
    singleSelect: false,
    rownumbers: true,
    pageNumber: 1,
    pageSize: 50,
    pagination: true,
    ctrlSelect: true,
    loadMsg: '正在加载，请稍等...'
});

$.extend($.fn.treegrid.defaults, {
       collapsible:true,
       animate:true,
       method:'post',
       pagination:true,
       pageSize:20,
       pageNumber:1,
       loadMsg:'正在加载，请稍等...',
       border:true,
       striped:true,
       fit:true, 
       ctrlSelect:true,
       singleSelect: false,
       idField:'id',
       treeField:'name',
       rownumbers:true
});

$.extend($.fn.pagination.defaults, {
    showRefresh: false
});

$.extend($.fn.validatebox.defaults.rules, {
    idcard: {// 验证身份证 
        validator: function (value) {
            return /^\d{15}(\d{2}[A-Za-z0-9])?$/i.test(value);
        },
        message: '身份证号码格式不正确'
    },
    minLength: {
        validator: function (value, param) {
            return value.length >= param[0];
        },
        message: '请输入至少{0}个字符.'
    },
    length: {
        validator: function (value, param) {
            var len = $.trim(value).length;
            return len >= param[0] && len <= param[1];
        },
        message: "输入内容长度必须介于{0}和{1}之间."
    },
    maxlength: {
        validator: function (value, param) {
            var len = $.trim(value).length;
            return len <= param[0];
        },
        message: "输入内容长度不能超过{0}个字."
    },
    phone: {// 验证电话号码 
        validator: function (value) {
            return /^((\(\d{2,3}\))|(\d{3}\-))?(\(0\d{2,3}\)|0\d{2,3}-)?[1-9]\d{6,7}(\-\d{1,4})?$/i.test(value);
        },
        message: '格式不正确,请使用下面格式:020-88888888'
    },
    mobile: {// 验证手机号码 
        validator: function (value) {
            return /^(13|15|18|17|19)\d{9}$/i.test(value);
        },
        message: '手机号码格式不正确'
    },
    number: {// 验证整数或小数 
        validator: function (value) {
           return /^[-]?\d+(\.\d+)?$/i.test(value);
        },
        message: '请输入数字，并确保格式正确'
    },
    currency: {// 验证货币 
        validator: function (value) {
            return /^\d+(\.\d+)?$/i.test(value);
        },
        message: '货币格式不正确'
    },
    qq: {// 验证QQ,从10000开始 
        validator: function (value) {
            return /^[1-9]\d{4,9}$/i.test(value);
        },
        message: 'QQ号码格式不正确'
    },
    integer: {// 验证整数 
        validator: function (value) {
            return /^[+]?[1-9]+\d*$/i.test(value);
        },
        message: '请输入整数'
    },
    age: {// 验证年龄
        validator: function (value) {
            return /^(?:[1-9][0-9]?|1[01][0-9]|120)$/i.test(value);
        },
        message: '年龄必须是0到120之间的整数'
    },
    chinese: {// 验证中文 
        validator: function (value) {
            return /^[\Α-\￥]+$/i.test(value);
        },
        message: '请输入中文'
    },
    english: {// 验证英语 
        validator: function (value) {
            return /^[A-Za-z]+$/i.test(value);
        },
        message: '请输入英文'
    },
    unnormal: {// 验证是否包含空格和非法字符 
        validator: function (value) {
            return /.+/i.test(value);
        },
        message: '输入值不能为空和包含其他非法字符'
    },
    username: {// 验证用户名 
        validator: function (value) {
            return /^[a-zA-Z][a-zA-Z0-9_]{5,15}$/i.test(value);
        },
        message: '用户名不合法（字母开头，允许6-16字节，允许字母数字下划线）'
    },
    faxno: {// 验证传真 
        validator: function (value) {
            //            return /^[+]{0,1}(\d){1,3}[ ]?([-]?((\d)|[ ]){1,12})+$/i.test(value); 
            return /^((\(\d{2,3}\))|(\d{3}\-))?(\(0\d{2,3}\)|0\d{2,3}-)?[1-9]\d{6,7}(\-\d{1,4})?$/i.test(value);
        },
        message: '传真号码不正确'
    },
    zip: {// 验证邮政编码 
        validator: function (value) {
            return /^[1-9]\d{5}$/i.test(value);
        },
        message: '邮政编码格式不正确'
    },
    ip: {// 验证IP地址 
        validator: function (value) {
            return /d+.d+.d+.d+/i.test(value);
        },
        message: 'IP地址格式不正确'
    },
    name: {// 验证姓名，可以是中文或英文 
        validator: function (value) {
            return /^[\Α-\￥]+$/i.test(value) | /^\w+[\w\s]+\w+$/i.test(value);
        },
        message: '请输入姓名'
    },
    date: {// 验证姓名，可以是中文或英文 
        validator: function (value) {
            //格式yyyy-MM-dd或yyyy-M-d
            return /^(?:(?!0000)[0-9]{4}([-]?)(?:(?:0?[1-9]|1[0-2])\1(?:0?[1-9]|1[0-9]|2[0-8])|(?:0?[13-9]|1[0-2])\1(?:29|30)|(?:0?[13578]|1[02])\1(?:31))|(?:[0-9]{2}(?:0[48]|[2468][048]|[13579][26])|(?:0[48]|[2468][048]|[13579][26])00)([-]?)0?2\2(?:29))$/i.test(value);
        },
        message: '清输入合适的日期格式'
    },
    msn: {
        validator: function (value) {
            return /^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/.test(value);
        },
        message: '请输入有效的msn账号(例：abc@hotnail(msn/live).com)'
    },
    same: {
        validator: function (value, param) {
            if ($("#" + param).val() != "" && value != "") {
                return $("#" + param).val() == value;
            } else {
                return true;
            }
        },
        message: '两次输入的密码不一致！'
    },
    regExpress: {
        validator: function (value, param) {
            return param.test(value);
        },
        message: '数据格式错误！'
    }
});

$.extend($.fn.validatebox.methods, {  
    remove: function(jq){  
        return jq.each(function(){  
            $(this).removeClass("validatebox-text validatebox-invalid").unbind('focus').unbind('blur');
        });  
    },
    reduce: function(jq, newposition){  
        return jq.each(function(){  
           var opt = $(this).validatebox.options;
           $(this).addClass("validatebox-text").validatebox(opt);
        });  
    }   
});

//第三方控件
function parseExternalComponents(target) {
    parseCheckBoxRadio(target, false);
    if (!target) {
        target = $("body");
    }
    $(target).find(".chosen-select").each(function(){
        var datavalue = $(this).attr("data-value");
        if (datavalue){
            $(this).find("option[value='"+ datavalue +"']").prop("selected", true);
        }
    });
    if ($(target).find(".chosen-select").length > 0) {
        $(target).find(".chosen-select").extChosen();
    }
    parseInputFile(target);
}

function parseCheckBoxRadio(target, isGrid) {
    if (!target) {
        target = $("body");
    }
    $(target).find("input[type=checkbox],input[type=radio]").each(function (index) {
        if (!$(this).hasClass("css-checkbox")) {
            var hasGridParent = $(this).parents(".datagrid-view").length > 0;
            if ((isGrid && hasGridParent) || (!isGrid && !hasGridParent)) {
                var id = $(this).attr("id");
                var name = $(this).attr("name");
                if (id === undefined) {
                    var rand = Math.ceil(Math.random() * 10000);
                    id = name + "_" + rand;
                    $(this).attr("id", id);
                }
                $(this).addClass("css-checkbox");
                var lableClass = isGrid ? "grid-label" : "css-label";
                var label = ' <label for="' + id + '" class="' + lableClass + '"></label>';
                $(this).after(label);
                var ignore_uncheck = $(this).attr("data-ignore-uncheck"); //忽略未选择，未选中的话不传值到后台，一般用在作为选择标示
                //如果没有隐藏字段，加入隐藏字段,否则后台获取不到未选的值
                if ($(target).find("input[name='" + name + "']").length == 1 && ignore_uncheck == undefined) {
                    var hidden = '<input type="hidden" name="' + name + '" value="0" />';
                    $(this).before(hidden);
                }
                //根据data-value值设置check属性
                var dataValue = $(this).attr("data-value");
                var is_default = $(this).attr("default");
                if (dataValue == $(this).val() || (is_default == "true" && $.trim(dataValue).length == 0)) {
                    $(this).prop("checked", true);
                }
            }
        }
    });
}

/*文件上传控件*/
function parseInputFile(target) {
    if (!target) {
        target = $("body");
    }
    $(target).find("input[class='easyui-file']").each(function () {
        var $this = $(this);
        var id = "file-path-" + Math.ceil(Math.random() * 10000);
        //$this.css("width", "90%");
        var width = $this.parents().width() - 60 + "px"; //扣除按钮长度
        var accept = $this.attr("accept");
        var options = $this.attr("data-options");
        var title = $this.attr("title");
        if (title == undefined){title = "文件名："}
        if(!$this.next("div").find(".easy-file-caption").html())
        $this.after(
            '<div id="' + id + '" style="display: flex; flex-direction: row">' +
            '<span class="easy-file-caption">'+ title +'</i></span>'+
            '<input type="text"  class="easyui-validatebox" value="' + this.defaultValue + '" data-options="' + options + '" style="flex:1;width: ' + width + '" />' +
            '<span class="easyui-file-caption"></span>'+
            '</div> ');
        var $wrap = $("#" + id);
        $this.css("left", $wrap.find(".easyui-file-caption").css("left"));
        $this.css("top", $wrap.find(".easyui-file-caption").css("top"));
        $.parser.parse("#" + id);

        $this.change(function () {
            var filePath = $this.val();
            var fileExt = filePath.substring(filePath.lastIndexOf('.') + 1, filePath.length)
            if (accept && accept.lastIndexOf(fileExt) >= 0) {
                $("#" + id).find("input").val($this.val());
            } else {
                $("#" + id).find("input").val("");
                if (accept) {
                    $.dialog.alert("文件类型错误！后缀必须为" + accept);
                } else {
                    $.dialog.alert("文件必须设置accept属性！");
                }
            }
        });
    });
}

function closeAllTabs(tabs, title) {
    $("li", tabs).each(function (index, obj) {
        //获取所有可关闭的选项卡
        var tab = $(".tabs-closable", this).text();
        if (title !== tab) {
            $(".easyui-tabs").tabs('close', tab);
        }
    });
    $("#close").remove();//同时把此按钮关闭
}

$(function () {
    $.extend($.fn.tabs.methods, {
        /**
         * 绑定双击事件
         * @param {Object} jq
         * @param {Object} caller 绑定的事件处理程序
         */
        bindDblclick: function (jq, caller) {
            return jq.each(function () {
                var that = this;
                $(this).children("div.tabs-header").find("ul.tabs").undelegate('li', 'dblclick.tabs').delegate('li', 'dblclick.tabs', function (e) {
                    if (caller && typeof (caller) == 'function') {
                        var title = $(this).text();
                        var index = $(that).tabs('getTabIndex', $(that).tabs('getTab', title));
                        caller(index, title);
                    }
                });
            });
        },
        /**
         * 解除绑定双击事件
         * @param {Object} jq
         */
        unbindDblclick: function (jq) {
            return jq.each(function () {
                $(this).children("div.tabs-header").find("ul.tabs").undelegate('li', 'dblclick.tabs');
            });
        }
    });

    /*$(target).inputEditor({rowIndex: rowIndex}) 创建inputEdiotr
     * $(target).inputEditor('field', 'value') 设置Editor值
     * $(target).inputEditor('field')  获取Editor
     * */
    $.fn.inputEditor = function (options, param) {
        function setEditor(target, field, param) {
            var editor = getEditor(target, field, param);
            if (editor) {
                switch (editor.type) {
                    case 'numberbox':
                        $(editor.target).numberbox('setValue', param);
                        break;
                    case 'textbox':
                        $(editor.target).textbox('setValue', param);
                        break;
                    case 'datetimebox':
                        $(editor.target).datetimebox('setValue', param);
                        break;                    
                    case 'datebox':
                        $(editor.target).datebox('setValue', param);
                        break;
                    case 'chosen':
                        $(editor.target).val(param);
                        $(editor.target).trigger("chosen:updated");
                        break;
                    default:
                        break;
                }
                $(editor.target).val(param);
            }
        }
        function getEditor(target, field) {
            var data = $.data(target, 'input_editor');
            var editor = $(target).datagrid('getEditor', {
                index: data.rowIndex,
                field: field
            });
            return editor;
        }
        function getEditorTarget(target, field) {
            var editor = getEditor(target, field, param);
            if (editor) {
                return $(editor.target);
            }
            return undefined;
        }

        var $_this = $(this).get(0);
        if (typeof options === 'string') {
            if (param === undefined) {
                return getEditorTarget($_this, options);
            } else {
                setEditor($_this, options, param)
            }
        } else {
            $.data($_this, 'input_editor', $.extend({}, options));
        }
        return this;
    };

    parseExternalComponents();
});

//*自动完成
function autocomplete(target, datalist, selected_callback, options){
    if ($.isArray(datalist)){
        var width = $(target).width();
        if (options == undefined || options == null){
            options = {name: "name", search: ["querykey", "name"]};
        }
        $(target).autocomplete(datalist, {
            width: width,
            matchContains: true,
            scroll: true,
            scrollHeight: 100,
            formatItem: function (row, i, max) {
                return row[options.name];
            },
            formatResult: function (row, i, max) {
                return row[options.name];
            },
            formatMatch: function (row, i, max) {
                var match_str = "";
                $(options.search).each(function(){
                    match_str = match_str + row[this];
                });
                return match_str;
            }
        }).result(function(handler, item){
            if (selected_callback != undefined){
                selected_callback(item);
            }
        });
    }
}
