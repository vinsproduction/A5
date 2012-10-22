<?

/* ==== Класс авторизации-регистрации ============== */

/*

	Basic структура таблицы Users (mySQL)

	CREATE TABLE IF NOT EXISTS `users` (
	  `id` bigint(20) NOT NULL AUTO_INCREMENT,
	  `pass` varchar(255) COLLATE utf8_bin NOT NULL,
	  `email` varchar(255) COLLATE utf8_bin NOT NULL,
	  `confirm_key` varchar(255) COLLATE utf8_bin NOT NULL,
	  `is_reg` int(1) NOT NULL DEFAULT '0',
	  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	  `is_removed` int(1) NOT NULL DEFAULT '0',
	  `ip` varchar(255) COLLATE utf8_bin NOT NULL,
	  `is_admin` int(1) NOT NULL DEFAULT '0',
	  PRIMARY KEY (`id`)
	) 
	
	// Дамп данных, если надо!
	INSERT INTO `users` (`pass`, `email`, `confirm_key`, `is_reg`, `created`, `is_removed`, `ip`, `is_admin`) VALUES
	('123456', 'mail@gmail.com', 'Yl1972z8A3xc0wqFHPY2w2FfQyip856tAzdanbA9L21nyWvQOt3XFgTxpBTEkzrM', 1, '2012-05-29 16:04:47', 0, '80.251.112.197', 0)

*/

