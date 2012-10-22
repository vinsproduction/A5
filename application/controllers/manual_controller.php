<?

/* === FRAMEWORK MANUAL ============ */


// Только в дебаг режиме можно просматривать мануал
//if( !DEBUG_MODE ) { header('HTTP/1.1 403 Forbidden'); exit;}

layout('manual');

$uri_1 = isset($_GET['uri_1']) ? $_GET['uri_1'] : false;
$uri_2 = isset($_GET['uri_1']) ? $_GET['uri_1'] : false;
$uri_3 = isset($_GET['uri_1']) ? $_GET['uri_1'] : false;


if(action() == 'index'){
	

	$tables = db_select_cell("SELECT 1 FROM information_schema.tables LIMIT 1");

	render_view('/manual/index');	
}


if(action() == 'helpers'){

	render_view('/manual/helpers');
	
}

if(action() == 'mvc'){

	if($uri_2 == false) { render_view('/manual/mvc/index'); }

	switch($uri_2) {
		
		case 'db': render_view('/manual/mvc/db'); break;
		case 'view': render_view('/manual/mvc/view'); break;
		case 'controller': render_view('/manual/mvc/controller'); break;
	
		default: render_view('/manual/mvc/index');	
	}
	 
}


if(action() == 'classes'){

	if($uri_2 == false) { render_view('/manual/classes/index'); }

	switch($uri_2) {
		
		case 'date': render_view('/manual/classes/date'); break;
		case 'image_class': render_view('/manual/classes/image_class'); break;
		case 'form_builder': render_view('/manual/classes/form_builder'); break;
		case 'cookie': render_view('/manual/classes/cookie'); break;
		case 'session': render_view('/manual/classes/session'); break;
		case 'client': render_view('/manual/classes/client'); break;
		case 'pagination': render_view('/manual/classes/pagination'); break;
		case 'mail_send': render_view('/manual/classes/mail_send'); break;
		
		

		
		
		default: render_view('/manual/classes/index');
	}

	 
}


	