/**
 * monthdatespinner - jQuery EasyUI
 * 
 * Licensed under the GPL:
 *   http://www.gnu.org/licenses/gpl.txt
 *
 * Copyright 2010 stworthy [ stworthy@gmail.com ] 
 * 
 * Dependencies:
 *   spinner
 * 
 */
(function ($) {
    function create(target) {
        var opts = $.data(target, 'monthdatespinner').options;
        $(target).spinner(opts);
        var textbox = $(target).monthdatespinner("textbox");
        $(target).unbind('.monthdatespinner');
        textbox.bind('click.monthdatespinner', function () {
            var start = 0;
            if (this.selectionStart != null) {
                start = this.selectionStart;
            } else if (this.createTextRange) {
                var range = target.createTextRange();
                var s = document.selection.createRange();
                s.setEndPoint("StartToStart", range);
                start = s.text.length;
            }
            if (start >= 0 && start <= 2) {
                opts.highlight = 0;
            } else if (start >= 3 && start <= 5) {
                opts.highlight = 1;
            }
            highlight(target);
        }).bind('blur.monthdatespinner', function () {
            fixValue(target, $(target).spinner("getValue"));
        });
    }

    /**
     * highlight the hours or minutes or seconds.
     */
    function highlight(target) {
        var opts = $.data(target, 'monthdatespinner').options;
        var textbox = $(target).monthdatespinner("textbox").get(0);
        var start = 0, end = 0;
        if (opts.highlight == 0) {
            start = 0;
            end = 2;
        } else {
            start = 3;
            end = 5;
        }
        if (textbox.selectionStart != null) {
            textbox.setSelectionRange(start, end);
        } else if (textbox.createTextRange) {
            var range = textbox.createTextRange();
            range.collapse();
            range.moveEnd('character', end);
            range.moveStart('character', start);
            range.select();
        }
        textbox.focus();
    }

    /**
     * parse the time and return it or return null if the format is invalid
     */
    function parseMonthDate(target, value) {
        var opts = $.data(target, 'monthdatespinner').options;
        if (value){
            var testVal = value.replace(opts.separator, '-');
            if (!(/\d{1,2}-\d{1,2}/i.test(testVal)))
                return null;
            var vv = value.split(opts.separator);
            if (vv.length === 2 && !isNaN(vv[0]) && !isNaN(vv[1])){
                vv[0] = parseInt(vv[0]);
                if (vv[0] > 12){
                    vv[0] = 1;
                }
                vv[1] = parseInt(vv[1]);
                if (vv[1] > 31){
                    vv[1] = 1;
                }
                return vv;
            }else{
                return null;
            }
        }
        return null;
    }

    function fixValue(target, value) {
        var opts = $.data(target, 'monthdatespinner').options;
        var monthDate = parseMonthDate(target, value);
        if (!monthDate) {
            opts.value = '01' + opts.separator + '01';
            $(target).spinner("setValue", opts.value);
        }else{
            var tt = [formatNumber(monthDate[0]), formatNumber(monthDate[1])];
            var val = tt.join(opts.separator);
            opts.value = val;
            $(target).spinner("setValue", val);
        }
        function formatNumber(value) {
            return (value < 10 ? '0' : '') + value;
        }
    }

    function doSpin(target, down) {
        var opts = $.data(target, 'monthdatespinner').options;
        var val = $(target).val();
        if (val == '') {
            val = [1, 1].join(opts.separator);
        }
        var vv = val.split(opts.separator);
        for (var i = 0; i < vv.length; i++) {
            vv[i] = parseInt(vv[i], 10);
        }
        if (down == true) {
            vv[opts.highlight] -= opts.increment;
        } else {
            vv[opts.highlight] += opts.increment;
        }
        opts.value = vv.join(opts.separator);
        fixValue(target, opts.value);
        highlight(target);
    }


    $.fn.monthdatespinner = function (options, param) {
        if (typeof options == 'string') {
            var method = $.fn.monthdatespinner.methods[options];
            if (method) {
                return method(this, param);
            } else {
                return this.spinner(options, param);
            }
        }

        options = options || {};
        return this.each(function () {
            var state = $.data(this, 'monthdatespinner');
            if (state) {
                $.extend(state.options, options);
            } else {
                $.data(this, 'monthdatespinner', {
                    options: $.extend({}, $.fn.monthdatespinner.defaults, $.fn.monthdatespinner.parseOptions(this), options)
                });
                create(this);
            }
        });
    };

    $.fn.monthdatespinner.methods = {
        options: function (jq) {
            var opts = $.data(jq[0], 'monthdatespinner').options;
            return $.extend(opts, {
                value: jq.val()
            });
        },
        setValue: function (jq, value) {
            return jq.each(function () {
                $(this).val(value);
                fixValue(this, value);
            });
        }
    };

    $.fn.monthdatespinner.parseOptions = function (target) {
        var t = $(target);
        return $.extend({}, $.fn.spinner.parseOptions(target), {
            separator: t.attr('separator'),
            highlight: (parseInt(t.attr('highlight')) || undefined)
        });
    };

    $.fn.monthdatespinner.defaults = $.extend({}, $.fn.spinner.defaults, {
        separator: '月',
        highlight: 0, // The field to highlight initially, 0 = hours, 1 = minutes, ...
        spin: function (down) {
            doSpin(this, down);
        }
    });
})(jQuery);