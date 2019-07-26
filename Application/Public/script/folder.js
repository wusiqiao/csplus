(function ($) {
    function createFolderPanel(target) {
        var state = $.data(target, 'folder');
        if (!state.options.single) {
            var nav =
                    '<div class="folder-address-nav">' +
                    ' <div class="folder-action folder-add">' +
                    '    <a href="javascript:void(0)"  title="添加目录"><i class="icon-plus-sign icon-2x"></i></a>' +
                    '</div>' +
                    '<div class="folder-action folder-home" style="display: none">' +
                    '    <a href="javascript:void(0)"  title="返回" ><i class="icon-home icon-2x"></i></a>' +
                    '</div>' +
                    '</div>';
            $(nav).appendTo($(target));
            $(target).find(".folder-add").on("click", function () {
                var folder_id = add_folder(target, "", "新文件夹", true);
                state.options.onAdd(folder_id, "新文件夹");
            });
            $(target).find(".folder-home").on("click", function () {
                _toggle(target);
                $(target).find(".folder-address-nav").css("bottom", 0);
                state.options.onHome();
            });
        }
        var contains = '<ul class="ul-folder"></ul>';
        $(contains).appendTo($(target));

//        $(target).find(".easyui-tabs").tabs({onSelect: function () {
//                var pp = $(this).tabs("getSelected");
//                if (pp.hasClass("tab-attachment-content"))
//                {
//                    $(target).find(".folder-add,.ul-folder").show();
//                    $(target).find(".folder-home,.file-list").hide();
//                } else {
//                    $(target).find(".folder-action").hide();
//                }
//            }
//        });
    }

    function add_folder(target, id, name, removed) {
        var folder_id = id;
        if (!folder_id) {
            folder_id = uuid(16, 16);
        }
        var readonly = removed ? "" : "readonly";
        var state = $.data(target, 'folder');
        var $li = $('<li id="' + folder_id + '"><div class="folder"></div><div class="folder-name"><input type="text" ' + readonly + '  value="' + name + '" org-val="' + name + '"/></div></li>');
        var $input = $li.find("input");
        $input.on("blur", function () {
            var _this = $(this);
            var folder_name = _this.val();
            var org_val = _this.attr("org-val");
            if (org_val !== folder_name) {
                if ($.trim(folder_name) === "") {
                    alert("目录名不能为空！");
                    _this.val(org_val);
                    _this.focus();
                    return true;
                }
                if (id) {  //历史记录，非新增
                    $.messager.confirm('修改确认', "确定修改为" + folder_name + "？", function (r) {
                        if (r) {
                            $.post(state.options.update_url, {category: state.options.category, id: id, name: folder_name}, function (result) {
                                _this.attr("org-val", folder_name);
                                state.options.onChange(id, folder_name);
                            });
                        } else {
                            _this.focus();
                        }
                    });  
                } else {
                    state.options.onChange(folder_id, folder_name);
                }
            }
        });
        var $ul = $(target).find(".ul-folder");
        $li.appendTo($ul);
        if (removed) {
            var $a_remove = $('<a href="javascript:;" class="folder-remove icon-remove-sign icon-large"></a>');
            $a_remove.appendTo($li);
            $a_remove.on('click', function () {
                var _this = this;
                if (id) {  //历史记录，非新增                    
                    $.messager.confirm('删除确认', "确定删除此目录及目录下所有文件？", function (r) {
                        if (r) {
                            $.post(state.options.remove_url, {category: state.options.category, id: id}, function (result) {
                                if (result.code === 0) {
                                    $(_this).parent("li").remove();
                                } else {
                                    alert(result.message);
                                }
                            });
                        }
                    }); 
                } else {
                    $(_this).parent("li").remove();
                }
            });
        }
        var $folder = $li.find(".folder");
        $folder.on('click', function () {
            var folder_name = $(this).parent("li").find("input").val();
            _toggle(target);
            state.options.onClick(state.options.category, folder_id, folder_name);
        });
        if (state.options.single) {
            $folder.trigger("click");
        }
        return folder_id;
    }

    function _toggle(target) {
        $(target).find(".ul-folder").toggle();
        $(target).find(".folder-add").toggle();
        $(target).find(".folder-home").toggle();
        $(target).find(".file-list").toggle();
    }

    $.fn.folder = function (options, param) {
        if (typeof options === 'string') {
            var method = $.fn.folder.methods[options];
            if (method) {
                return method(this, param);
            }
            ;
        }
        options = options || {};
        return this.each(function () {
            var state = $.data(this, 'folder');
            if (state) {
                $.extend(state.options, options);
            } else {
                options.category = $(this).attr("id");
                if (!options.category) {
                    options.category = uuid(16, 16);
                }
                $.data(this, 'folder', {options: $.extend({}, $.fn.folder.defaults, options)});
            }

            var _this = this;
            createFolderPanel(this);
            $.getJSON(options.get_url, {category: options.category},
            function (data) {
                if ($.isArray(data) && data.length > 0) {
                    $.each(data, function () {
                        var removed = (this.folder_name === "默认") ? false : true;
                        add_folder(_this, this.folder_id, this.folder_name, removed);
                    });
                } else {
                    add_folder(_this, "", "默认", false);
                }
            });
        })
    };

    $.fn.folder.defaults = $.extend({}, {
        currentFolder: "default",
        data: [],
        single: false,
        onClick: function (category, id, name) {
        },
        onChange: function (id, name) {
        },
        onHome: function () {
        },
        onAdd: function (id, name) {
        }
    });

    $.fn.folder.methods = {
        upload: function (jq, options) {
            return jq.each(function () {
                // upload(this, options);
            });
        },
        load: function (jq, settings) {
            return jq.each(function () {
                // load(this, settings);
            });
        }
    };


})(jQuery);