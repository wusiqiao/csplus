(function($) {       
    $.fn.miniTip = function (options, param) {
        if (typeof options === 'string') {
            var method = $.fn.miniTip.methods[options];
            if (method) {
                return method(this, param);
            };
        }
        options = options || {};
        return this.each(function () {
            var state = $.data(this, 'miniTip');
            if (state) {
                $.extend(state.options, options);
            } else {
                $.data(this, 'miniTip', $.extend({}, $.fn.miniTip.defaults, options));
                initial(this);
            }
        });
    };
    
    function initial(target){
        var state = $.data(target, "miniTip");
        var $contentWrap = $("<div class='content-wrap'></div>").appendTo($(target)).hide();
        var $wrapArrow = $("<div class='arrow-up'></div>");
        $wrapArrow.appendTo($contentWrap).hide();
        $(state.content).appendTo($contentWrap);
        $contentWrap.css({
            border: "solid 1px #D3D3D3", 
            borderRadius:"5px", 
            width:state.width, 
            height:state.height,
            zIndex: 9999,
            position: "fixed",
            padding:"5px",
            background: "#D3D3D3"
        });
        $wrapArrow.css({
            position: "fixed"
        });
        $(target).css("cursor","pointer");
        $(target).mouseover(function(){
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
                   arrowTop = $(target).offset().top + $(target).outerHeight(true);
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
        $(target).mouseleave(function(){
           $contentWrap.fadeOut();  
        });
        $(target).click(function(){
            $contentWrap.fadeOut();
        });
        $contentWrap.click(function(){
            $(this).fadeOut();
        });
    }
    
    $.fn.miniTip.defaults = $.extend({
        width: "100%",
        height:"auto",
        content:"",
        direction:"bottom"
    });
    $.fn.miniTip.methods = {
        reset: function (jq) {
            return jq.each(function () {
              $(this).remove(".content-wrap");
              $(this).removeData("miniTip");
            });
        }
    };
  
})(jQuery);   