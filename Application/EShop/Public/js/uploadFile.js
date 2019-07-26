
class main{
    constructor(){
        this.orderId = '{$order_id}';
        this.maxCount = 5;
        this.maxfilesize = 1024 * 1024 * 5;//最大文件大小设置为2M
        this.ValidateType = 'img';
        this.UploadFileUrl = '/Uploads/scheduleUploads/t/0.html'
    }
    defaultValidate(files){
        if (files.size > this.maxfilesize) {
            layer.msg("文件大小最多只能5M,请重新上传");
            return false;
        }
        var count = $(this.FileListsDom).find("li > div > input[name*="+this.InputName+"]").length;
        if(count >= this.maxCount){
            layer.msg("最多只能上传"+this.maxCount+'个文件');
            return false;
        }

        return true;
    }
    //文件显示格式处理工具
    fileShowProcessingTool(files){
        var imgExts = ['jpg','gif','png','jpeg','JPGE'];
        var excelExts = ['xlsx','xlsm','xltx','xltm','xls','xlsb','xlsm'];
        var wordExts = ['doc','docx','docm','dotx','dotm'];
        var textExts = ['text'];
        var pdfExtes = ['pdf'];
        var fileName = files.name;
        var ext = fileName.split('.')[1].toLowerCase();
        var result = [];
        if($.inArray(ext,imgExts) != -1){
            result = {'type':'image','typename':'图片'};
        }
        if($.inArray(ext,wordExts) != -1){
            result = {'type':'word','typename':'文档'};
        }
        if($.inArray(ext,excelExts) != -1){
            result = {'type':'excel','typename':'表格'};
        }
        if($.inArray(ext,textExts) != -1){
            result = {'type':'text','typename':'文本'};
        }
        if($.inArray(ext,pdfExtes) != -1){
            result = {'type':'pdf','typename':'pdf'};
        }
        if(result.length == 0){
            result = {'type':'other','typename':'其他'};
        }
        var url = this.FileShowUrl+'ask-'+result.type+'.png';
        result.url = url;
        return result;
    }
}
//上传类
class Uploads extends main{
    setButtonId(value){
        this.buttonId = value;
        this.buttonDom = '#'+value;

    }
    setFileDom(value){
        this.FileDom = value;
    }
    setFileId(value){
        this.FileId = value;
        this.FileDom = '#'+value;
    }
    setMaxCount(value){
        this.maxCount = value;
    }
    setAfterFunction(func){
        this.func = func;
    }
    setInputName(value){
        this.InputName = value;
    }
    setValidateType(value){
        this.ValidateType = value;
    }
    setFileListsId(value){
        this.FileListsId = value;
        this.FileListsDom = '#'+value;
    }
    setUploadFileUrl(value){
        this.UploadFileUrl = value;
    }
    setFileShowUrl(value){
        this.FileShowUrl = value;
    }
    bindButtonClick(){
        var obj = this;
        $(this.buttonDom).on('click',function(){
            $(obj.FileDom).click();
        })
    }
    bindFileChanre(){
        var obj = this;
        $(this.FileDom).change(function(){
            var file = this.files[0];
            if(obj.defaultValidate(file) && obj.handlerValidateType(file,obj.ValidateType)){
                obj.handlerFilesloading(file);
            }else{
                return false;
            }
        })
    }
    handlerValidateType(file,type){
        switch (type){
            case 'img':
                return this.validateImg(file);
                break;
            case 'enc':
                return this.validateEnc(file);
                break;
            default :
                return false;
                break;
        }
    }
    handlerFilesloading(file){
        var reader = new FileReader();
        var obj = this;
        if (file.type != 'image/jpeg' && file.type != 'image/jpg' && file.type != 'image/gif' && file.type != 'image/png'){
            var index = layer.load(1, {
            });
            var fileDom = obj.FileListsDom;
            $.ajaxFileUpload({
                url: obj.UploadFileUrl,
                secureuri: false,
                fileElementId: this.FileId, //上传控件ID
                dataType: 'json',
                success: function (data) {
                    if (data.code == 1) {
                        layer.msg(data.msg);
                        setTimeout(function () {
                            layer.closeAll();
                        }, 2000);
                    } else {
                        parent.layer.close(index);
                        var fileShow = obj.fileShowProcessingTool(file);
                        $(fileDom).html($(fileDom).html() + "<li class='dib' style='padding: .15rem;'>  <div class='am-gallery-item'><input name="+obj.InputName+"[]' value='" + data.record.file_url + "' type='hidden' ><div style='position: relative;'><span class='del-img' onclick='del_img(this);'></span><a href='" + data.record.file_url + "' target='_blank'> <img src='" + fileShow.url + "' width='50' height='50' /></a></div></div></li>");

                    }
                    obj.afterFunction();
                }
            });
        }else{
            var index = layer.load(1, {
                //time:500
            });
            reader.readAsDataURL(file);
            reader.onload = function (e) {
                var base64 = this.result;
                var fileDom = obj.FileListsDom;
                var fileShow = obj.fileShowProcessingTool(file);
                var fileShowValue = '';
                if(fileShow.type == 'image'){
                    fileShowValue = base64;
                }else{
                    fileShowValue = fileShow.url;
                }
                $(fileDom).html($(fileDom).html() + "<li class='dib' style='padding: .15rem;'>  <div class='am-gallery-item'><input name="+obj.InputName+"[]' value='" + base64 + "' type='hidden' ><div style='position: relative;'><span class='del-img' onclick='del_img(this);'></span><a href='" + base64 + "' target='_blank'> <img src='" + fileShowValue + "' width='50' height='50' /></a></div></div></li>");
                obj.afterFunction();
                layer.close(index);
            }
        }
    }
    validateImg(files){
        if (files.type != 'image/jpeg' && files.type != 'image/jpg' && files.type != 'image/gif' && files.type != 'image/png') {
            layer.msg("文件类型只能是jpeg/jpg/gif/png类型");
            return false;
        }
        return true;
    }
    validateEnc(files){
        var filenames = files.name.split('.');
        return true;
    }
    afterFunction(){
        if(typeof this.func === 'function'){
            this.func();
        }
    }
}
