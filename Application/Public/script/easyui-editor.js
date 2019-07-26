$.extend($.fn.datagrid.defaults.editors, {
    chosen: {
        init: function (container, options)
        {
            var $select = $('<select class="chosen-select" style="width:100%;"><option value=0>--选择--<option/></select>');
            $select.appendTo(container);
            var parent = container.parents(".datagrid-view").get(0); //要传入对象，不是数组，切记！！！
            $select.extChosen($.extend(options, {parent: parent, asyncLoad:false}));
            $select.parents(".datagrid-cell").css("overflow", "visible");
            var $chosen_container = $select.next(".chosen-container");
            $chosen_container.find("a.chosen-single").css("border-radius", 0);
            var left = container.offset().left;
            var top = container.offset().top;
            $chosen_container.css({position:"fixed",left:left, top:top});
            return $select;
        },
        getValue: function (target)
        {
            return $(target).find("option:selected").text();
        },
        setValue: function (target, value)
        {
            //$(target).find("option[text='xxx']")这种方式一直无法获得，未解，用下面解决方法
            $(target).find("option").filter(function(index) {
                return value === $(this).text();
            }).attr("selected", "selected");
            $(target).trigger("chosen:updated");
        },
        destroy: function (target) {
            $(target).chosen('destroy');
            $(target).remove();
        },
        resize: function (target, width) {
            $(target).width(width);
        }
    },
    numberspinner: {
        init: function (container, options) {
            var input = $('<input type="text">').appendTo(container);
            return input.numberspinner(options);
        },
        destroy: function (target) {
            $(target).numberspinner('destroy');
        },
        getValue: function (target) {
            return $(target).numberspinner('getValue');
        },
        setValue: function (target, value) {
            $(target).numberspinner('setValue', value);
        },
        resize: function (target, width) {
            $(target).numberspinner('resize', width);
        }
    },
    datebox : {
        init : function(container, options) {
          var input = $('<input type="text">').appendTo(container);
          input.datebox(options);
          return input;
        },
        destroy : function(target) {
          $(target).datebox('destroy');
        },
        getValue : function(target) {
          return $(target).datebox('getValue');//获得旧值
        },
        setValue : function(target, value) {
          $(target).datebox('setValue', formatDate(value));//设置新值的日期格式
        },
        resize : function(target, width) {
          $(target).datebox('resize', width);
        }
  },
  thumb: {
        init: function (container, options) {
            var thumb = formatThumbImage();
            return $(thumb).appendTo(container);
        },
        getValue: function (target) {
            return $(target).attr('value');
        },
        setValue: function (target, value) {
            $(target).attr('value', value);
        },
        destroy : function(target) {
          $(target).remove();
        }
    }

});
