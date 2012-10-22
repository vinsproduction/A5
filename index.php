<?

/*

	Апач должен сразу смотреть в папку htdocs! минуя этот файл
	Для этого в настройках Апача (etc/apache2/apache2.conf), в разделе <VirtualHost 178.250.240 и тд...>
	надо выставить DocumentRoot до htdocs, например /var/www/vins/data/www/vins.podgrib.com/htdocs и всё!
	
	*Апач может ругаться на .htaccess. см в логах	
	Строка: Options -MultiViews +FollowSymLinks
	Чтобы это исправить, в настройках Апача (/etc/apache2/conf.d/secure.conf) надо дописать к options
	Options=All,MultiViews
	
*/
	//Проверка DocumentRoot на правильный путь то htdocs	
	//var_export($_SERVER) 


?>
