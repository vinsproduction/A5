<?php
// Определяем константы
define("CMS_URL_MARKER_PREFIX", "[[==");
define("CMS_URL_MARKER_SUFFIX", "==]]");
define("CMS_ID_SYSNAME_REGEX", "(?:[0-9]+|[a-zA-Z][0-9a-zA-Z:\._-]*)");
define("CMS_SYSNAME_REGEX", "[a-zA-Z][0-9a-zA-Z:\._-]*");

if (!defined("CMS_MEDIA_DIR")) { define("CMS_MEDIA_DIR", normalize_path(PUBLIC_DIR . "/data/media")); }
elseif (normalize_path(CMS_MEDIA_DIR) != CMS_MEDIA_DIR) { die("<b>Fatal error:</b> CMS_MEDIA_DIR (" . CMS_MEDIA_DIR . ") is not processed with normalize_path() function"); }
elseif (strpos(CMS_MEDIA_DIR, PUBLIC_DIR) !== 0) { die("<b>Fatal error:</b> CMS_MEDIA_DIR (" . CMS_MEDIA_DIR . ") must be inside PUBLIC_DIR (" . PUBLIC_DIR . ")"); }

// Дополнительные типовые папки
if (!defined("CMS_MEDIA_IMAGE_DIR")) { define("CMS_MEDIA_IMAGE_DIR", normalize_path(CMS_MEDIA_DIR . "/images")); }
if (!defined("CMS_MEDIA_FLASH_DIR")) { define("CMS_MEDIA_FLASH_DIR", normalize_path(CMS_MEDIA_DIR . "/flash")); }
if (!defined("CMS_MEDIA_FILES_DIR")) { define("CMS_MEDIA_FILES_DIR", normalize_path(CMS_MEDIA_DIR . "/files")); }

// Путь к папке пользовательских модулей
if (!defined("CMS_MODULES_DIR")) { define("CMS_MODULES_DIR", normalize_path(APP_DIR . "/cms-modules")); }

// Префикс веб-папки в которой размещено содержимое папки public системы управления
if (!defined("CMS_PUBLIC_URI")) { define("CMS_PUBLIC_URI", "cms-data"); }

// Основной префикс контроллеров, например если вы разместили контроллеры в папке controllers/administration -
// то вы должны переопределить данную константу
if (!defined("CMS_PREFIX")) { define("CMS_PREFIX", "cms"); }

