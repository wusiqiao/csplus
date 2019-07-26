(function ($) {
    $.fn.base64_uploader = function (options, param) {
        if (typeof options === 'string') {
            var method = $.fn.base64_uploader.methods[options];
            if (method) {
                return method(this, param);
            }
        }
        options = options || {};
        return this.each(function () {
            var state = $.data(this, 'base64_uploader');
            if (state) {
                $.extend(state.options, options);
            } else {
                $.data(this, 'base64_uploader', $.extend({}, $.fn.base64_uploader.defaults, options));
                initial(this);
            }
        });
    };
    
    $.fn.base64_uploader.defaults = $.extend({
        max_width: 640
    });
    
    //    $.fn.base64_uploader.methods = {
//        reset: function (jq) {
//            return jq.each(function () {
//              $(this).remove(".content-wrap");
//              $(this).removeData("base64_uploader");
//            });
//        }
//    };

    function initial(target) {
        $(target).append('<div class="kui-img-item capture">'+
                '<input type="file" name="image_file[]" accept="image/*"  /> '+
                '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAMAAAD04JH5AAABNVBMVEUAAAD5wk38wEr6wEz6wkr8wkz/w0v+u1D8wkz8wEz5wEn7wktEREBERED5wEz8wktERED5wEz8wUtEREBERED2vkr6wEz8wEr7v0r3vEr2vEr8wUz8wkr//wBERECoqB76wkpEREBERED8wEpEREBEREBEREBEREBEREBERED7v0r2vEr1u0rbVVT7Wlr9Wlr7WFj4Wln8WFj8Wlr8wUz6wkz8wEv8wkz7wUv8uUT4WFfjVVXgVVTkV1fhV1axUE//X1/tV1f/WVnpXFumUlD+W1v9WVn7WVnwWFfuV1f7WFj8WlqPTkttbU77wUv8wkz7wkr+xkb6wUv8wkz+W1v8W1v4W1rnWFjJU1L6wkz8wkr5w038wkz7wUz6wUz6wEz5wEz7wkz4v0z9Wlr7WlrzWVnyWVjOoRewAAAAXHRSTlMALtH+/LURE7jQLZQBApu9A8NwBAV57by8vb63bQEGAu8HCMAJCgsMDg/AwsJPubhCYVxgu/tzsZEPXWVmaWohEBYUEh0OvL3AwNXUGgl6wpcSd7QqcHF4NfDNK9pOjDwAAAPpSURBVHja7Zuxb9tGFIe/Rz9DskIDzqDIktGhDdogyJQW6JQ1c4YMcfoPJkONbBnapUuAbO1qFF0itHacwDBaQbFli6/D0bYokqosSrwUOI0/3t3vibw7fniPpyLynPl/L0VGMy435GL3BqOxJyo/ZCUBsFJtl1cugFjABtl2sciTGX0LtKd7KtlrkYAlszQFYAvgJNtuC0SSm4030uz9V4Axs7Q1IG4I2Fn27zcEpv3/c7znCvBTehtEBMwyN2xKU7l1TNxswfAs8wCc1nq9cVHad1rbeHQZjrx3Y7QF7EN23FLttEj7OF9fp929fqLu2p0Ikqn2K9euA9jczsdbg3YVwGZXwA7/ybSvQdOraxHYQbZ9T1auXQYQbwtif2Xb7wisWEM0nRsC9mdB+9VqROaWYTsqiXe1/utmaQAFz6sG/4aYmwNaMF9r8I8lnYQRkHyo3X+zLWCm7v5P7Rd1+G8bkLhl6MO/i8FFug9Y/f49DDM3B2xstfvvYCR27gLIMkQ9/mLYUcetAvPhD3Y46EzxQL3+l/ueevK/2vfVj/+1pp79JwPw4j/JhF78J5iw54dJNMOLHphoggm9+AcmDEwYmDAw4f+dCVW+AvnNHxPqt8CvHpnwm8CEgQmXwIQvd9Mo6ve/DeyrpOlzD0wYAZHKK1275YcJD9i/H+loBMeVmLC5aEz9Pm/j6kzo8u8LMomO1eXQF2fCSv6NMQrEzcWZsJJ/M90HGq2FmbCSf3s8xBS2ZGEmTKQKEwIMlLgCk8UCZosyYYKdoAgMTyswmZ0uzITDMzcH7KyK/2I818Nc3UsBD/47WFq7UuyE+v3FsKOBex0PKvgPKvD04YDAhPMz4Z2vZ9STHz4srhP/8cvymDCaVc8u1ZbIhL1F/NdWlCec13/O99R8ecK1frGXdoHD82L/5hLzhD+XjPFdFzj43XOesFnFPzBhYMLAhJ8BEzZEpPXRCxM+EBFReSLJaz9MuN6CT3pBwoYfJnzzGN7prkvZ+mDCDnAvMGHIE4bacagd+2ZC8cuElWvHX/6NyBfH/mrHP4bacagdh9px+J4wfE8YmDAwYWDCz4IJw/eE4XvCwISBCZfOhD0styaXrkk5E3ax3JpciVbChNtYbk2uRitkwrhtRnI0qE9TgI27V3uTgFmnk9mvJrXb0UEf4jZJ/jwtCQ/W35T3nda+vwrg0eXe7M5kn2f265zWJ26Oczl+p0nr8cy+eS3zCMzIJI4LtX3iBsPp7z5STbjheC907+nEu7HgnHhOu/9Wx+TOnaeazO6b156p7I3S4+dilj8nP63tR1E8Ljh3n2ry6d290r557cUz+RfKlYcWPvj4OwAAAABJRU5ErkJggg==" />'+
                '</div>');
        $(target).find("input[type='file']").change(function(){
            fileSelectHandler(target, this)
        });
        var $preview_pannel = $('<div class="preview-image">'+
                '<a class="icon-minus btn-item-remove"><span class="mui-icon mui-icon-close-filled"></span></a>'+
                '<img src="" />'+
                '</div>');
        $("body").append($preview_pannel);
        
        $preview_pannel.find(".btn-item-remove").click(function () {
                if (confirm("是否删除此图片？", "删除提醒")) {
                    $(".preview-image").hide();
                    var id = $(".preview-image").attr("thumb_id");
                    $("#" + id).parent(".kui-img-item").remove();
                    $("#file-" + id).remove();
                };
         });
        
    }

    function fileSelectHandler(target, obj) {
        var oFile = $(obj).get(0).files[0];
        var rFilter = /^(image\/jpeg|image\/png)$/i;
        if (!rFilter.test(oFile.type)) {
            alert("文件必须是图片");
            return;
        }
        // check for file size
        if (oFile.size > 10 * 1024 * 1024) {
            alert("文件超过规定大小");
            return;
        }

        //var state = $.data(target, 'base64_uploader');
        var oReader = new FileReader();
        oReader.onload = function (e) {
            compress(target, oReader.result, oFile.size);
        };
        oReader.readAsDataURL(oFile);
    }

    function compress(target, res, fileSize) { //res代表上传的图片，fileSize大小图片的大小
        var state = $.data(target, 'base64_uploader');
        var img = new Image(), maxW = state.max_width; //设置最大宽度
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
            if (fileSize > 0) {
                compressRate = 50 * 1024 / fileSize; //getCompressRate(1, fileSize);最大50K
            }
            //创建存储base64数据的input
            var $input_file = $('<input type="hidden" name="image_files[]" />');
            var base64data = cvs.toDataURL('image/jpeg', compressRate);
            $input_file.val(base64data).attr("id", "file-" + img.getAttribute("id"));
            //创建图片缩略图
            var $kui_img_item = $("<div class='kui-img-item'></div>");
            $kui_img_item.append($(img));
            $(target).prepend($kui_img_item).append($input_file);
            //点击缩略图预览
            $(img).on("click", function () {
                var preview_img = $(".preview-image img");
                preview_img.attr("src", $("#file-" + this.getAttribute("id")).val());
                $(".preview-image").attr("thumb_id", this.getAttribute("id")).show();
            });
            
            //点击缩预览图，关闭
            $(".preview-image img").on("click", function (event) {
                event.stopPropagation();
                $(".preview-image").hide();
            });
        };
        img.setAttribute("id", "thumb-" + new Date().getTime());
        img.src = res;
    }

    function getCompressRate(allowMaxSize, fileSize) { //计算压缩比率，size单位为MB
        var compressRate = 1;
        if (fileSize / allowMaxSize > 4) {
            compressRate = 0.5;
        } else if (fileSize / allowMaxSize > 3) {
            compressRate = 0.6;
        } else if (fileSize / allowMaxSize > 2) {
            compressRate = 0.7;
        } else if (fileSize > allowMaxSize) {
            compressRate = 0.8;
        } else {
            compressRate = 0.9;
        }
        return compressRate;
    }
// convert bytes into friendly format
    function bytesToSize(bytes) {
        var sizes = ['Bytes', 'KB', 'MB'];
        if (bytes == 0)
            return 'n/a';
        var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
        return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + sizes[i];
    };
    
    function create_thumb(thumbs) {
        $(thumbs).each(function () {
            var img = new Image();
            img.src = this.picturename;
            compress(img.src, 0);
        });
    }

})(jQuery);   