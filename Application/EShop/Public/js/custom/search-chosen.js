/* 
 * @param {number} defaultTime 10为1s
 * @param {string} searchList  搜索下拉列表id
 * @param {string} searchUrl   查询地址
 * @param {string} searchDropload 选中列表项时执行的函数名
 * @author Lynn
 * @date  July 25th,2017
 * */
(function($){
	$.fn.custormChosen = function(options){
		var settings = $.extend(true, {
			defaultTime : 5,
			searchList : '#show_search_list',
			serachUrl : '',
			searchDropload:''
		}, options);
		return new Mychosen(this,settings);
	}
	var Mychosen = function(element,options){
		var me = this;
		me.$element = element;
		me.$dropload = options.searchDropload;
		this.init(options);
	}
	Mychosen.prototype.init=function(options){
		var me = this;
		var default_time	= options.defaultTime;
		var chosenXhr 		= default_time;
		var the_time;
		me.$element.on('input propertychange',function(){
			if(chosenXhr == 0){
				chosenXhr = default_time;
			}else{
				clearTimeout(the_time);
				time(default_time);
			}
		})
		function time(default_time){
			the_time = setTimeout(function(){
							chosenXhr = default_time - 1;
							if(chosenXhr != 0){
								time(chosenXhr);
							}else{
								search_list_view();
							}
						},100);
		}
		function search_list_view(){
			var inputValue	=	me.$element.val();
								if (inputValue == '') {
									$(options.searchList).empty();
									return false;
								}
			get_search_list(inputValue);
		}
		function get_search_list(inputValue){
			//删除之前的搜所列表
			$(options.searchList).empty();
			$.getJSON(options.serachUrl, {value: inputValue}, function($data){
				var html = '';
				if($data){
					for (var i = 0;i < $data.length ; i++) {
						html +=parseTemplate("#li-list", $data[i]);
					}
					$(options.searchList).show().append(html);
				}
	       });
		}
		$(options.searchList).on('mousedown','li',function(){
			$(options.searchList).empty();
			var value	=	$(this).text();
			me.$element.val(value);
			if(me.$dropload){
				me.$dropload();
			}
		})
		me.$element.on('focusin',function(){
			$(options.searchList).show();
		})
		me.$element.on('focusout',function(){
			$(options.searchList).hide();
		})
	}
})(jQuery);
