<?

if( action() == 'index' ){

	if($_AUTH) {
		
		$content = ('<a href="/auth/logout">Logout</a>');
	}else {	
		$content = ('<a href="/auth/login">Login</a><hr><a href="/auth/registration">Registration</a><hr><a href="/auth/reminder">Reminder</a>');
	}
	
	render_content($content);
}

if( action() == 'login' ){

	$Form = new FormBuilder('login');

	$Form->label('Email');
	$Form->input('email')->value(@$_POST['email'])->rule('notempty');
	
	$Form->label('Пароль');
	$Form->input('pass')->value(@$_POST['pass'])->rule('notempty');
	
	$Form->buttonSave()->value('Войти')->hasClass('btn btn-primary');

	$Form->freeField('reminder','<a href="/auth/reminder">Напомнить пароль</a>');
	
	if( $Form->submit() ){
	
		$login = Auth::login($_POST['email'],$_POST['pass']);
	
		if($login->error) {
			$errors = array($login->error); 
		}else{
			redirect_to('/');
		}
	
	}
	
	render_view('/helpers/form');

}

if( action() == 'logout' ){

	Auth::logout();
	
	redirect_to('/');

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
	
	$Form->buttonSave()->value('Зарегистрироваться')->hasClass('btn btn-primary');
	
	if( $Form->submit() ){
	
	
		$login = Auth::registration($_POST['email'],$_POST['pass']);
	
		if($login->error) {
			$errors = array($login->error); 
		}else{
			redirect_to('/auth/reg_done');
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
			redirect_to('/auth/confirm_done');
		}
	}
	
	exit($errors[0]);
	
}

// Напоминание пароля
if( action() == 'reminder' ){


	$Form = new FormBuilder('reminder');

	$Form->label('Введите ваш Email');
	$Form->input('email')->value(@$_POST['email'])->rule('email');
	
	$Form->buttonSave()->value('Напомнить пароль')->hasClass('btn btn-primary');
	
	if( $Form->submit() ){
	
		$reminder = Auth::reminder($_POST['email']);
		
		if($reminder->error) {
			$errors = array($login->error); 
		}else{
			redirect_to('/auth/rem_done');
		}
	}
	
	render_view('/helpers/form');
}

if( action() == 'confirm_done' ){

	render_content('Аккаунт успешно подтвержден');
}

if( action() == 'rem_done' ){

	render_content('На указанный email отправлен пароль от аккаунта');
}

if( action() == 'log_done' ){

	render_content('<a href="/auth/logout">Logout</a>');
}

if( action() == 'reg_done' ){

	render_content('На ваш email отправлено письмо активации аккаунта');
}