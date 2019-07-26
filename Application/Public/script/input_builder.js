/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
//0：单行文本，1：多行文本，2：整数，3:小数，4：单选，5：多选，6：日期，7：日期时间，8：时间，9：图片
function build_inputs(input_datas, group_id){
    var result = '<ul class="input-contents"><input type="hidden" name="group_id" value="'+group_id+'">';
    $(input_datas).each(function(){
       var input_type = parseInt(this.input_type);
       var required = "";
       if (parseInt(this.required) == 1){
          required = "required:true"; 
       }
       switch(input_type){
           case 0:
               result+='<li><div class="title">'+ this.name +'</div><div class="input">'+
                    '<input type="text" class="easyui-validatebox" name="'+format_input_name(this)+'" data-options="'+ required + '"  placeholder="'+ this.placeholder +'" value="'+ this.value +'"/></div></li>';
               break;
           case 1:
               result+='<li><div class="title">'+ this.name +'</div><div class="input">'+
                    '<textarea name="'+format_input_name(this)+'" rows = "5" placeholder="'+ this.placeholder +'">'+this.value+'</textarea></div></li>';
               break;
           case 2:
           case 3:
               var precision = "";
               if (input_type == 3){
                  precision = ",precision:2";
               }
               result+='<li><div class="title">'+ this.name +'</div><div class="input">'+
                       '<input type="text" class="easyui-validatebox easyui-numberbox" name="'+format_input_name(this)+'" data-options="'+ required + precision + '" placeholder="'+ this.placeholder +'" value="'+ this.value +'"/></div></li>';
               break;               
           case 4:
               var input_options = this.input_options.split(",");
               result+='<li><div class="title"></div><div class="input"><fieldset style="border:1px #ccc solid;margin:0;border-radius:5px"><legend>'+ this.name +'</legend>';
               for(var i=0; i< input_options.length; i++){
                    var option_vt = input_options[i].split("=");
                    result+='<label><input name="'+format_input_name(this)+'" type="radio" value="'+ option_vt[0] +'" />'+option_vt[1]+'</label>';
               }
               result+='<div></fieldset></li>';
               break;
           case 5:
               var input_options = this.input_options.split(",");
               result+='<li><div class="title"></div><div class="input"><fieldset style="border:1px #ccc solid;border-radius:5px"><legend>'+ this.name +'</legend>';
               for(var i=0; i< input_options.length; i++){
                  var option_vt = input_options[i].split("=");
                  result+='<label><input type="checkbox" name="'+format_input_name(this)+'[]" class="easyui-validatebox" value="'+ option_vt[0] +'" />'+option_vt[1]+'</label>';
               }
               result+='<div></fieldset></li>';
               break;
           case 6:
               result+='<li><div class="title">'+ this.name +'</div><div class="input">'+
                       '<input type="text" name="'+ format_input_name(this) +'" class="easyui-datebox easyui-validatebox" data-options="'+ required + '" value="'+ this.value +'"/></div></li>';
               break;
           case 7:
               break;
           case 8:
               break;               
       }
    });
    result+= '<div class="form-actions">'+
        '<a href="javascript:void(0)" class="easyui-linkbutton fontawesome-icon-button" plain="true" icon="fa-save fa-lg"  onclick="action_preview()">预览</a>'+
        '</div>';
    return result;
}

function format_input_name(group_data){
    return "group_field_" + group_data.input_name;
}