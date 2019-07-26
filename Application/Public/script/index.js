$(function () {
    $('#signout').click(function () {
        $.dialog.confirm('您确定要退出本次登录吗?',
                function () {
                    setCookies("voucher_company_id","");
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
    // $("#menu_accrdion li.menu_item").click(function(){
    //     var node = $.parser.parseOptions(this);
    //     $(this).siblings("li").removeClass("menu_item_hover");
    //     $(this).addClass("menu_item_hover");
    //     var url = app_config.module + "/" + node.url;
    //     if (node.params){
    //        url = url +  node.params;
    //     }
    //     newTabFrame(node.url, node.text, url, node.icon, true, node.style);
    // });
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
    $(".extend-nav .menu_item").click(function(){
        displayMenuItemContent(this);
    });
    $('.side-bar li').on('click',function(){
        var module = $(this).data("url");
        var self = this;
        if (module == "VoucherModule") {
            if($(self).hasClass('active')){
                return false;
            } else {
                selectVourcherCompany("enter");
            }
        }else {
            var company_id = $(".page-selected .company-selected-container .company-name").data("company_select");
            if (company_id) {
                $.post("/Login/resetLoginUser", function (result) {
                    if (result.code == 0) {
                        $(".page-selected .company-selected-container").remove();
                        $("#module-content .content").html("");
                    }
                }, "json");
            }
            if ($(self).hasClass('active')) {
                return false;
            } else {
                showChildMenu(self);
            }
        }
    });
});

function selectVourcherCompany(type){
    var menu = $(".side-bar li[data-url='VoucherModule']");
    var isSerivce = $(".company-selected-container").length>0; //只有商户才会显示这个div
    if (isSerivce) {
        var company_id = getCookie('voucher_company_id');
        var company_name = getCookie('voucher_company_name');
        if(company_id && company_name && type == "enter"){
            enterVoucher(company_id,company_name,menu);
        }else{
            createSimpleDialog("/Login/selectCompany", '请选择要进入的公司', "dlg-company-select", function () {
                var company_id = $("input[name=company_selected]").val();
                var company_name = $("input[name=company_name]").val();
                if (company_id && company_name) {
                    enterVoucher(company_id,company_name,menu);
                    setCookies('voucher_company_id', company_id);
                    setCookies('voucher_company_name', company_name);
                    return true;
                } else {
                    $.dialog.tips("请先选择公司");
                    return false;
                }
            });
        }
    }else{
        showChildMenu(menu);
    }
}
//进入凭证功能
function enterVoucher(company_id,company_name,menu) {
    $.post("/Login/selectCompany", {branch_id: company_id}, function (result) {
        $("#module-content .content").html(result);
        var $company_selected_container =  $(".page-selected .company-selected-container");
        if ($company_selected_container.length == 0){
            $(".page-selected").append($(".company-selected-container").prop("outerHTML"));
            $company_selected_container = $(".page-selected .company-selected-container");
        }
        $company_selected_container.css("display", "inline-block");
        $company_selected_container.find(".company-name").data("company_select", company_id);
        $company_selected_container.find(".company-name").text(company_name);
        showChildMenu(menu);
    });
}
function showChildMenu(menu){
    var menu_id = $(menu).data('id');
    $('.side-bar > div').hide();
    $('.side-bar > div[bind=menu'+menu_id+']').css('display','flex');
    $(".extend-nav .menu_item").removeClass("active");
    if($(menu).siblings().hasClass('active')){
        $(menu).addClass('active').siblings().removeClass('active').parents('.side-bar').addClass('active');
    } else {
        $(menu).addClass('active').parents('.side-bar').addClass('active');
    }
}

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

function displayMenuItemContent(menuItem){
    var node = $.parser.parseOptions(menuItem);
    var url = app_config.module + "/" + node.url;
    if (node.params){
        url = url +  node.params;
    }
    $(menuItem).siblings().removeClass('active');
    $(menuItem).addClass("active");
    var title = $('.side-bar li.active').text() + ">" +  $(menuItem).text();
    $("#module-content .head span:first").text(title);
    $.get(url, function(result){
        if(result == '无此功能操作权限' || result == '登陆超时,请重新登陆！'){
            $.dialog.tips(result);
            return false;
        }
        $("#module-content .content").html("").append(result);
        $.parser.parse("#module-content .content");
        if (!node.allow){
            $.dialog.alert("此功能为付费版，付费升级请联系0592-5239592", function(){
                $("#module-content .content").html("");
            });
            $(".ui_title").text("友情提醒");
        }
    });
}

function displayMenuItemContentByUrl(url){
   var $menuItem = $($.format(".extend-nav .menu_item[data-url='{0}']", [url]));
   if ($menuItem.length == 1){
       displayMenuItemContent($menuItem.get(0));
   }
}

// 读取 cookie
function getCookie(c_name)
{
    if (document.cookie.length>0)
    {
        c_start = document.cookie.indexOf(c_name + "=")
        if (c_start!=-1)
        {
            c_start=c_start + c_name.length+1
            c_end=document.cookie.indexOf(";",c_start)
            if (c_end==-1) c_end=document.cookie.length
            return unescape(document.cookie.substring(c_start,c_end))
        }
    }
    return "";
}

function setCookies(name, value, time)
{
    var cookieString = name + "=" + escape(value) + ";";
    if (time != 0) {
        var Times = new Date();
        Times.setTime(Times.getTime() + time);
        cookieString += "expires="+Times.toGMTString()+";"
    }
    document.cookie = cookieString;
}
