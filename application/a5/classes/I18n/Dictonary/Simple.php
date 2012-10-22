<?php
/* Стандартный простой класс для поиска переводов.
 * Все данные и переводы храняться в обычных php-файлах и располагаются по указанным вами путям.
 * Каждое имя файла должно начинаться с имени языка перевод для которого в нём располагается.
 * И может быть снабжено дополнительным суффиксом, например для разделения на разные логические части.
 * Каждый файл может содержать определение одного или нескольких массивов, в которых ключём является
 * идентификатор строки для перевода, а значением собственно сам её перевод.
 * Все массивы комбинируются в единый массив данных стандартным array_merge, так что каждый
 * последующий ключ перезатирает предыдущий (если таковой уже имеется).
 * Файлы подгружаются в алфавитном порядке в порядке обхода указанных путей.
 *
 * Пример использования данного словаря:
 *
 * I18n::insert(new I18n_Dictonary_Simple(APP_DIR . "/locales"));
 *
 * В папке APP_DIR . /locales к примеру можно расположить такие файлы для русского языка
 *
 * locales/ru.php
 * locales/ru.formats.php
 *
 * К примеру в файле locales/ru.php можно разместить следующие данные
 * $table = array
 * (
 * 		"Hello, world" => "Привет, мир",
 * 		"Goodbye" => "До свидания",
 * );
 *
 * В файле locales/ru.formats.php можно разместить следующие данные
 * $table = array
 * (
 * 		"date.month.full_names" => array(null, "Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"),
 * 		"date.month.abbr_names" => array(null, "Янв", "Фев", "Мар", "Апр", "Май", "Июн", "Июл", "Авг", "Сен", "Окт", "Ноя", "Дек"),
 * );
 */
class I18n_Dictonary_Simple extends I18n_Dictonary
{
	// Пути поиска локалей
	private $locale_pathes = array();
	private $translates = array();
	private $translates_loaded_for = array();

	// При инициализации указывается путь (пути) по которым будет производится поиск переводов
	function __construct($locale_pathes)
	{
		if (!is_array($locale_pathes)) { $locale_pathes = array($locale_pathes); }
		$this->locale_pathes = $locale_pathes;
	}

	function get($msg_id, $lang, $module_name = null)
	{
		if (!array_key_exists($lang, $this->translates_loaded_for))
		{
			$this->load_dictonaries($lang);
			$this->translates_loaded_for[$lang] = true;
		}
		if (array_key_exists($lang, $this->translates))
		{ $msg_id = array_key_exists($msg_id, $this->translates[$lang]) ? $this->translates[$lang][$msg_id] : false; }
		return $msg_id;
	}

	// Загрузка словарей для указанного языка
	private function load_dictonaries($lang)
	{
		$this->translates[$lang] = array();
		foreach ($this->locale_pathes as $path)
		{
			$files = @glob($path . "/" . $lang . "*.php");
			if ($files === false) { throw_error("Cannot load dictonaries from '" . $path . "': " . @$php_errormsg); }
			else
			{
				foreach ($files as $file)
				{ $this->translates[$lang] = array_merge($this->translates[$lang], $this->load_dictonary($file)); }
			}
		}
	}

	private function load_dictonary()
	{
		require(func_get_arg(0));
		$loaded_vars = get_defined_vars();
		$dictonary = array();
		foreach ($loaded_vars as $name => $dict)
		{
			if (is_array($dict))
			{ $dictonary = array_merge($dictonary, $dict); }
		}
		return $dictonary;
	}
}