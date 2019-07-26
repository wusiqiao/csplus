$(function () {
    $('#signout').click(function () {
        $.dialog.confirm('您确定要退出本次登录吗?',
                function () {
                    location.href = app_config.module + '/Login/logout';
                },
                function () {
                    return true;
                }
        );
    });

    $('#download').click(function () {
        var url = $(this).attr('data-url');
        location.href = url;
    });

    $(".nav li a").click(function () {
        $(".nav li a.selected").removeClass("selected")
        $(this).addClass("selected");
    })

    $('#show-menu').click(function (e) {
        var posRef = $("#menu_accrdion");
        $("#user-info").menu('show', {
            left: posRef.offset().left + 2,
            top: posRef.offset().top,
            hideOnUnhover: true
        });
    });
    $("#menu_accrdion li.menu_item").click(function(){
        var node = $.parser.parseOptions(this);
        $(this).siblings("li").removeClass("menu_item_hover");
        $(this).addClass("menu_item_hover");
        var url = app_config.module + "/" + node.url;
        if (node.params){
           url = url +  node.params;
        }
        newTabFrame(node.url, node.text, url, node.icon, true, node.style);
    });
    $(".menu_item_title").click(function(evt){
        evt.stopPropagation();
        var $target = $(this);
        var $parent = $target.parents(".layout-panel-west");
        $(this).siblings("li").removeClass("menu_item_hover");
        $(this).addClass("menu_item_hover");
        
        var id = $target.attr("id");
        var $contentWrap = $("#sub_menu");
        var $menu_list = $target.find("ul");
        if ($menu_list.length > 0){
            $menu_list.appendTo($contentWrap);
            $contentWrap.find("li").show();
        }
        $contentWrap.find("ul").hide();
        $contentWrap.find("ul[class='menu-"+ id +"']").show();
        var left = $target.offset().left + $target.outerWidth(true) + 3;
        var top = $target.offset().top + ($target.outerHeight(true)-$contentWrap.outerHeight(true))/2;
        if (top < $parent.offset().top){
            top = $parent.offset().top;
        }
        $contentWrap.css("top", top);
        $contentWrap.css("left", left); 
        $contentWrap.find("li").click(function(){$contentWrap.fadeOut();});
        $contentWrap.fadeIn(); 
    });
    $("body").click(function(){
        $(".custom-float").hide();
        $(".monthpicker-content-wrap").hide();
    });//自定义浮动层。
    addTabCloseAllButton();
});

function addTabCloseAllButton(){
    var $closeBtn = $("<i id='tab_close_all' class='fa fa-remove fa-lg'  title='关闭全部'></i>");
    $closeBtn.click(function(){
        closeAllTabs();
        $(this).hide();
    });
    $closeBtn.appendTo($('#tabMain .tabs-wrap'));
}

function closeSelectTab(){
    var $currentTab = $('#tabMain').tabs("getSelected");
    var index = $('#tabMain').tabs('getTabIndex',$currentTab);
    $('#tabMain').tabs('close', index);
}

function newTabFrame(id, subtitle, url, icon, closable, style) {
    if (parseInt(style) === 2) { //dialog
        createDialog(url, subtitle, id);
    } else {
        closeAllTabs();
        if (!$('#tabMain').tabs('exists', subtitle)) {
            var data = {
                title: subtitle,
                closable: closable
            };
            if (parseInt(style) === 0) {
                data["href"] = url;
            } else {
                var content = '<iframe scrolling="no" frameborder="0"  src="' + url + '" style="width:100%;height:99%;"></iframe>';
                data["content"] = content;
            }
            if (icon !== "") {
                data["iconCls"] = icon;
            }
            $('#tabMain').tabs('add', data);
            $('#tab_close_all').show();
        } else {
            $('#tabMain').tabs('select', subtitle);
        }
    }
}

