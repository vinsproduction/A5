// JavaScript Document
var popupStatus = 0;

//loading popup with jQuery magic!
function loadPopup(){

	if(popupStatus==0){
		$("#backgroundPopup").css({
			"opacity": "0.8"
		});
		$("#backgroundPopup").show();
		$("#popup").css({"visibility":"visible"});
		$("#popup_close").show();
		popupStatus = 1;
	}
}

function disablePopup(){

	if(popupStatus==1){
		$("#backgroundPopup").hide();
		$("#popup").css({"visibility":"hidden", "left":"-1000000px", "top":"-1000000px"});
		popupStatus = 0;
	}
}

function centerPopup(){
	var windowWidth = document.documentElement.clientWidth;
	var windowHeight = document.documentElement.clientHeight;
	var popupHeight = $("#popup").height() + parseInt($("#popup").css('paddingTop')) + parseInt($("#popup").css('paddingBottom'));
	var popupWidth = $("#popup").width() + parseInt($("#popup").css('paddingLeft')) + parseInt($("#popup").css('paddingRight'));
	var windowScroll = $(window).scrollTop();
	var t = windowHeight/2-popupHeight/2 + windowScroll;
	if (t < 0) {
		t = 0;
	}
	$("#popup").css({
		"position": "absolute",
		"top": t,
		"left": windowWidth/2-popupWidth/2
	});
}







// Функиця открытия конкретного попапа
// closeDisable -- true, если надо без кнопки Закрытия (блокирут возможность закрытия попапа)
function openPopup($el, closeDisable){
	
	$("div.popup").css({"position":"absolute", "visibility":"hidden", "left":"-1000000px", "top":"-1000000px"});
	$el.css({"position":"relative", "visibility":"visible", "left":"0px", "top":"0px"});
	$("#popup").css({"width":$el.width() + "px"});

	centerPopup();
	loadPopup();
	
	if(closeDisable){
		$("#popup_close").hide();
		popupStatus = 0;
	};	
	
}

// Функция закрытия всех попапов
function closePopup(){
	
	popupStatus = 1;
	//$("div.popup").css({"position":"absolute"}).hide();
	disablePopup();
}


// кастомный попап для вывода любой	информации
// button -- если нужна кнопка OK, передается функция пост обработки нажатия или bool
// Например: customPopup('Необходимо авторизоваться', function() { openPopup($('#popup_register')) });

function customPopup(text, button ){
	
	var $el = $("#popup_custom");
	var $button = $el.find('.button input');
		
	if(button){
		
		$button.show();
		
		$button.click(function(event){

			if(typeof button == 'function'){			
				button();
			}else{			
				closePopup();
			}		
			
			$button.unbind('click');			
		return false;
		});		

	}else{
		
		$button.hide();
		$button.unbind('click');
	}
	
	$el.find('p').html(text);
	openPopup($el);
}


//CONTROLLING EVENTS IN jQuery
$(document).ready(function(){
	
	$("#popup").css({"visibility":"hidden"});
	
	$("a.popup").click(function(){
		try {
			var id = $(this).attr("id").substr(5);
		}
		catch(err) {
		
			/* 	Для валидатора - rel - НЕ может быть произвольным значением. 
				Поэтому лучше использовать атрибут data-something. 
			*/
			var rel = $(this).attr("rel");
			var data = $(this).attr("data-name");

			var id = (rel) ? rel : data;
			
			
		}
		$("div.popup").css({"position":"absolute", "visibility":"hidden", "left":"-1000000px", "top":"-1000000px"});
		$("#" + id).css({"position":"relative", "visibility":"visible", "left":"0px", "top":"0px"});
		$("#popup").css({"width":$("#" + id).width() + "px"});
				
		centerPopup();
		loadPopup();
		return false;
	});
	
	//Click out event!
	$("#backgroundPopup, #popup_close").click(function(){	
		if(popupStatus==1){
			disablePopup();
		}
		return false;
	});
	
	//Press Escape event!
	$(document).keyup(function(e){
		if(e.keyCode==27 && popupStatus==1){
			disablePopup();
		}
	});
});

$(window).resize(function(){
	if(popupStatus == 1){
		centerPopup();
	}
});