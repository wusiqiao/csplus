(function ($) {
    function createToolbar(target) {
        var state = $.data(target, 'gridtoolbar');
        var toolbar = new Array();
        if (state.visible){
            if (state.toolbar.append){
               toolbar.push({text: '添加',iconCls: 'fa fa-plus',handler: function () { append(target); }}); 
            } 
            if (state.toolbar.edit){
               if (toolbar.length > 0){toolbar.push("-");}
               toolbar.push({text: '修改',iconCls: 'fa fa-edit',handler: function () { edit(target); }}); 
            }
            if (state.toolbar.remove){
               if (toolbar.length > 0){toolbar.push("-");}
               toolbar.push({text: '删除',iconCls: 'fa fa-trash',handler: function () { remove(target); }}); 
            }
            if (state.toolbar.cancel){
               if (toolbar.length > 0){toolbar.push("-");}
               toolbar.push({text: '撤销',iconCls: 'fa fa-undo',handler: function () { cancel(target); }}); 
            }
            if (state.toolbar.moveup){
               if (toolbar.length > 0){toolbar.push("-");}
               toolbar.push({text: '上移',iconCls: 'fa fa-arrow-up',handler: function () { MoveUp(target); }}); 
            }
            if (state.toolbar.movedown){
               if (toolbar.length > 0){toolbar.push("-");}
               toolbar.push({text: '上移',iconCls: 'fa fa-arrow-down',handler: function () { MoveDown(target); }}); 
            }
            if (state.toolbar.copy){
               if (toolbar.length > 0){toolbar.push("-");}
               toolbar.push({text: '复制',iconCls: 'fa fa-copy',handler: function () { copy(target); }}); 
            }
        }
        
        $(target).datagrid({
            toolbar: toolbar,
            ctrlSelect:true,
            singleSelect:false,
            onAfterEdit: function (rowIndex, rowData, changes) {
                state.editRow = undefined;  
                state.onChangeRow(target, rowIndex, rowData);         
            },
            onDblClickRow: function (rowIndex, rowData) {                            
                if (state.editRow !== undefined) {
                    $(target).datagrid('endEdit', state.editRow);                                
                }                           
                if (state.editRow === undefined) {
                    $(target).datagrid('beginEdit', rowIndex);
                    state.editRow = rowIndex;
                    state.onEditRow(target,rowIndex, rowData);            
                }                        
            },
            onClickRow: function (rowIndex, rowData) {            
                if (state.editRow !== undefined) {
                    $(target).datagrid('endEdit', state.editRow);                                 
                }      
            },
            onLoadSuccess: function(data){
                state.onLoadSuccess(target, data); 
            },
            queryParams: state.queryParams
        });
    }

    function MoveUp(target) {
        var row = $(target).datagrid('getSelected');
        var index = $(target).datagrid('getRowIndex', row);
        mysort(index, 'up', target);
    }
    //下移
    function MoveDown(target) {
        var row = $(target).datagrid('getSelected');
        var index = $(target).datagrid('getRowIndex', row);
        mysort(index, 'down', target);
    }

    function mysort(index, type, target) {
        var state = $.data(target, 'gridtoolbar');
        if ("up" === type) {
            if (index !== 0) {
                var toup = $(target).datagrid('getData').rows[index];
                var todown = $(target).datagrid('getData').rows[index - 1];
                if (state.sortField){
                   var sort = toup[state.sortField];
                   toup[state.sortField] = todown[state.sortField];
                   todown[state.sortField] = sort; //需要editor才会触发更新事件
                }
                $(target).datagrid('getData').rows[index] = todown;
                $(target).datagrid('getData').rows[index - 1] = toup;
                $(target).datagrid('refreshRow', index);
                $(target).datagrid('unselectRow', index);
                $(target).datagrid('refreshRow', index - 1);
                $(target).datagrid('selectRow', index - 1);
            }

        } else if ("down" === type) {
            var rows = $(target).datagrid('getRows').length;
            if (index !== rows - 1) {
                var todown = $(target).datagrid('getData').rows[index];
                var toup = $(target).datagrid('getData').rows[index + 1];
                if (state.sortField){
                   var sort = toup[state.sortField];
                   toup[state.sortField] = todown[state.sortField];
                   todown[state.sortField] = sort;                  
                }
                $(target).datagrid('getData').rows[index + 1] = todown;
                $(target).datagrid('getData').rows[index] = toup;
                $(target).datagrid('refreshRow', index);
                $(target).datagrid('unselectRow', index);
                $(target).datagrid('refreshRow', index + 1);
                $(target).datagrid('selectRow', index + 1);
            }

        }   
        //为确保效率，保存前一次触发row_no更新动作，待完善              
    }

    function saveRow(target) {
        var state = $.data(target, 'gridtoolbar');
        if (state.editRow !== undefined) {
             $(target).datagrid('endEdit', state.editRow);
            state.editRow = undefined;

        }

    }
    
    function append(target){
       var state = $.data(target, 'gridtoolbar'); 
       if (state.editRow !== undefined) {
            $(target).datagrid('endEdit', state.editRow);                                       
       }                                    
       if (state.editRow === undefined) {
           state.editRow = $(target).datagrid('getRows').length;
           $(target).datagrid('appendRow', {});
           $(target).datagrid('clearSelections');
           //$(target).datagrid('selectRow', state.editRow);
           $(target).datagrid('beginEdit', state.editRow);
           var editor = getSortEditor(target, state.editRow, state.sortField);
           if (editor){
              $(editor.target).numberbox('setValue', state.editRow);  
           }
           state.onAppendRow(target, state.editRow);         
       }        
    }
    
    function getSortEditor(target, rowIndex, sortField){
       return $(target).datagrid('getEditor', {index: rowIndex,field:sortField}); 
    }
    
    function appendAll(target,rows){
        var state = $.data(target, 'gridtoolbar'); 
        if (state.editRow !== undefined) {
            $(target).datagrid('endEdit', state.editRow);                                       
        }                                    
        if (state.editRow === undefined) {
           state.editRow = $(target).datagrid('getRows').length;
        }
        var index = state.editRow;
        $(rows).each(function(){
            if (state.sortField){
                this[state.sortField] = index;
            }
            $(target).datagrid('appendRow', this);
            $(target).datagrid('beginEdit', index); 
            state.onAppendRow(target, index, this);  
            $(target).datagrid('endEdit', index);             
            index++;
        });
        state.editRow = undefined; 
        $(target).datagrid('clearSelections');                     
    }
    
    function edit(target, rows){
        var state = $.data(target, 'gridtoolbar'); 
        var row = $(target).datagrid('getSelected');                                    
        if (row !== null) {                                            
            if (state.editRow !== undefined) {
                $(target).datagrid('endEdit', state.editRow);                    
            }                
            if (state.editRow === undefined) {                                                   
                var index = $(target).datagrid('getRowIndex', row);
                $(target).datagrid('beginEdit', index);
                state.onEditRow(target, index, row);
                state.editRow = index;                 
            }                                        
        }   
    }
    
    function remove(target){      
      $.messager.confirm('删除确认', '确认删除所选记录?',
        function (r) {
            if (r) {
                var state = $.data(target, 'gridtoolbar'); 
                var rows = $(target).datagrid('getSelections');
                if (rows.length !== 0){
                    $(rows).each(function(){
                        var index = $(target).datagrid('getRowIndex', this);
                        $(target).datagrid('deleteRow', index);
                        state.onChangeRow(target, index, this);
                    });
                }
            }
        });  
    }
    
    function copy(target) {
        var state = $.data(target, 'gridtoolbar'); 
        var row = $(target).datagrid('getSelected');
        var current_index = $(target).datagrid('getRowIndex', row);
        if (state.editRow !== undefined)
        {
            current_index =  state.editRow;
        }
        if (row['name']){
            row['name'] = row['name'] + "复制";
        }
        if (row[state.sortField]){
           row[state.sortField] = current_index;
        }
        row["id"] = Math.ceil(Math.random() * 1000000);
        $(target).datagrid('insertRow',{index: current_index + 1, row: row}); 
        $(target).datagrid('selectRow', current_index + 1);
        state.editRow = undefined;//current_index + 1;
    }
    
    function removeByField(target, data){      
      $.messager.confirm('删除确认', '确认删除对应的记录?',
        function (r) {
            if (r) {
                var rows = $(target).datagrid('getRows');
                $(rows).each(function(){
                    if ($.inArray(this[data.field], data.valueList) !== -1){
                        var index = $(target).datagrid('getRowIndex', this);
                        $(target).datagrid('deleteRow', index);
                    }
                });
            }
        });  
    }
    
    function cancel(target){
        var state = $.data(target, 'gridtoolbar'); 
        state.editRow = undefined;
        $(target).datagrid('rejectChanges');
      $(target).datagrid('unselectAll');  
    }
    
    function validate(target, event){
        var state = $.data(target, 'gridtoolbar'); 
        if (state.mainForm){
           if (state.editRow !== undefined) {
             $(target).datagrid('endEdit', state.editRow);
              state.editRow = undefined;
            }
            //系统自动添加一个input,用来传输detail数据和校验。
            if (state.mainForm.find("input[name='"+ state.name +"']").length === 0) {
                var $input = $("<input type='hidden' name='"+ state.name +"' />");
                $input.validatebox({required: true});//必须转化成easyui控件，保存前才会校验
                $input.appendTo(state.mainForm);
            }
            //每行检查
            var invalidRow = -1;
            var rows = $(target).datagrid('getRows');
            $(rows).each(function(){
                var index = $(target).datagrid('getRowIndex', this);
                $(target).datagrid('endEdit', index);
                if (!$(target).datagrid('validateRow', index)) {
                    invalidRow = index;
                    return false;
                }
            });
            if (invalidRow > -1){
                $.dialog.tips("请输入表身明细中红色必填项！");
                state.editRow = invalidRow; //定位在无效行
                return true;
            }
            var detail = {};
            //获取不到某个字段，可能是append的row里面没这个字段
            detail.inserted =  $(target).datagrid("getChanges", "inserted");
            detail.updated =  $(target).datagrid("getChanges", "updated");
            detail.deleted =  $(target).datagrid("getChanges", "deleted");  
            detail.all = $(target).datagrid("getRows");
            if (rows.length === 0 && !state.allowEmpty) {
                $.dialog.tips("表身明细数据不能为空！");
            } else {
                var detail_val = "{}"; //默认没改变，传入空对象符号
                if ((detail.inserted.length > 0) || (detail.updated.length > 0) 
                        || (detail.deleted.length > 0) || (detail.all.length > 0)) {
                    detail_val = JSON.stringify(detail);
                }
                $input = state.mainForm.find("input[name='"+ state.name +"']");
                $input.val(detail_val);
            }
        }
    }

    $.fn.gridtoolbar = function (options, param) {
        if (typeof options === 'string') {
            var method = $.fn.gridtoolbar.methods[options];
            if (method) {
                return method(this, param);
            }

        }
        options = options || {};
        return this.each(function () {
            var state = $.data(this, 'gridtoolbar');
            if (state) {
                $.extend(state.options, options);
            } else {
                $.data(this, 'gridtoolbar', $.extend({}, $.fn.gridtoolbar.defaults, options));
                createToolbar(this);
                var _this = this;
                if (options.mainForm){
                    options.mainForm.bind("Update", function (event){
                        validate(_this, event);
                    });
                }
            }
        });

    };

    $.fn.gridtoolbar.defaults = $.extend({},
    {
        editRow: undefined,
        sortField: 'row_no',
        toolbar:{append:true,edit:true,remove:true,cancel:true,moveup:true,movedown:true,copy:false},
        visible:true,
        allowEmpty: false,
        name:'Detail',
        onAppendRow: function (target, index, row) {
        },
        onEditRow: function (target, index, row) {
        },
        onChangeRow: function (target, index, row) {
        },
        onLoadSuccess:function(target, data){
        },
        queryParams:{}

    });

    $.fn.gridtoolbar.methods = {
        updateLastChanged: function (jq) {
            saveRow(jq[0]);
        },
        appendAll: function(jq, rows){
            return jq.each(function () {
                appendAll(this, rows);
            });
        },
        removeByField: function(jq, data){
            return jq.each(function () {
                removeByField(this, data);
            });
        },
        getCurrentRow: function (jq) {
            var state = $.data(jq[0], 'gridtoolbar');
            return state.editRow;
        },
        removeAll: function(jq){
            var rows = $(jq).datagrid('getRows');
            if (rows.length !== 0){
                $(rows).each(function(){
                    var index = $(jq).datagrid('getRowIndex', this);
                    $(jq).datagrid('deleteRow', index);
                });
            }
        }
    };



})(jQuery);