(function($) {
    $.fn.multiUploader = function (options, param) {
        if (typeof options === 'string') {
            var method = $.fn.multiUploader.methods[options];
            if (method) {
                return method(this, param);
            };
        }
        options = options || {};
        return this.each(function () {
            var state = $.data(this, 'multiUploader');
            if (state) {
                $.extend(state.options, options);
            } else {
                $.data(this, 'multiUploader', $.extend({}, $.fn.multiUploader.defaults, options));
                initial(this);
            }
        });
    };

    function initial(target){
        var state = $.data(target, "multiUploader");
    }

    function multiFileSelectHandler(obj) {
        for(var i=0; i<$(obj).get(0).files.length; i++){
            var oFile = $(obj).get(0).files[i];
            var rFilter = /^(image\/jpeg|image\/png)$/i;
            if (!rFilter.test(oFile.type)) {
                layer.msg("文件必须是图片");
                return;
            }
            // check for file size
            if (oFile.size > 10 * 1024 * 1024) {
                layer.msg("文件超过规定大小");
                return;
            }

            var oReader = new FileReader();
            oReader.onload = function (e) {
                createImageElement(e.currentTarget.result, e.total);
            };
            oReader.readAsDataURL(oFile);
        };
        //selectPicture($(".img-wrap").first());
    }

    function createImageElement(res, fileSize) { //res代表上传的图片，fileSize大小图片的大小
        var img = new Image(), maxW = 1024; //设置最大宽度
        img.onload = function () {
            var cvs = document.createElement('canvas'), ctx = cvs.getContext('2d');
            if (img.width > maxW) {
                img.height *= maxW / img.width;
                img.width = maxW;
            }
            cvs.width = img.width;
            cvs.height = img.height;
            ctx.clearRect(0, 0, cvs.width, cvs.height);
            ctx.drawImage(img, 0, 0, img.width, img.height);
            var compressRate = 1;
            if (fileSize > 0){
                compressRate = 5000*1024 / fileSize; //getCompressRate(1, fileSize);最大50K
            }
            var base64data = cvs.toDataURL('image/jpeg', compressRate);
            var $item_remove = $("<a class=\"btn-item-remove\"><i class=\"fa fa-trash-o fa-2x\"></i></a>");
            var $wrap = $("<div class='kui-img-item img-wrap'></div>");
            $wrap.append("<span class=\"icon-uploaded\"><i class=\"\"></i></span>")
            $(".preview-image").append($item_remove); //添加删除控件
            var $nav_left = $("<a class=\"btn-nav btn-nav-left\"><i class=\"fa fa-arrow-left fa-2x\"></i></a>");//添加向前控件
            $(".preview-image").append($($nav_left));
            $nav_left.click(function(evt){
                thumb_upload_prev();
                evt.stopPropagation();
            });
            var $nav_right = $("<a class=\"btn-nav btn-nav-right\"><i class=\"fa fa-arrow-right fa-2x\"></i></a>");//添加向右控件
            $(".preview-image").append($($nav_right));
            $nav_right.click(function(evt){
                thumb_upload_next();
                evt.stopPropagation();
            });
            $wrap.append($(img));
            $(".kui-img-list").prepend($wrap);
            $item_remove.click(function () {
                $.messager.confirm("是否删除此图片？","删除提醒",function(e){
                    if (e){
                        $(".preview-image").hide();
                        var id = $(".preview-image").attr("thumb_id");
                        $("#" + id).parent(".kui-img-item").remove();
                        $("#file-" + id).remove(); //移除input
                        $(".kui-img-list").trigger("img_remove");
                    }
                });
            });
            $wrap.on("click", function(){
                selectPicture($wrap);
            });
        };
        img.setAttribute("id", "thumb-" + new Date().getTime() + Math.ceil(Math.random()*1000));
        img.src = res;
    }
    function selectPicture($wrap){
        var img_target = $wrap.find("img");
        $(".kui-img-item img").removeClass("active");
        $(img_target).addClass("active");
        var preview_img = $(".preview-image img");
        preview_img.attr("src", img_target.attr("src"));
        $(".preview-image").attr("thumb_id", img_target.attr("id")).show();
        $(".preview-image").data("target", $wrap);
        $(".preview-image").show();
    }
    function create_thumb(thumbs){
        if (thumbs !== null && thumbs !== undefined){
            var objects = thumbs;
            if (typeof(thumbs) == "string"){
                objects = $.parseJSON(thumbs);
            }
            //$(".kui-img-list .img-wrap").remove();
            $(objects).each(function(){
                var img = new Image();
                img.src = this.picturename;
                createImageElement(img.src, 0);
            });
        }
    }

    function thumb_upload_prev(){
        var current = $(".preview-image").data("target");
        if (current.length > 0) {
            var ps = $(current).prev();
            if (ps.length > 0) {
                selectPicture(ps);
            } else {
                $.dialog.tips("已经到第一张了");
            }
        }
    }

    function thumb_upload_next(){
        var current = $(".preview-image").data("target");
        if (current.length > 0) {
            var ns = $(current).next();
            if (ns.hasClass("kui-img-item")) {
                selectPicture(ns);
            } else {
                $.dialog.tips("已经到最后一张了");
            }
        }
    }

    function uploadBillImage(url, params){
        var err_class = "fa fa-close fa-2x bg_red";
        var ok_class = "fa fa-check-square-o fa-2x";
        var total = $(".kui-img-list .img-wrap").length;
        var has_uploaded = $(".kui-img-list .file-upload").length;
        var count = total - has_uploaded;
        if (count == 0){
            $.dialog.tips("没有需要上传的文件");
            return false;
        }
        showMaskLayer();
        $(".kui-img-list .img-wrap").each(function(){
            var _this = this;
            if (!$(_this).hasClass("file-upload")){ //有成功的标志就跳过
                var imgData = $(this).find("img").attr("src");
                if (imgData) {
                    var $upload_flag_target = $(this).find(".icon-uploaded");
                    var data = {image_file: imgData};
                    $.extend(data, params||{});
                    $.post(url, data, function(result){
                        if (result.code == 0){
                            $upload_flag_target.removeClass(err_class).addClass(ok_class);
                            $(_this).addClass("file-upload");
                            count--;
                            if (count == 0){
                                hideMaskLayer();
                            }
                        }else{
                            $upload_flag_target.removeClass(ok_class).addClass(err_class);
                            hideMaskLayer();
                            return false;
                        }
                    },"json").error(function(){
                        hideMaskLayer();
                    });
                }
            }
        });
    }

    $.fn.multiUploader.defaults = $.extend({
        width: "100%",
        height:"auto",
        content:"",
        direction:"bottom"
    });
    $.fn.multiUploader.methods = {
        reset: function (jq) {
            return jq.each(function () {
                $(this).remove(".content-wrap");
                $(this).removeData("multiUploader");
            });
        }
    };

})(jQuery);   