/**
 * IndexedList
 * 类似联系人应用中的联系人列表，可以按首字母分组
 * 右侧的字母定位工具条，可以快速定位列表位置
 * varstion 1.0.0
 * by Houfeng
 * Houfeng@DCloud.io
 **/

(function($, window, document) {

	var classSelector = function(name) {
		return '.' + $.className(name);
	}
	
	var IndexedList = $.IndexedList = $.Class.extend({
		/**
		 * 通过 element 和 options 构造 IndexedList 实例
		 **/
		init: function(holder, options) {
			var self = this;
			self.options = options || {};
			self.box = holder;
			if (!self.box) {
				throw "实例 IndexedList 时需要指定 element";
			}
			self.createDom();
			self.findElements();
			self.caleLayout();
			self.bindEvent();
		},
		createDom: function() {
			var self = this;
			self.el = self.el || {};
			//styleForSearch 用于搜索，此方式能在数据较多时获取很好的性能
			self.el.styleForSearch = document.createElement('style');
			(document.head || document.body).appendChild(self.el.styleForSearch);
		},
		findElements: function() {
			var self = this;
			self.el = self.el || {};
			self.el.head = self.box.querySelector(classSelector('head'));
			self.el.mainTab = self.box.querySelector(classSelector('main-tab'));
			self.el.search = self.box.querySelector(classSelector('search-area'));
			self.el.selectWrap = self.box.querySelector(classSelector('select-wrap'));
			self.el.previewBtn = self.box.querySelector(classSelector('previewBtn'));
			self.el.searchInput = self.box.querySelector(classSelector('search-area-input'));
			self.el.searchClear = self.box.querySelector(classSelector('indexed-list-search') + ' ' + classSelector('icon-clear'));
			self.el.bottombtn = self.box.querySelector(classSelector('bottom-check-commit'));
			self.el.bottomSpace = self.box.querySelector(classSelector('bottom-space'));
			self.el.bar = self.box.querySelector(classSelector('indexed-list-bar'));
			headBar = document.querySelector(classSelector('bar-nav'));
			self.el.barItems = [].slice.call(self.box.querySelectorAll(classSelector('indexed-list-bar') + ' a'));
			self.el.inner = self.box.querySelector(classSelector('indexed-list-inner'));
			self.el.items = [].slice.call(self.box.querySelectorAll(classSelector('indexed-list-item')));
			self.el.liArray = [].slice.call(self.box.querySelectorAll(classSelector('indexed-list-inner') + ' li'));
			self.el.alert = self.box.querySelector(classSelector('indexed-list-alert'));
		},
		caleLayout: function() {
			var self = this;
			if(!self.el.mainTab && self.el.bottombtn){
				//有底部全选搜索栏无窗口切换
				console.log('有底部全选搜索栏无窗口切换');
				if(headBar){
					var withoutOtherHeight = (document.body.offsetHeight - self.el.search.offsetHeight - self.el.bottombtn.offsetHeight - headBar.offsetHeight - 25) + 'px';					
				} else {
					var withoutOtherHeight = (document.body.offsetHeight - self.el.search.offsetHeight - self.el.bottombtn.offsetHeight - 25) + 'px';							
				}
				self.el.inner.style.height = withoutOtherHeight;
			} else if (!self.el.bottombtn && self.el.mainTab) {
				//无底部有窗口切换和搜索框
				console.log('无底部有窗口切换和搜索框');
				var withoutOtherHeight = (document.body.offsetHeight - self.el.mainTab.offsetHeight - self.el.search.offsetHeight - headBar.offsetHeight - 40) + 'px';
				self.el.inner.style.height = withoutOtherHeight;
			} else if(self.el.bottombtn && self.el.mainTab) {
				//有底部、窗口切换、搜索栏
				console.log('有底部、窗口切换、搜索栏');
				var withoutOtherHeight = (document.body.offsetHeight - self.el.mainTab.offsetHeight - self.el.search.offsetHeight - self.el.bottombtn.offsetHeight - 120) + 'px';
				self.el.inner.style.height = withoutOtherHeight;
			} else if (self.el.previewBtn) {
				//预览专用(弹窗不需要顶部返回按钮）
				console.log('预览专用(弹窗不需要顶部返回按钮）');
				var withoutOtherHeight = (document.body.offsetHeight - self.el.search.offsetHeight - self.el.previewBtn.offsetHeight - 35) + 'px';
				self.el.inner.style.height = withoutOtherHeight;
			} else if (self.el.head) {
				//分组&标签
				console.log('分组&标签');
				var withoutOtherHeight = (document.body.offsetHeight - self.el.head.offsetHeight - headBar.offsetHeight - 20) + 'px';
				if(self.el.inner){
					self.el.inner.style.height = withoutOtherHeight;
				}
			} else {
				//客户列表，仅减去外部wrap内边距
				console.log('客户列表，仅减去外部wrap内边距');
				var withoutOtherHeight = (document.body.offsetHeight - headBar.offsetHeight - 20) + 'px';
				self.el.inner.style.height = withoutOtherHeight;
			}
			
			
		},
		scrollTo: function(group) {
			var self = this;
			var groupElement = self.el.inner.querySelector('[data-group="' + group + '"]');
			if (!groupElement || (self.hiddenGroups && self.hiddenGroups.indexOf(groupElement) > -1)) {
				return;
			}
			self.el.inner.scrollTop = groupElement.offsetTop;
		},
		bindBarEvent: function() {
			var self = this;
			var pointElement = null;
			var findStart = function(event) {
				if (pointElement) {
					pointElement.classList.remove('active');
					pointElement = null;
				}
				self.el.bar.classList.add('active');
				var point = event.changedTouches ? event.changedTouches[0] : event;
				pointElement = document.elementFromPoint(point.pageX, point.pageY);
				if (pointElement) {
					var group = pointElement.innerText;
					if (group && group.length == 1) {
						pointElement.classList.add('active');
						self.el.alert.innerText = group;
						self.el.alert.classList.add('active');
						self.scrollTo(group);
					}
				}
				event.preventDefault();
			};
			var findEnd = function(event) {
				self.el.alert.classList.remove('active');
				self.el.bar.classList.remove('active');
				if (pointElement) {
					pointElement.classList.remove('active');
					pointElement = null;
				}
			};
			self.el.bar.addEventListener($.EVENT_MOVE, function(event) {
				findStart(event);
			}, false);
			self.el.bar.addEventListener($.EVENT_START, function(event) {
				findStart(event);
			}, false);
			document.body.addEventListener($.EVENT_END, function(event) {
				findEnd(event);
			}, false);
			document.body.addEventListener($.EVENT_CANCEL, function(event) {
				findEnd(event);
			}, false);
		},
		search: function(keyword) {
			var self = this;
			keyword = (keyword || '').toLowerCase();
			var selectorBuffer = [];
			var groupIndex = -1;
			var itemCount = 0;
			var liArray = self.el.liArray;
			var itemTotal = liArray.length;
			self.hiddenGroups = [];
			var checkGroup = function(currentIndex, last) {
				if (itemCount >= currentIndex - groupIndex - (last ? 0 : 1)) {
					selectorBuffer.push(classSelector('indexed-list-inner li') + ':nth-child(' + (groupIndex + 1) + ')');
					self.hiddenGroups.push(liArray[groupIndex]);
				};
				groupIndex = currentIndex;
				itemCount = 0;
			}
			liArray.forEach(function(item) {
				var currentIndex = liArray.indexOf(item);
				if (item.classList.contains($.className('indexed-list-group'))) {
					checkGroup(currentIndex, false);
				} else {
					var text = (item.innerText || '').toLowerCase();
					var value = (item.getAttribute('data-value') || '').toLowerCase();
					var tags = (item.getAttribute('data-tags') || '').toLowerCase();
					if (keyword && text.indexOf(keyword) < 0 &&
						value.indexOf(keyword) < 0 &&
						tags.indexOf(keyword) < 0) {
						selectorBuffer.push(classSelector('indexed-list-inner li') + ':nth-child(' + (currentIndex + 1) + ')');
						itemCount++;
					}
					if (currentIndex >= itemTotal - 1) {
						checkGroup(currentIndex, true);
					}
				}
			});
			if (selectorBuffer.length > itemTotal) {
				self.el.inner.classList.add('empty');
			} else if (selectorBuffer.length > 0) {
				self.el.inner.classList.remove('empty');
				self.el.styleForSearch.innerText = selectorBuffer.join(', ') + "{display:none !important;}";
			} else {
				self.el.inner.classList.remove('empty');
				self.el.styleForSearch.innerText = "";
			}
		},
		bindSearchEvent: function() {
			var self = this;
			if(self.el.searchInput){
				self.el.searchInput.addEventListener('input', function() {
					var keyword = this.value;
					self.search(keyword);
				}, false);
			}
			
			$(self.el.search).on('tap', classSelector('icon-clear'), function() {
				self.search('');
			}, false);
		},
		bindEvent: function() {
			var self = this;
			self.bindBarEvent();
			self.bindSearchEvent();
		}
	});

	//mui(selector).indexedList 方式
	$.fn.indexedList = function(options) {
		//遍历选择的元素
		this.each(function(i, element) {
			if (element.indexedList) return;
			element.indexedList = new IndexedList(element, options);
		});
		return this[0] ? this[0].indexedList : null;
	};

})(mui, window, document);