(function($) {       
    $.fn.monthpicker = function (options, param) {
        if (typeof options === 'string') {
            var method = $.fn.monthpicker.methods[options];
            if (method) {
                return method(this, param);
            };
        }
        options = options || {};
        return this.each(function () {
            var state = $.data(this, 'monthpicker');
            if (state) {
                $.extend(state.options, options);
            } else {
                $.data(this, 'monthpicker', $.extend({}, $.fn.monthpicker.defaults, options));
                initial(this);
            }
        });
    };
    
    function initial(target){
        var state = $.data(target, "monthpicker");
        var $contentWrap = $("<div class='monthpicker-content-wrap'></div>").appendTo($(target)).hide();
        var $wrapArrow = $("<div class='arrow-up'></div>");
        $wrapArrow.appendTo($contentWrap).hide();
        var content = 
                "<div class='monthpicker-top'>"+
                    "<div class='left'>"+
                        "<div class='top button year-inc'><span class='icon-plus icon-large'></span></div>"+
                        "<div class='center year-value'>--</div>"+
                        "<div class='bottom button year-dec'><span class='icon-minus icon-large'></span></div>"+
                    "</div>"+
                    "<div class='right'>"+
                         "<div class='top button month-inc'><span class='icon-plus icon-large'></span></div>"+
                        "<div class='center month-value'>--</div>"+
                        "<div class='bottom button month-dec'><span class='icon-minus icon-large'></span></div>"+
                    "</div>"+
                "</div>"+
                "<div class='monthpicker-bottom'>"+
                    "<div class='left button mp-cancel'>"+
                    "取消"+
                    "</div>"+
                    "<div class='right button mp-ok'>"+
                    "确定"+
                    "</div>"+
                "</div>";
        $(content).appendTo($contentWrap);
        $contentWrap.css({
            border: "solid 1px #D3D3D3", 
            borderRadius:"5px", 
            width:state.width, 
            height:state.height,
            zIndex: 9999,
            position: "fixed",
            padding:"5px",
            background: "#F5F5F5",
        });
        $wrapArrow.css({
            position: "fixed",
            borderBottomColor: "#F5F5F5"
        });
        $(target).click(function(evt){
            evt.stopPropagation();
            var top,left,arrowLeft,arrowTop;
            switch (state.direction)
            {
                case "right":
                    //先设置箭头，这个影响到wrapArrow的outerWidth（设置后，outerWidth=15px（outerWidth获取包括边框+内边界+长度的值））
                    $wrapArrow.removeClass().addClass("arrow-left");  
                    arrowLeft = $(target).offset().left + $(target).width();
                    arrowTop = $(target).offset().top + ($(target).outerHeight(true)-$wrapArrow.outerHeight(true))/2;
                    left = $(target).offset().left + $(target).width() + $wrapArrow.outerWidth();
                    top = $(target).offset().top + ($(target).outerHeight(true)-$contentWrap.outerHeight(true))/2;
                    break;
                case "top":
                   $wrapArrow.removeClass().addClass("arrow-down");
                   arrowLeft = $(target).offset().left + ($(target).outerWidth(true) - $wrapArrow.outerWidth(true))/2;
                   arrowTop = $(target).offset().top - $wrapArrow.outerHeight(true);
                   left = $(target).offset().left + ($(target).width() - $contentWrap.width())/2;
                   top = $(target).offset().top - $contentWrap.outerHeight(true) - 15;
                    break;
                case "left":
                    $wrapArrow.removeClass().addClass("arrow-right");
                    arrowLeft = $(target).offset().left - $wrapArrow.outerWidth(true);
                    arrowTop = $(target).offset().top + ($(target).outerHeight(true)-$wrapArrow.outerHeight(true))/2;
                    left = $(target).offset().left - $contentWrap.outerWidth(true) - $wrapArrow.outerWidth(true);
                    top = $(target).offset().top + ($(target).outerHeight(true)-$contentWrap.outerHeight(true))/2;
                    break;
                default:
                   arrowTop = $(target).offset().top + $(target).outerHeight(true) + 1;
                   arrowLeft = $(target).offset().left + ($(target).outerWidth(true) - $wrapArrow.outerWidth(true))/2;
                   top = $(target).offset().top + $(target).outerHeight(true) + $wrapArrow.outerHeight(true);
                   left = $(target).offset().left + ($(target).width() - $contentWrap.width())/2;
            }            
            $contentWrap.css("top", top);
            $contentWrap.css("left", left);
            $wrapArrow.css("top", arrowTop);
            $wrapArrow.css("left", arrowLeft);            
            $contentWrap.fadeIn(); 
            $wrapArrow.fadeIn();
        });
        var $yearTarget = $(target).find(".year-value");
        var $monthTarget = $(target).find(".month-value");
        var year_month = $(target).find("input").val();
        if (isNaN(new Date(year_month))){
            year_month = new Date().getFullYear()+ "/" + (new Date().getMonth() + 1) +"/01";
        }
        var year = new Date(year_month).getFullYear();
        var month =  new Date(year_month).getMonth() + 1;
        month = (month>9)?month:"0" + month;
        $yearTarget.html(year);
        $monthTarget.html(month);
        $contentWrap.click(function(e){
            $(this).fadeOut();
            e.stopPropagation();
        });
        $(target).find(".year-inc").click(function(e){
            var year = $yearTarget.html();
            if ($.isNumeric(year)){
                year++;
                $yearTarget.html(year);
            }
            e.stopPropagation();            
        });
        $(target).find(".year-dec").click(function(e){
            var year = $yearTarget.html();
            if ($.isNumeric(year)){
                year--;
                $yearTarget.html(year);
            }
            e.stopPropagation();
        });
        $(target).find(".month-inc").click(function(e){
            var month = $monthTarget.html();
            if ($.isNumeric(month)){
                month++;
                if (month === 13){
                  $(target).find(".year-inc").trigger("click"); 
                  month = 1;
                }
                $monthTarget.html((month>9)?month:"0" + month);                
            }
            e.stopPropagation();
        });
        $(target).find(".month-dec").click(function(e){
            var month = $monthTarget.html();
            if ($.isNumeric(month)){
                month--;
                if (month === 0){
                    $(target).find(".year-dec").trigger("click"); 
                    month = 12;
                }
                $monthTarget.html((month>9)?month:"0" + month); 
            }
            e.stopPropagation();
        });
        $(target).find(".mp-ok").click(function(e){
            var year = $yearTarget.html();
            var month = $monthTarget.html();
            $(target).find("input").val(year +"/" + month);
        });
        /*如果没有初始值，设置为当前年月*/
        if ($.trim($(target).find("input").val()) === ""){
            $(target).find("input").val(year +"/" + month);
        }
    }
    
    $.fn.monthpicker.defaults = $.extend({
        width: "auto",
        height:"auto",
        direction:"bottom"
    });
    $.fn.monthpicker.methods = {
        reset: function (jq) {
            return jq.each(function () {
              $(this).remove(".monthpicker-content-wrap");
              $(this).removeData("monthpicker");
            });
        }
    };
  
})(jQuery);   