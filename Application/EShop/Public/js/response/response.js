$(function(){
	$(window).resize(infinite);
	function infinite() {
		var htmlWidth = $('html').width();
		if (htmlWidth >= 960) {
			$("html").css({
				"font-size" : "42px"
			});
		} else {
			$("html").css({
				"font-size" :  36 / 1080 * htmlWidth + "px"
			});
		}
	}infinite();
	
});




