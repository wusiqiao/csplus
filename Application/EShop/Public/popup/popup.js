//资金提现
$(function () {
	//更换按钮点击
    $(".bank-right").click(function () {
        $("#bg").css({
            display: "block", height: $(document).height()
        });
        var $box = $('.card-list');
        $box.css({
            display: "block",
        });
    });
    //点击银行卡的时候，遮罩层关闭
    $(".chose-card").on('click',function () {
    	$(this).children('input[type=radio]').prop('checked',true);
        $("#bg,.card-list").css("display", "none");
        var selector = $(this).siblings().children('input[type=radio]');
        if(selector.is(':checked')){
        	selector.prop('checked',false);
        }
    });
});

$(function(){
	$(".show-bank").on('click',function () {
        $("#bg").css({
            display: "block", height: $(document).height()
        });
        var $box = $('.bank-list');
        $box.css({
            display: "block",
        });
    });
    
    $(".line").on('click',function () {
    	$(this).children('input[type=radio]').prop('checked',true);
        $("#bg,.bank-list").css("display", "none");
        var selector = $(this).siblings().children('input[type=radio]');
        if(selector.is(':checked')){
        	selector.prop('checked',false);
        }
        $('#deposit_name').val($(this).children('span').html());      
    });
})

// 分享有奖
$(function(){
    $('.header-share').on('click',function(){
        $("body").css({
            'position':'fixed',
            "top":"-" + $("body").scrollTop() + "px",
            'left':"0",
            'right':"0"
        });
        $('#modal-share').css({
            'display':'block'
        });
    })
    $('#share-rule').on('click',function(){
        $("body").css({
            'position':'fixed',
            "top":"-" + $("body").scrollTop() + "px",
            'left':"0",
            'right':"0"
        });
        $('#modal-rules').css({
            'display':'block'
        });
    })

    $('#come-back').on('click',function(){
        window.history.back();
    })
    $('#close-all,#modal-share').on('click',function(){
        $('#modal-share').hide();
        $('#modal-rules').hide();
        $('body').removeAttr('style');
    })
})


