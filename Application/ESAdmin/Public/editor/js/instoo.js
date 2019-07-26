$.browser = {};
$.browser.mozilla = /firefox/.test(navigator.userAgent.toLowerCase());
$.browser.webkit = /webkit/.test(navigator.userAgent.toLowerCase());
$.browser.opera = /opera/.test(navigator.userAgent.toLowerCase());
$.browser.msie = /msie/.test(navigator.userAgent.toLowerCase()) || /trident/.test(navigator.userAgent.toLowerCase());
if ($.browser.msie) {
    alert("请使用谷歌或360/搜狗/猎豹等浏览器的极速模式，勿使用IE浏览器！");
}
rs_callbacks.loginSucess = function (request) {
    if (request.success) {
        publishController.close_dialog();
        $('#login_errorinfo').hide();
        if (sso.form) {
            $(sso.form).trigger("submit");
            sso.form = null;
        }
        if (sso.callback) {
            sso.callback.apply(sso.callback, sso.callback_args);
        }
        reloadStyleOperate();
        $('#login-menus').removeClass('hidden');
        $('#login-links').remove();
        $('#login-user-name').html(request.userinfo.username);

        if (request.userinfo && request.userinfo.session_flash) {
            request.success += request.userinfo.session_flash;
        }
    } else {
    }
    ;
}
$('#btn-favor-color').click(function () {
    var colors = $('#custom-color-text').css('backgroundColor');//.val();
    var color_array = [];
    color_array[0] = colors;
    var hasfavor = false;
    $('#favor-colors li').each(function () {
        if ($.inArray($(this).css('backgroundColor'), color_array) == -1) {
            colors += ';' + $(this).css('backgroundColor');
        } else {
            hasfavor = true;
        }

    });
    if (hasfavor == false) {
        $('#favor-colors').prepend('<li class="color-swatch" style="background-color:' + $('#custom-color-text').val() + ' ;"><i title="删除" class="fa fa-remove"></i></li>');
    } else {
        alert('此颜色已收藏');
    }
});
$(document).on('click', '#favor-colors .fa-remove', function (e) {

    e.preventDefault();
    e.stopPropagation();

    $(this).parent().remove();
    var colors = '';
    $('#favor-colors li').each(function () {
        colors += $(this).css('backgroundColor') + ';';
    });
    //setFavorColor(colors);
    return false;
});


function reloadStyleOperate() {
    $('#style-operate-area').append('<div style="position:absolute; background: rgba(255,255,255,0.7);width: 100%;line-height: 65px;text-align: center" title="若自动刷新失败，请按"F5"手动刷新"><img src="' + BASEURL + '/img/ajax/wheel_throbber.gif"> 正在加载数据...</div>').load('/beautify_editor #style-operate-area', function () {
        $('.editor-template-list').mixItUp({
            layout: {display: 'block'},
            callbacks: {}
        });
        $('.popover-trigger').popover({trigger: "hover"});
        $('#btn-help').popover();
        $('#editor-type-pop').popover({
            trigger: 'hover'
        });
    });
}
$(function () {
    setTimeout(function () {
        $('#qrcode-pannel').fadeOut();
        ;
    }, 30000);
    $('#style-categories .filter').click(function () {
        $('#insert-style-list').scrollTop(0); //切换分类时，回到顶部
    })
    $('#right-fix-tab > li > a').click(function () {

        var t = $(this).data('toggle');
        if ($(t).hasClass('active')) {
            $(t).parent('.tab-content').hide();
            $(t).removeClass('active');
        } else {
            $('#color-choosen').removeClass('active');
            $('#features').removeClass('active');

            $(t).parent('.tab-content').show();
            $(t).addClass('active');
        }
    })
    $(window).resize(function () {
        var win_height = $(window).height();
        $('#full-page').height(win_height - 23);
        var area_height = win_height - 100;
        if (area_height > 800) {
            area_height = 800;
        } else {
            $('#full-page').addClass('small-height');
            area_height += 5;
        }
        if ($(window).width() < 1000 && win_height < 650) {
            if ($('#color-choosen').hasClass('active')) {
                $('#right-fix-tab > li > a:first').trigger('click');
            }
        } else {
            if (!$('#color-choosen').hasClass('active')) {
                $('#color-choosen').parent('.tab-content').show();
                $('#right-fix-tab > li > a:first').trigger('click');
            }
        }
        $('#editor,.edui-editor-iframeholder').height(area_height - 16);
        $('.item,.n1-1').height(area_height);
        $('.editor2').height(area_height);
    }).trigger('resize');
    $('.autonum').on('mousewheel', function (event) {
        if (event.deltaY < 0) { //向下滚动
            $(this).html(parseInt($(this).html()) - 1);
        } else {
            $(this).html(parseInt($(this).html()) + 1);
        }
        return false;
    }).tooltip({'title': '上下滚动鼠标，可调整序号大小', container: 'body'});

    window.onbeforeunload = function (event) {
//        var html = getEditorHtml();
//        if (html != "") {
//            if (window.localStorage) {
//                localStorage._v3content = html;
//            }
//            if (isout == "true") {
//                event.returnValue = "即将离开页面，是否确认编辑的内容已使用并废弃？";
//            }
//        }
        event.returnValue = "即将离开页面，是否确认编辑的内容已使用并废弃？";
    }

    $('#html-more-popover').popover({trigger: "hover"}).on('shown.bs.popover', function () {
        var $this = $(this);
        $('#more-popover .popover-content').html($('#more-popover-content').html());
    })

    $('.editor-template-list').mixItUp({
        layout: {display: 'block'},
        callbacks: {}
    });
    $('.popover-trigger').popover({trigger: "hover"});
    $('#btn-help').popover();
    $('#editor-type-pop').popover({
        trigger: 'hover'
    });


});

