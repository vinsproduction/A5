<?php

session_start();

// В проекте используются flash - переменные
Session::flash_init();

// Добавляет base href к head
//ob_start(array("A5", "ob_add_base_tag"));

// Глобальная переменная, хранящая id или userinfo юзера если он авторизован или false
global $_AUTH;
$_AUTH = Auth::init()->user;

$controller = controller();
$action 	= action();

// layout по умолчанию для всех страниц
if( mb_substr ($controller,0,5) == 'admin'){
	
	layout('admin');
	
	if($_AUTH && $_AUTH['admin'] != 1) exit('Access denied!');
	
}else{
	layout('default');
}

// Общая переменная для удобства - в неё например титлы страниц заносятся
global $_PAGE; 

$_PAGE = array(
	 'title' => 'title'
	,'description'=>'description'
	,'keywords'=>'keywords'
	,'metas'=>''
);


// Мета для соц сетей
$_PAGE["metas"] = '
<link rel="image_src" href="http://'.$_SERVER['SERVER_NAME'].'/img/apple-touch-icon-144-precomposed.png" />
<meta property="og:image" content="http://'.$_SERVER['SERVER_NAME'].'/img/apple-touch-icon-144-precomposed.png" />			
';


// Last modified
$timestmp = gmdate("D, d M Y H:i:s", (time() - 60*60*3) )." GMT";  
header('Last-Modified:' .$timestmp);



