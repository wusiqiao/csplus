/**
 *
 * HTML5 Image uploader with Jcrop
 *
 * Licensed under the MIT license.
 * http://www.opensource.org/licenses/mit-license.php
 * 
 * Copyright 2012, Script Tutorials
 * http://www.script-tutorials.com/
 */
//最终实现思路：
//1、设置压缩后的最大宽度 or 高度；
//        2
//、设置压缩比例，根据图片的不同size大小，设置不同的压缩比。
function image_click_preview(obj) {
    var _this = obj;
    var srcList = [];
    $(".kui-img-item img").each(function () {
        srcList.push(this.src);
    });
    WeixinJSBridge.invoke('imagePreview', {
        'current': _this.src,
        'urls': srcList
    });
}
;

function compress(res, fileSize) { //res代表上传的图片，fileSize大小图片的大小
    var img = new Image(), maxW = 640; //设置最大宽度
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
        var compressRate = getCompressRate(1, fileSize);
        var base64data = cvs.toDataURL('image/jpeg', compressRate);
        var $item_remove = $("<a class=\"icon-minus btn-item-remove\"><span class=\"mui-icon mui-icon-close-filled\"></span></a>");
        var $wrap = $("<div class='kui-img-item'></div>");
        //var $input_file = $('<input type="file" name="image_file[]" accept="image/*"  onchange="fileSelectHandler(this)" />');
        var $input_file = $('<input type="hidden" name="image_files[]" />');
        $input_file.val(base64data);
        $wrap.append($item_remove);
        $wrap.append($(img));
        $(".kui-img-list").prepend($wrap).append($input_file);
        //var left = $(".kui-img-list").find(".kui-img-item:last").offset().left;
        //$input_file.css("left", left);
        $item_remove.click(function () {
            $wrap.remove();
        });
        $(img).click(function(){image_click_preview(this)});
        var timestamp = 0;
        $(img).on({
            touchstart: function (e) {
                timestamp = new Date().getTime();
                $item_remove.hide();
                e.preventDefault();
            },
            touchmove: function () {
                timestamp = 0;
            },
            touchend: function () {
                var timeDiff = new Date().getTime() - timestamp;
                if (timeDiff > 500) { //长按,大于2秒   
                    $item_remove.show();
                } else {

                }
                return false;
            }
        });
    };
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
}
;

function fileSelectHandler(obj) {
    var oFile = $(obj).get(0).files[0];
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
        compress(oReader.result, oFile.size);
    };
    oReader.readAsDataURL(oFile);
}