/*=======*/

var less_parser = new less.Parser;
//ZeroClipboard.config( { swfPath: "ueditor/third-party/zeroclipboard/ZeroClipboard.swf" } );
//var client = new ZeroClipboard( $('.copy-editor-html') );
current_editor = UE.getEditor('editor');

$(function () {
    $('.colorPicker').colorPicker({
        customBG: '#222',
        init: function (elm, colors) {
            elm.style.backgroundColor = elm.value;
            elm.style.color = colors.rgbaMixCustom.luminance > 0.22 ? '#222' : '#ddd';
        }
    });

    $(document).on('click', '#flat-strip-shadow', function () {
        current_active_v3item.find("section:first").css('box-shadow', '');
        current_editor.undoManger.save();
    });

    $(document).on('click', '#flat-add-shadow', function () {
        $(current_active_v3item).find("section:first").css('box-shadow', 'rgba(205, 205, 205,0.9) 2px 3px 2px');
        current_editor.undoManger.save();
    });


    $(document).on('click', '#set-image-radius', function () {
        $(current_active_v3item).find("section:first").css('border-radius', '50%');
        current_editor.undoManger.save();
    });
    $(document).on('click', '#set-image-border', function () {
        $(current_active_v3item).find("section:first").css({"background-color": "#fff", "border-radius": "4px", "max-width": "100%", "padding": "4px", "border": "1px solid #ddd"});
        current_editor.undoManger.save();
    })

    $(document).on('click', '#flat-add-radius', function () {
        $(current_active_v3item).find("section:first").css('border-radius', '4px');
        current_editor.undoManger.save();
    });

    $(document).on('click', '#flat-strip-radius', function () {
        $(current_active_v3item).find("section:first").css('border-radius', '');
        current_editor.undoManger.save();
    });

    $(document).on('click', '#flat-add-border', function () {
        $(current_active_v3item).find("section:first").css('border', '1px solid #efefef');
        current_editor.undoManger.save();

    });
    $(document).on('click', '#flat-strip-border', function () {
        $(current_active_v3item).find("section:first").css({"border-width": "0", "border": ""});
        current_editor.undoManger.save();
    })

    $(document).on('dblclick', '#insert-style-list .ui-portlet-list > li', function () {

        if ($(this).hasClass('ignore')) {
            return false;
        }

        var ret = false;
        var num = parseInt($(this).find('.autonum:first').text());

        if (typeof num != "undefined") {
            $(this).find('.autonum:first').find('.autonum:first').text(num + 1);
        }

        var id = $(this).attr('hs-imagetext-id');


        $(this).contents().filter(function () {
            return this.nodeType === 3 && $.trim($(this).text()) == "";
        }).remove();
        $(this).find('p').each(function () {
            if ($.trim($(this).html()) == "&nbsp;") {
                $(this).html('<br/>');
            }
        });
        $(this).find('*').each(function () {
            if ($(this).attr('data-width')) {
                return;
            }

            if (this.style.width && this.style.width != "") {
                $(this).attr('data-width', this.style.width);
            }
        });
        var style_item = $(this).find('.fzneditor:first');
        if (style_item.size()) {
            // insertHtml( $(this).html() + "<p>&nbsp;</p>"); 包含收藏夹的删除按钮等 
            ret = insertHtml("<section hs-imagetext-id=\"" + id + "\" class=\"fzneditor\">" + style_item.html() + "</section><p><br/></p>");
        } else { //最外围包装fzneditor容器
            ret = insertHtml("<section hs-imagetext-id=\"" + id + "\" class=\"fzneditor\">" + $(this).find(".wrap").html() + "</section><p><br/></p>");
        }

        if (ret) {
            if (typeof num != "undefined") {
                $(this).find('.autonum:first').text(num + 1);
            }
            //style_click($(this).data('id')); //统计使用该样式的次数
        }

    });
    $(document).on('click', '#v3-random-transform', function () {
        var editor_document = current_editor.selection.document;
        var deg = parseInt(Math.random() * 8);
        var f = Math.random() * 10 > 5 ? '+' : '-';
        $(editor_document).find('.fzneditor').each(function (i) {
            if ((i + 1) % 3 == 0) {
                deg = parseInt(Math.random() * 8);
                f = Math.random() * 10 > 5 ? '+' : '-';
            }
            $(this).css('transform', 'rotate(' + f + deg + 'deg)').css('-webkit-transform', 'rotate(' + f + deg + 'deg)');
        });
    });
    $(document).on('click', '#v3-random-color', function () {
        var html = getDealingHtml();
        var obj = $('<div>' + html + '</div>');
        var bgcolors = ['#5BB75B', '#2E8BCC', '#F09609', '#E51400', '#7B4F9D', '#E671B8', '#008641', '#20608E', '#FFC40D'];
        var rd = Math.floor(Math.random() * (bgcolors.length));
        var used = [];
        var current_bgcolor = bgcolors[rd];
        var components = obj.find('.fzneditor').each(function (i) {
            if (i % random_color_step == 0) {
                do
                {
                    rd = Math.floor(Math.random() * (bgcolors.length));
                } while (jQuery.inArray(rd, used) != -1);

                used[used.length] = rd;
                if (used.length == bgcolors.length) {
                    used = [];
                }

                current_bgcolor = bgcolors[rd];
            }
            $(this).html(parseHtml($(this).html(), current_bgcolor, current_select_color));
        });

        setDealingHtml(obj.html())
        obj = null;
    });
    $(document).on('click', '#tpl-tab-content .template-cover,#tpl-tab-content .editor-style-content', function () { //选中模板后设置编辑器的内容	
        var tid = $(this).data('id');
        var obj = $('#template-' + tid);

        obj.contents().filter(function () {
            return this.nodeType === 3 && $.trim($(this).text()) == "";
        }).remove();
        obj.find('p').each(function () {
            if ($.trim($(this).html()) == "&nbsp;") {
                $(this).html('<br/>');
            }
        });
        setEditorHtml(obj.html());
        var id = parseInt(obj.data('id'));
        if (id > 0) {
            $('#template_id').val(id);
            $('#auto_cover_tpl').removeClass('hidden');
        } else {
            $('#template_id').val('');
            $('#auto_cover_tpl').addClass('hidden');
        }

        $('#template_name').val(obj.data('name'));
        $('#dialog-template-name').val(obj.data('name'));
    });

    $(document).on('click', '.delete-msg', function () {
        var $this = $(this);
        var url = $(this).data('url');
        if (confirm('是否确认删除这条图文消息')) {
            ajaxAction(url, null, null, function (request) {
                $this.parents('.article-msg:first').remove();
            });
        }
    })

    $(document).on('click', '.article-msg a', function (e) {
        e.stopPropagation();
    });

    $(document).on('click', '.article-msg', function () {
        var id = $(this).data('id');
        current_edit_msg_id = id;
        var url = BASEURL + '/wx_msgs/view/' + id + '.json?nolazy=1';
        ajaxAction(url, null, null, function (request) {
            if (request.data) {
                setEditorHtml(request.data.WxMsg.content);
            }
        });

    });


    $('.color-swatch').click(function () {
        $('.color-swatch').removeClass('active');
        $(this).addClass('active');
        var color = $(this).data('color'); //data-color为前景色，bgcolor为背景色，或者无背景文字的前景色
        var bgcolor = $(this).css('backgroundColor');
        $('#custom-color-text').val(bgcolor).css('backgroundColor', bgcolor);
        if (!color)
            color = '#FFF';
        setBackgroundColor(bgcolor, color, true);
        if (!current_active_v3item) {
            $('.editor-template-list li > .fzneditor').each(function () {
                parseObject($(this), bgcolor, color);
                $(this).attr('data-color', bgcolor);
            })
        }
    });

    $(document).on('click', '#v3-random-color', function () {
        var html = getDealingHtml();
        var obj = $('<div>' + html + '</div>');
        var bgcolors = ['#5BB75B', '#2E8BCC', '#F09609', '#E51400', '#7B4F9D', '#E671B8', '#008641', '#20608E', '#FFC40D'];

        var rd = Math.floor(Math.random() * (bgcolors.length));
        var used = [];
        var current_bgcolor = bgcolors[rd];
        var components = obj.find('.fzneditor').each(function (i) {
            if (i % random_color_step == 0) {
                do
                {
                    rd = Math.floor(Math.random() * (bgcolors.length));
                } while (jQuery.inArray(rd, used) != -1);

                used[used.length] = rd;
                if (used.length == bgcolors.length) {
                    used = [];
                }

                current_bgcolor = bgcolors[rd];
            }
            $(this).html(parseHtml($(this).html(), current_bgcolor, current_select_color));
        });

        setDealingHtml(obj.html())
        obj = null;
    });

    $(document).on('click', '#tab-tpl-trigger', function () {
        if ($('#editor-tpls-list').html() == "") {
            $('#editor-tpls-list').html('正在加载').load('/editor_styles/myTemplates?suffix=-1 #tpl-tab-content', function () {
                $('#editor-tpls-list').find('.col-md-3').removeClass('col-md-3').addClass('col-md-6');
                $('#editor-tpls-navtab a:first').tab('show');
            })
        }
    });

    $('#clear-editor').click(function () {

        current_edit_msg_id = null;
        setEditorHtml("");

    });
    $('#html-see').click(function () {
        $('#saveModal').modal('show')
    });

//	client.on( 'ready', function(event) {
//		client.on( 'copy', function(event) {
//			clean_v3helper();
//	  		event.clipboardData.setData('text/html', getEditorHtml());
//	  		event.clipboardData.setData('text/plain',getEditorHtml());
//			$("#success").css("display","block");
//            setTimeout(function(){$("#success").css("display","none")},500);
//	  		//alert("已成功复制到剪切板");
//			});
//		});
//	$('.copy-editor-htmls').click(function(){
//		alert("注册用户才能复制编辑内容！");	
//	});
});
function clean_v3helper() {
    var editor_document = current_editor.selection.document;
    $(editor_document).find('.weixinhow.com').each(function () {
        $(this).removeAttr('style');
    });
}
$('#my-tpl-trigger').click(function () {
    if ($('#my-list').html() == "") {
        $('#my-list').html('正在加载').load('wxstyle/mystyle.php #loader', function () {
            $('#style-my a:first').tab('show');
        })
    }
})
$('#pic-tpl-trigger').click(function () {
    if ($('#pic-list').html() == "") {
        $('#pic-list').html('正在加载').load('wxstyle/style.php?type=9 #loader', function () {
            $('#style-pic a:first').tab('show');
        })
    }
})
$('#backg-tpl-trigger').click(function () {
    if ($('#backg-list').html() == "") {
        $('#backg-list').html('正在加载').load('wxstyle/style.php?type=8 #loader', function () {
            $('#style-backg a:first').tab('show');
        })
    }
})
$('#scene-tpl-trigger').click(function () {
    if ($('#scene-list').html() == "") {
        $('#scene-list').html('正在加载').load('wxstyle/style.php?type=7 #loader', function () {
            $('#style-scene a:first').tab('show');
        })
    }
})
$('#yuanwen-tpl-trigger').click(function () {
    if ($('#yuanwen-list').html() == "") {
        $('#yuanwen-list').html('正在加载').load('wxstyle/style.php?type=6 #loader', function () {
            $('#style-yuanwen a:first').tab('show');
        })
    }
})
$('#img-tpl-trigger').click(function () {
    if ($('#img-list').html() == "") {
        $('#img-list').html('正在加载').load('wxstyle/style.php?type=5 #loader', function () {
            $('#style-img a:first').tab('show');
        })
    }
})
$('#hutui-tpl-trigger').click(function () {
    if ($('#hutui-list').html() == "") {
        $('#hutui-list').html('正在加载').load('wxstyle/style.php?type=4 #loader', function () {
            $('#style-hutui a:first').tab('show');
        })
    }
})
$('#body-tpl-trigger').click(function () {
    if ($('#body-list').html() == "") {
        $('#body-list').html('正在加载').load('wxstyle/style.php?type=3 #loader', function () {
            $('#style-body a:first').tab('show');
        })
    }
})
$('#title-tpl-trigger').click(function () {
    if ($('#title-list').html() == "") {
        $('#title-list').html('正在加载').load('wxstyle/style.php?type=2 #loader', function () {
            $('#style-title a:first').tab('show');
        })
    }
})
if ($('#guanzhu-list').html() == "") {
    $('#guanzhu-list').html('正在加载').load('wxstyle/style.php?type=1 #loader', function () {
        $('#style-guanzhu a:first').tab('show');
    })
}
$('#guanzhu-tpl-trigger').click(function () {
    if ($('#guanzhu-list').html() == "") {
        $('#guanzhu-list').html('正在加载').load('wxstyle/style.php?type=1 #loader', function () {
            $('#style-guanzhu a:first').tab('show');
        })
    }
});