// Возвращает основную информацию об объектах с указанными id и/или системными именами
// Для id и имён не найденых в базе возвращается инфа 404-й страницы
function cms_get_objects_by_ids_and_system_names($ids, $sns)
{
	$nodes = db_select_all("
	SELECT
		n.id,
		n.type,
		n.system_name,
		n.url_id,
		n.controller,
		n.action
	FROM
		v_cms_nodes n
	WHERE
		n.id IN (?i)
		OR n.system_name IN (?)
	", $ids, $sns);

	$ids = array_flip($ids);
	$sns = array_flip($sns);

	$fetched_nodes = array();
	foreach ($nodes as $i => $node)
	{
		if (!is_empty($node["system_name"]))
		{
			$fetched_nodes[$node["system_name"]] = $node;
			unset($sns[$node["system_name"]]);
		}
		$fetched_nodes[$node["id"]] = $node;
		unset($ids[$node["id"]]);
	}

	$ids = array_flip($ids);
	$sns = array_flip($sns);

	if (count($ids) || count($sns))
	{
		$unknown = db_select_row("
		SELECT
			n.id,
			n.type,
			n.system_name,
			n.url_id,
			n.controller,
			n.action
		FROM
			v_cms_nodes n
		WHERE
			id = ?i
		", get_404_error_id());

		foreach ($ids as $id) { $fetched_nodes[$id] = $unknown; }
		foreach ($sns as $sn) { $fetched_nodes[$sn] = $unknown; }
	}

    return $fetched_nodes;
}

// Удаляет узел (или узлы если передали массив) из дерева документов
// Если всё прошло успешно возвращает true иначе возвращает false
// Если вернулся false то сообщение об ошибке можно получить из $GLOBALS["php_errormsg"]
function cms_delete_nodes($nodes)
{
	if (!is_array($nodes)) { $nodes = array($nodes); }
	if (false === $result = @db_delete("cms_nodes", array("id IN (?i)" => $nodes)))
	{ $GLOBALS["php_errormsg"] = db_last_error(true); }
	return $result;
}

// Устанавливаем текущий режим видимости
// 0 - вьюшки возвращают ВСЕ объекты
// 1 - вьюшки возвращают только НЕ скрытые объекты
function cms_set_view_mode($mode = 0)
{
	// Устанавливаем и кэшируем
	$GLOBALS["__cms__"]["current_view_mode"] = db_select_cell("SELECT cms_set_view_mode(?i)", $mode);
	A5::cache_context(cms_get_view_mode(), @$GLOBALS["__cms__"]["current_language"]);
	return $GLOBALS["__cms__"]["current_view_mode"];
}

function cms_get_view_mode()
{
	if (isset($GLOBALS["__cms__"]["current_view_mode"])) { return $GLOBALS["__cms__"]["current_view_mode"]; }
	return $GLOBALS["__cms__"]["current_view_mode"] = db_select_cell("SELECT cms_get_view_mode()");
}

// Устанавливаем текущий язык и кэшируем значение
function cms_set_language($lang)
{
	$GLOBALS["__cms__"]["current_language"] = db_select_cell("SELECT cms_set_current_language(?)", $lang);
	A5::cache_context(@$GLOBALS["__cms__"]["current_view_mode"], cms_get_language());
	return $GLOBALS["__cms__"]["current_language"];
}

function cms_get_language()
{
	if (isset($GLOBALS["__cms__"]["current_language"])) { return $GLOBALS["__cms__"]["current_language"]; }
	return $GLOBALS["__cms__"]["current_language"] = db_select_cell("SELECT cms_get_current_language()");
}

function cms_get_language_info($key = null)
{
	$langs = cms_get_all_languages();
	if ($key === null) { return $langs[cms_get_language()]; }
	else { return $langs[cms_get_language()][$key]; }
}

function cms_is_default_language($lang)
{
	static $langs;
	if (isset($langs[$lang])) { return $langs[$lang]; }
	$langs[$lang] = db_select_cell("SELECT lang FROM cms_languages WHERE lang = ? AND is_default = 1", $lang);
	return $langs[$lang];
}

function cms_get_default_language()
{
	foreach (cms_get_all_languages() as $lang)
	{
		if (cms_is_default_language($lang["lang"]))
		{ return $lang; }
	}
	return null;
}

function cms_is_existing_language($lang)
{
	static $langs;
	if (isset($langs[$lang])) { return $langs[$lang]; }
	$langs[$lang] = db_select_cell("SELECT lang FROM cms_languages WHERE lang = ?", $lang);
	return $langs[$lang];
}

// Получает все языки сайта и кэширует
function cms_get_all_languages()
{
	if (isset($GLOBALS["__cms__"]["project_languages"])) { return $GLOBALS["__cms__"]["project_languages"]; }
	return $GLOBALS["__cms__"]["project_languages"] = db_select_all("
	SELECT
		lang,
		name,
		is_default
	FROM
		cms_languages
	", array("@key" => "lang"));
}

// Данная функция должна вызыватся перед самым выводом ХТМЛ-текста
// Задача функции заменить все маркеры УРЛ на реальный УРЛ
function cms_url_replace($text)
{
    // Выделяем все ID и SYSTEM_NAME (а также возможно параметры).
    if (!preg_match_all("/" . preg_quote(CMS_URL_MARKER_PREFIX, "/") . "(" . CMS_ID_SYSNAME_REGEX . ")(\|[^\s]+)?" . preg_quote(CMS_URL_MARKER_SUFFIX, "/") . "/su", $text, $matches, PREG_SET_ORDER)) { return $text; }

    // Определяем уникальные ID и SYSTEM_NAME
	$ids = $system_names = array();
	foreach ($matches as $m)
	{
		if (is_numeric($m[1])) { if (!isset($ids[$m[1]])) $ids[$m[1]] = $m[0]; }
		elseif (!isset($system_names[$m[1]])) { $system_names[$m[1]] = $m[0]; }
	}

	$ids = array_flip($ids);
	$system_names = array_flip($system_names);

    $nodes = cms_get_objects_by_ids_and_system_names($ids, $system_names);
    if (!count($nodes)) { return $text; }

	// Специальная функция для уточнения параметров урл по id ноды
	// На вход должна принимать первым параметром - тип нод,
	// вторым параметром - массив нод, массив представляет собой набор записей
	// где ключ массива - id ноды в базе - значение - массив (список полей данной ноды).
	// функция должна возвращать аналогичный массив, но для каждой ноды нужно
	// добавить ключ "url_params" - это доп.параметры для формирования url_for().
	// не обязательно должна возвращать все ноды переданные на входе, те которые будут
	// отсуствовать будут формироваться стандартным способом.
	if (!function_exists("cms_url_mapping"))
	{
		// Минимальный вид данной функции
		function cms_url_mapping($node_type, $nodes)
		{
			$disable_id_for = array("root", "error404");
			foreach ($nodes as $i => $item)	{ if (in_array($item["system_name"], $disable_id_for)) $nodes[$i]["url_params"]["id"] = null; }
			return $nodes;
		}
	}

	$nodes_by_type = array();
	foreach ($nodes as $i => $item)
	{
		if (!array_key_exists($item["type"], $nodes_by_type) || !is_array($nodes_by_type[$item["type"]])) { $nodes_by_type[$item["type"]] = array(); }
		$nodes_by_type[$item["type"]][$item["id"]] = $nodes[$i];
	}

	foreach ($nodes_by_type as $node_type => $nodes_list)
	{
		$nodes_list = @cms_url_mapping($node_type, $nodes_list);
		if (is_array($nodes_list))
		{
			foreach ($nodes as $i => $item)
			{
				if (array_key_exists($item["id"], $nodes_list))
				{ $nodes[$i] = array_merge($item, $nodes_list[$item["id"]]); }
			}
		}
	}

    $froms = array(); $tos = array();
    foreach ($matches as $m)
    {
    	$node = $nodes[$m[1]];
		$froms[] = $m[0];
		$params = array("controller" => $node["controller"], "action" => $node["action"], "id" => $node["url_id"]);
		if (array_key_exists("url_params", $node) && is_array($node["url_params"])) { $params = array_merge($params, $node["url_params"]); }
		if (isset($m[2])) { $params = array_merge($params, unserialize(base64_decode(substr($m[2], 1)))); }
		$url = url_for($params);
		$tos[] = $url;
    }

    // Заменяем маркеры на реальные URL.
    $text = str_replace($froms, $tos, $text);

    return $text;
}
function cms_ob_url_replace($buffer) { return in_array(A5::output_type(), array("html", "xml", "javascript")) ? cms_url_replace($buffer) : $buffer; }

// Функция добавляет в базу новую строку для перевода и возвращает её
function cms_add_string_constant($str)
{
	$langs = cms_get_all_languages();
	$fields = array("name" => $str, "parent_id" => get_id_by_system_name("string_constants"), "type" => "string_constants");
	foreach ($langs as $lang => $info)
	{
		$fields["lang"] = $lang;
		$fields["value"] = $str;
		$fields["id"] = set_node_data($fields);
	}
	return $str;
}

// Функция получающая на вход произвольный текст и заменяющая все конструкции вида <str>text</str> на их перевод
// с учётом текущего языка
function cms_translate_strings($text)
{
    // Выделяем все <str></str>
    if (!preg_match_all('{ (<|&lt;) (str) (:[a-z_]+)* (>|&gt;) (.*?) (\\1) (/str) (\\4) }sixu', $text, $matches, PREG_SET_ORDER)) { return $text; }

	// Составляем список всех уникальных маркеров на странице
	$chunks = array();
	foreach ($matches as $m) { $chunks[$m[0]] = $m; }

	// Составляем список всех уникальных идентификаторов
	$identifiers = array();
	foreach ($chunks as $i => $chunk)
	{
		$chunks[$i][5] = ltrim_lines($chunk[5]);
		$identifiers[$chunks[$i][5]] = null;
	}

	// Добавляем переводы к известным идентификаторам
	$identifiers = array_merge($identifiers, db_select_col("
	SELECT
		name,
		value
	FROM
		v_string_constants
	WHERE
		name IN (?)
	",
	array_keys($identifiers),
	array("@key" => "name", "@column" => "value")
	));

	$langs = cms_get_all_languages();
	foreach ($identifiers as $ident => $value)
	{
		// Если для данного параметра в базе нет значения - добавляем
		if ($value === null) { $identifiers[$ident] = cms_add_string_constant($ident); }
	}

	// Теперь заполняем массивы для перевода всей текстовой строки
	$from = array(); $to = array();
	foreach ($chunks as $chunk => $item)
	{
		$from[] = $chunk;
		$flags = array();
		if ($item[3]) { $flags = array_flip(explode(":", trim($item[3], ":"))); }
		$replacement = $identifiers[$item[5]];
		if (!isset($flags["html"])) { $replacement = h($replacement); }
		$to[] = $replacement;
	}

    // Заменяем маркеры на реальные строки.
    $text = str_replace($from, $to, $text);
    return $text;
}
function cms_ob_translate_strings($buffer) { return in_array(A5::output_type(), array("html", "xml")) ? cms_translate_strings($buffer) : $buffer; }

function cms_ob_html_handlers($buffer)
{
	$buffer = cms_ob_url_replace($buffer);
	$buffer = cms_ob_translate_strings($buffer);
	return $buffer;
}

// Функция транслирования русских букв в английские
function cms_normalize_file_name($file_name)
{
	$file_name = mb_strtolower($file_name);
	$file_name = strtr($file_name, array
	(
		"а" => "a", "б" => "b", "в" => "v", "г" => "g", "д" => "d", "е" => "e", "з" => "z", "и" => "i", "й" => "j", "к" => "k",
		"л" => "l", "м" => "m", "н" => "n", "о" => "o", "п" => "p", "р" => "r", "с" => "s", "т" => "t", "у" => "u", "ф" => "f",
		"х" => "h", "ц" => "c", "ы" => "y", "э" => "e", "ё" => "yo", "ж" => "zh", "ч" => "ch", "ш" => "sh", "щ" => "shc",
		"ъ" => "", "ь" => "", "ю" => "yu", "я" => "ya", " " => "_"
	));
	$file_name = preg_replace("/[^a-zA-Z0-9_\.-]/su", "_", $file_name);
	$file_name = preg_replace("/_{2,}/su", "_", $file_name);
	$file_name = preg_replace("/-{2,}/su", "-", $file_name);
	$file_name = preg_replace("/[_-]{2,}/su", "-", $file_name);
	$file_name = preg_replace("/\.{2,}/su", ".", $file_name);
	$file_name = preg_replace("/^[_-]+/su", "", $file_name);
	$file_name = preg_replace("/[_-]+$/su", "", $file_name);
	$file_name = preg_replace("/[_-]+(\.|$)/su", "$1", $file_name);
	return $file_name;
}

function cms_construct_url_id($name)
{
	$url_id = cms_normalize_file_name($name);
	$url_id = str_replace("_", "-", $url_id);
	$url_id = str_replace(".", "-", $url_id);
	$url_id = preg_replace("/[-]{2,}/u", "-", $url_id);
	$url_id = preg_replace("/^[\d-]+/u", "", $url_id);
	$url_id = rtrim($url_id, "-");
	$url_id = rtrim(substr($url_id, 0, 128), "-");
	return $url_id;
}

// Возвращает массив с информацией о полях определённого типа
function cms_get_type_fields($type, $is_cache = false)
{
	// Получаем список полей для данного типа ноды, кэшируя их
	static $cache = array();
	if (!$is_cache || !array_key_exists($type, $cache))
	{ $cache[$type] = db_select_all("SELECT * FROM cms_metadata WHERE type = ? ORDER BY id", $type, array("@key" => "field_name")); }
	return $cache[$type];
}

/*
Функция создания/модифицирования объекта
На вход функция берёт один параметр - массив.
Ключи массива - имена полей, значения - значения полей.
Поля которые не будут переданы - обновлены не будут.
При создании объекта обязательно нужно передать поля "parent_id" и "type".
При модификицаии объекта достаточно передать только "id"
*/
function set_node_data($node)
{
	$is_new_node = false;
	if (!is_array($node)) { $node = array(); }

	// Добавление-ли это новой ноды?
	if (!array_key_exists("id", $node))
	{
		if (!array_key_exists("parent_id", $node)) { db_run_error("Вы должны указать родительский документ"); return false; }
		if (!array_key_exists("type", $node)) { db_run_error("Вы должны указать тип документа"); return false; }
		$is_new_node = true;
	}

	$node_info = array();

	// Получаем общую информацию о типах, полезно при массивных операциях insert или update
	static $types_info = array();
	if (!$types_info) { $types_info = db_select_all("SELECT type, is_lang, tree_name FROM cms_node_types", array("@key" => "type")); }

	db_begin();

	if ($is_new_node)
	{
		$node_info["id"] = db_select_cell("SELECT nextval('cms_nodes_id_seq')");
		$node_info["parent_id"] = $node["parent_id"];
		$node_info["type"] = $node["type"];
	}
	else
	{
		$row = db_select_row("SELECT id, parent_id, type FROM cms_nodes WHERE id = ?i", $node["id"]);
		if (false === $row) { db_run_error("Не найден документ с id: " . $node["id"]); return false; }
		$node_info["id"] = $row["id"];
		$node_info["parent_id"] = $row["parent_id"];
		$node_info["type"] = $row["type"];
	}

	$node_info["tree_name"] = $types_info[$node_info["type"]]["tree_name"];
	$node_info["is_lang"] = $types_info[$node_info["type"]]["is_lang"];

	// Получаем список полей для данного типа ноды кэшируя
	$node_fields = cms_get_type_fields($node_info["type"], true);

	// Берём шаблон для общего имени ноды чтобы затем заполнить его
	$node_tree_name = $node_info["tree_name"];
	// Поле id всегда известно
	$node_tree_name = str_replace('{id}', $node_info["id"], $node_tree_name);
	// Пробегаемся по всем полям, чтобы определить имя ноды общее (для дерева документов)
	foreach ($node_fields as $item)
	{
		if (array_key_exists($item["field_name"], $node))
		{
			$value = $node[$item["field_name"]];
			if ($item["field_type"] == "plain_text" || $item["field_type"] == "html") { $value = text2string($value); }
			if ($item["field_type"] == "file") { $value = basename($value); }
			if ($item["field_type"] == "date") { $value = Date::format($value, "d.m.Y"); }
			if ($item["field_type"] == "datetime") { $value = Date::format($value, "d.m.Y H:i:s"); }
			if ($item["field_type"] == "object") { $value = db_select_cell("SELECT name FROM v_cms_nodes WHERE id = ?i", $value); }
			// Заменяем в tree_name имя поля, на его значение
			$node_tree_name = str_replace('{' . $item["field_name"] . '}', $value, $node_tree_name);
		}
	}
	// Если не удалось заменить все поля, значит это поле обновлять не будем
	if (preg_match("/\{[a-z0-9_]+\}/u", $node_tree_name)) { unset($node_tree_name); }

	// Если не указан язык обновляемого документа - берётся текущий
	if (!array_key_exists("lang", $node)) { $node_info["lang"] = $node_info["is_lang"] ? cms_get_language() : null; }
	else { $node_info["lang"] = $node_info["is_lang"] ? $node["lang"] : null; }

	$fields = array();

	if ($is_new_node) { $fields["id"] = $node_info["id"]; }
	if (array_key_exists("is_menuitem", $node)) { $fields["is_menuitem"] = $node["is_menuitem"]; }
	if (array_key_exists("url_id", $node)) { $fields["url_id"] = $node["url_id"]; }
	if (array_key_exists("parent_id", $node)) { $fields["parent_id"] = $node["parent_id"]; }
	if (array_key_exists("type", $node)) { $fields["type"] = $node["type"]; }
	if (array_key_exists("system_name", $node)) { $fields["system_name"] = $node["system_name"]; }
	if (array_key_exists("controller", $node)) { $fields["controller"] = $node["controller"]; }
	if (array_key_exists("action", $node)) { $fields["action"] = $node["action"]; }

	// Если это создание новой записи и имеется имя для дерева
	// и url_id передали как null - генерим автоматически, если не передали - значит генерить не нужно
	if ($is_new_node && isset($node_tree_name) && array_key_exists("url_id", $node) && $node["url_id"] === null)
	{
		$url_id = cms_construct_url_id($node_tree_name);
		if (!is_empty($url_id))
		{
			$url_idx = 0;
			while (false !== db_select_cell("SELECT id FROM cms_nodes WHERE type = ? AND url_id = ?", $node_info["type"], $url_id))
			{
				if ($url_idx) { $url_id = preg_replace("/-\d+$/u", "", $url_id); }
				$url_id .= "-" . ++$url_idx;
			}
			$fields["url_id"] = $url_id;
		}
	}

	// Если вставляется новая запись
	if ($is_new_node) { if (false === db_insert("cms_nodes", $fields)) return false; }
	// Иначе - если есть хотя бы одно поле, которое нужно обновить
	elseif (count($fields)) { db_update("cms_nodes", $fields, array("id" => $node_info["id"])); }

	$fields = array();

	if ($is_new_node) { $is_new_record = true; }
	else { $is_new_record = (false === db_select_cell("SELECT id FROM cms_node_extras", array("id = ?i" => $node_info["id"], "lang" => $node_info["lang"]))); }

	// Если новая запись - должны быть добавлены доп.поля
	if ($is_new_record)
	{
		$fields["id"] = $node_info["id"];
		$fields["lang"] = $node_info["lang"];
	}

	if (isset($node_tree_name)) { $fields["name"] = $node_tree_name; }
	if (array_key_exists("is_hidden", $node)) { $fields["is_hidden"] = $node["is_hidden"]; }
	if (array_key_exists("title", $node)) { $fields["title"] = $node["title"]; }
	if (array_key_exists("meta_keywords", $node)) { $fields["meta_keywords"] = $node["meta_keywords"]; }
	if (array_key_exists("meta_description", $node)) { $fields["meta_description"] = $node["meta_description"]; }

	// Если есть что обновлять в доп.полях
	if (count($fields))
	{
		if ($is_new_record) { db_insert("cms_node_extras", $fields); }
		else { db_update("cms_node_extras", $fields, array("id" => $node_info["id"], "lang" => $node_info["lang"])); }

		// Имя ноды для дерева документов нужно добавить для всех языков где его нет
		if (isset($node_tree_name) && $node_info["is_lang"])
		{
			$insert_langs = array_keys(cms_get_all_languages());
			foreach ($insert_langs as $lang)
			{
				if ($lang != $node_info["lang"])
				{
					if ($is_new_node || false === db_select_cell("SELECT id FROM cms_node_extras", array("id = ?i" => $node_info["id"], "lang" => $lang)))
					{ db_insert("cms_node_extras", array("id" => $node_info["id"], "lang" => $lang, "name" => $node_tree_name)); }
				}
			}
		}
	}

	// Обновление специфичных для типа полей
	$fields = array();

	if ($is_new_node) { $is_new_record = true; }
	else { $is_new_record = (false === db_select_cell("SELECT id FROM " . db_escape_ident($node_info["type"]), array("id = ?i" => $node_info["id"], "lang" => $node_info["lang"]))); }

	// Если это новая нода - или такой записи ещё нет
	if ($is_new_record)
	{
		$fields["id"] = $node_info["id"];
		$fields["lang"] = $node_info["lang"];
	}

	foreach ($node_fields as $item)
	{
		// Если данное поле передано на обновление
		if (array_key_exists($item["field_name"], $node))
		{ $fields[$item["field_name"]] = $node[$item["field_name"]]; }
	}

	// Если есть что обновлять
	if (count($fields))
	{
		// Если это новая нода или такой записи ещё нет - вставляем запись
		if ($is_new_record) { db_insert($node_info["type"], $fields); }
		else { db_update($node_info["type"], $fields, array("id" => $node_info["id"], "lang" => $node_info["lang"])); }
	}

	db_commit();
	return $node_info["id"];
}

// Возвращает id объекта по его системному имени.
// Результат кэшируется, если такой объект не найден - возвращается 404-й
function get_id_by_system_name($sn)
{
	static $names = array();
	if (@!array_key_exists($sn, $names))
	{
		if (is_numeric($sn)) { $names[$sn] = db_select_cell("SELECT id FROM v_cms_nodes WHERE id = ?i", $sn); }
		else { $names[$sn] = db_select_cell("SELECT id FROM v_cms_nodes WHERE system_name = ?", $sn); }
	}
    return $names[$sn];
}

// Функция возвращает id - 404 страницы
function get_404_error_id() { return get_id_by_system_name('error404'); }

// Возвращает общее название объекта (страницы) по её системному имени
function get_name_by_system_name($sn)
{
	static $node_names = array();
	if (!isset($node_names[cms_get_language()][$sn]))
	{ $node_names[cms_get_language()][$sn] = db_select_cell("SELECT name FROM v_cms_nodes WHERE system_name = ?", $sn); }
	return $node_names[cms_get_language()][$sn];
}

// Возвращает id объекта по его url_id - на вход: url_id, type (тип объекта)
function get_id_by_url_id($url_id, $type)
{
	static $node_ids = array();
	if (!isset($node_ids[$type][$url_id]))
	{ $node_ids[$type][$url_id] = db_select_cell("SELECT id FROM v_cms_nodes WHERE type = ? AND url_id = ?", $type, $url_id); }
    return $node_ids[$type][$url_id];
}

// Возвращает Параметр БД или false если такого нету
function get_param($name)
{
	static $params = array();
	$lang = cms_get_language();
	if (!array_key_exists($lang, $params))
	{ $params[$lang] = db_select_all("SELECT id, system_name, value FROM v_params", array("@key" => "system_name")); }
	if (@!array_key_exists($name, $params[$lang])) { return false; }
	return $params[$lang][$name]["value"];
}

// string url($id_or_system_name, $param_pairs, ...)
// Возвращает маркер URL по ID или SYSTEM_NAME.
// Если результат подставить на страницу, то он потом автоматически
// заменится на реaльный URL с помощью ob_ обработчика.
// Данную функцию НУЖНО использовать в макетах и шаблонах
// если вы хотите указывать ссылку на какой-нибудь документ в базе
// Опционально - вы можете передать дополнительные параметры для формирования урл
// Они будут переданы в url_for
function url($id)
{
	if (is_scalar($id) && preg_match('/^' . CMS_ID_SYSNAME_REGEX . '$/su', $id))
	{
		$cond = array();
		$cond["@marker"] = (A5::current_context() == "view");
		$args = func_get_args(); $param_str = null;
		if (count($args) > 1)
		{
			if (count($args) == 2 && is_array($args[1])) { $params = $args[1]; } else { $params = list2hash(array_slice($args, 1)); }
			if (array_key_exists("@marker", $params)) { $cond["@marker"] = $params["@marker"]; unset($params["@marker"]); }
			$params = array_merge(A5::url_for_defaults(), $params);
			$param_str = base64_encode(serialize($params));
		}
		$url_marker = CMS_URL_MARKER_PREFIX . $id . (!is_empty($param_str) ? "|$param_str" : "") . CMS_URL_MARKER_SUFFIX;
		if ($cond["@marker"]) { return $url_marker; } else { return cms_url_replace($url_marker); }
	}
	else { return null; }
}

// Возвращает перевод указанной строки, если строки в базе нет - создаёт её
// При первом вызове загружает весь список строк из базы для текущего языка
// Нужно быть осторожным с использованием данной функции, так как если строк
// слишком много то это может создать большое потребление памяти
function s($str)
{
	static $translates = array();
	if (!array_key_exists(cms_get_language(), $translates))
	{ $translates[cms_get_language()] = db_select_col("SELECT name, value FROM v_string_constants", array("@key" => "name", "@column" => "value")); }
	$str = ltrim_lines($str);
	if (!array_key_exists($str, $translates[cms_get_language()])) { $translates[cms_get_language()][$str] = cms_add_string_constant($str); }
	return $translates[cms_get_language()][$str];
}