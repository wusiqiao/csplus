

$('.v-footer ul li').on('click',function(){
	console.log('click');
	var jumpUrl = $(this).attr('jump');
	if(jumpUrl){
		console.log(jumpUrl);
		window.location.href = jumpUrl + '.html';
	}
})