Class Auth {

	public $error	= false;
	public $user  = false;
	
	function __construct(){
	
		$this->User = new User();

		if (isset($_SESSION["auth"]["id"]))
		{
			$this->user = $this->User->infoShort($_SESSION["auth"]["id"]);
		}
	}	
	
	static function init(){ return new Auth(); }
	
	
	static function login($email, $pass){	
	
		$Auth = self::init();
	
		if( $Auth->user ){ 
		
			$Auth->error = 'Вы уже авторизованы';
			
		}else{
	
			$user = db_select_row("SELECT id, ip, is_reg FROM users WHERE lower(email) = lower(?) AND pass = ? AND is_removed = 0", $email, $pass);

			if(!$user){ 
			
				$Auth->error = 'Неправильный логин или пароль';
 				
			}elseif(!$user['is_reg']){
			
				$Auth->error = 'Пользователь не активирован'; 
				
			}else{
			
				$ip = (isset($_SERVER["HTTP_X_REAL_IP"])) ? $_SERVER["HTTP_X_REAL_IP"] : $_SERVER["REMOTE_ADDR"];
				
				if ($user["ip"] != $ip){
					db_update("users", array("ip" => $ip), array("id" => $user["id"]));
				}
				
				$_SESSION["auth"]["id"] = $user['id'];
				
				$Auth->userinfo = $Auth->User->infoShort($user['id']);			
			}
		}

		return $Auth;
	}
	
	static function logout(){
	
		unset($_SESSION["auth"]['id']);

		return 0;
	}
	
	
	
	
	/* ========== REGISTRATION ============ */
	

	static function registration($email, $pass){
		
		$Auth = self::init();
		
		if( $Auth->user ){ 
		
			$Auth->error = 'Вы уже авторизованы';
			
		}else{
		
			if (false !== db_select_cell("SELECT id FROM users WHERE lower(email) = lower(?) and is_removed=0", $_POST["email"]))
			{ 
				$Auth->error = "Такой email уже зарегистрирован"; 
				
			}else{
		
				db_begin();

				$confirm_key = generate_key(64);
				while (false !== db_select_cell("SELECT id FROM users WHERE confirm_key = ? and is_removed=0", $confirm_key))
				{ $confirm_key = generate_key(64); }

				$ip = (isset($_SERVER["HTTP_X_REAL_IP"])) ? $_SERVER["HTTP_X_REAL_IP"] : $_SERVER["REMOTE_ADDR"];

				$fields = array(
								"email"       => $email,
								"pass"    => $pass,								
								"confirm_key" => $confirm_key,
								"ip"          => $ip,
								);

				$user_id = db_insert("users", $fields);

				// Отправаляем письмо для активации
				$mail = new MailSend();
				$mail->from("YE-bay <info@yebay.ru>");
				$mail->to($email);
				$mail->subject("Подтверждение регистрации на сайте yebay.ru");
				$mail->message(ltrim_lines("
						Привет!
						<br><br>
						Кто-то, возможно ты, зарегистрировался на сайте yebay.ru.
						<br><br>
						Для завершения регистрации перейди по ссылке:<br>
						" . url_for("controller", "auth", "action", "confirm", "key", $confirm_key) . "
						<br><br>						
						"), true);
				$mail->send();

				db_commit();
			}
		}
		
		return $Auth;
		
	}
	
	// Подтверждение регистрации
	static function confirm($key){
	
		$Auth = self::init();
	
		// Подтвердить регистрацию может только незарегистрированный пользователь с правильным ключём
		$user = db_select_row("SELECT id, email, pass FROM users WHERE is_reg = 0 and is_removed=0 AND confirm_key = ?", $key);
		
		if ($user === false) { 
		
			$Auth->error = 'Неверный ключ подтверждения'; 
			
		}else{
		
			db_begin();

			db_update("users", array("is_reg" => true), array("id" => $user["id"]));
			
			$_SESSION["auth"]["id"] = $user["id"]; //логинемся сразу

			$mail = new MailSend();
			$mail->from("YE-bay <info@yebay.ru>");
			$mail->subject("Твои регистрационные данные на сайте yebay.ru");
			$mail->to($user["email"]);
			$mail->message(ltrim_lines("
					Добро пожаловать на сайт yebay.ru!
					<br><br>
					Ты успешно прошел регистрацию и подтвердил её.<br>
					Напоминаем твой пароль и логин для доступа к сайту.<br>
					Логин: Твой	текущий email ".$user["email"]."
					<br>
					Пароль: " . $user["pass"] . "
					<br><br>							
					"), true);
			$mail->send();

			db_commit();

		}
		
		return $Auth;

	}
	
	// Напоминание пароля
	static function reminder($email){
		
		$Auth = self::init();
		
		if (false === $user = db_select_row("SELECT id, pass, email, is_reg, confirm_key FROM users WHERE lower(email) = lower(?) and is_removed=0", $email)){
			
			$Auth->error = "Пользователь с таким e-mail незарегистрирован!"; 
		
		}else{
		
			if ($user["is_reg"]){
			
				$mail = new MailSend();
				$mail->from("YE-bay <info@yebay.ru>");
				$mail->subject("Напоминание логина и пароля к сайту yebay.ru");
				$mail->to($user["email"]);
				$mail->message(ltrim_lines("
						Привет!
						<br><br>
						Кто-то, возможно ты, попросил напомнить логин и пароль для доступа к сайту yebay.ru.
						<br><br>
						Логин: Твой email " . $user["email"] . "<br>
						Пароль: " . $user["pass"] . "<br>
						<br><br>
						"), true);
				$mail->send();
				$message = "Ваш логин и пароль выслан на e-mail!";
			
			}else{
			
				$mail = new MailSend();
				$mail->from("YE-bay <info@yebay.ru>");
				$mail->to($user["email"]);
				$mail->subject("Подтверждение регистрации на сайте yebay.ru");
				$mail->message(ltrim_lines("
						Привет!
						<br><br>
						Кто-то, возможно ты, зарегистрировлся на сайте yebay.ru.
						<br><br>
						Для завершения регистрации перейди по ссылке:<br>
						" . url_for("controller", "auth", "action", "confirm", "id", $user["confirm_key"]) . "
						<br><br>							
						"), true);
				$mail->send();
			}
		}
		
		return $Auth;
		
	}
	
	
	
	
}