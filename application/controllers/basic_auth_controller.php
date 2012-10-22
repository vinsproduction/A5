<?
/*

	Рабочая версия AUTH controller, но не заточенная под нужный шаблон 

	Лучше использовать в качестве примера или backdoor,
	т.к здесь отсутсвуют вьюхи и нет никакой стилизации
*/


layout(false);

if( action() == 'index' ){

	if($_AUTH) {
		varexp($_AUTH,'noexit'); 
		exit('<a href="/basic_auth/logout">Logout</a>');
	}else {	
		exit('<a href="/basic_auth/login">Login</a><hr><a href="/basic_auth/registration">Registration</a><hr><a href="/basic_auth/reminder">Reminder</a>');
	}		
}

if( action() == 'login' ){

	$Form = new FormBuilder('login');

	$Form->label('Email');
	$Form->input('email')->value(@$_POST['email'])->rule('notempty');
	
	$Form->label('Пароль');
	$Form->input('pass')->value(@$_POST['pass'])->rule('notempty');
	
	$Form->buttonSave()->value('Войти');

	
	if( $Form->submit() ){
	
		$login = Auth::login($_POST['email'],$_POST['pass']);
	
		if($login->error) {
			$errors = array($login->error); 
		}else{
			redirect_to('/basic_auth/log_done');
		}
	
	}
	
	render_view('/helpers/form');

}

if( action() == 'logout' ){

	Auth::logout();
	
	redirect_to('/auth');

}


if( action() == 'registration' ){

	$Form = new FormBuilder('login');

	$Form->label('Email');
	$Form->input('email')->value(@$_POST['email'])
		->rule('notempty')
		->rule('email');
	
	$Form->label('Пароль');
	$Form->input('pass')->value(@$_POST['pass'])
		->rule('password')
		->rule('min=3,max=10');
	
	$Form->buttonSave()->value('Зарегистрироваться');
	
	if( $Form->submit() ){
	
		$login = Auth::registration($_POST['email'],$_POST['pass']);
	
		if($login->error) {
			$errors = array($login->error); 
		}else{
			redirect_to('/basic_auth/reg_done');
		}
	
	}
	
	render_view('/helpers/form');

}

// Подтверждение регистрации
if( action() == 'confirm' ){

	if( !isset($_GET['key']) || empty($_GET['key']) ){
	
		$errors = array('Ну и где ключ?!'); 
		
	}else{
	
		$confirm = Auth::confirm($_GET['key']);
	
		if($confirm->error) {
		
			$errors = array($confirm->error);
			
		}else{
			redirect_to('/basic_auth/confirm_done');
		}
	}
	
	exit($errors[0]);
	
}

// Напоминание пароля
if( action() == 'reminder' ){


	$Form = new FormBuilder('reminder');

	$Form->label('email');
	$Form->input('email')->value(@$_POST['email'])->rule('email');
	
	if( $Form->submit() ){
	
		$reminder = Auth::reminder($_POST['email']);
		
		if($reminder->error) {
			$errors = array($login->error); 
		}else{
			redirect_to('/basic_auth/rem_done');
		}
	}
	
	render_view('/helpers/form');
}

if( action() == 'confirm_done' ){

	exit('Аккаунт успешно подтвержден'); 
}

if( action() == 'rem_done' ){

	exit('На указанный email отправлен пароль от аккаунта'); 
}

if( action() == 'log_done' ){

	varexp($_AUTH,'noexit'); 

	exit('<a href="/basic_auth/logout">Logout</a>');
}

if( action() == 'reg_done' ){

	exit('На ваш email отправлено письмо активации аккаунта'); 
}