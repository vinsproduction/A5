<?php
// Если определена пользовательская функция сохранения данных - используем её
// Данная функция должна производить действия для сохранения объекта
// Все данные она должна брать из массива $_POST
// ВНИМАНИЕ! В массиве $_POST содержаться не только данные о полях типа, но и другие
// общие системные поля, такие как lang, url_id, system_name, is_hidden и прочие
// Поэтому пользовательская функция должна учитывать этот факт и сохранять изменения
// с учётом данных полей (сохранять их значения тоже), предпочтительно для этой цели
// конечно же использовать стандартную функцию set_node_data.
// Функция первым параметром принимает тип текущего действия, т.е. "add" или "mod"
// и на основе этого должна проводить сохранение данных.
// Функция ДОЛЖНА возвращать id ноды которую она добавила (если это была операция добавления)

$is_user_defined_save_function = function_exists("cms_set_node_data");
$all_languages = cms_get_all_languages();

// Запускаем функцию сохранения на всех языках если:
// - этого просят
// - объект мультиязычный
// - языков более чем один
$is_multi_langs_save = ($_CMS["save_all_langs"] && db_select_cell("SELECT is_lang FROM cms_node_types WHERE type = ?", $_POST["type"]) && count($all_languages) > 1);

// Если сохранение будет происходить стандартными средствами
// убирём из массива данных те поля, которые являются обязательными, но не переданы или переданы пустыми
// и при этом имеют дефолт-значение
if (!$is_user_defined_save_function)
{
	$node_fields = db_select_col("SELECT field_name FROM cms_metadata WHERE type = ? AND is_req = 1 AND default_value IS NOT NULL", $_POST["type"]);
	foreach ($node_fields as $field_name)
	{
		if (@is_empty($_POST[$field_name]))
		{ unset($_POST[$field_name]); }
	}
}

if ($_CMS["do"] == "add")
{
	// При добавлении если url_id передали и оно пустое значение - делаем его null для автогенерации
	if (array_key_exists("url_id", $_POST) && is_empty($_POST["url_id"])) { $_POST["url_id"] = null; }
	$_POST["id"] = $is_user_defined_save_function ? cms_set_node_data($_CMS["do"]) : set_node_data($_POST);
	if ($is_user_defined_save_function && $_POST["id"] === null && DEBUG_MODE) { db_run_error("Функция cms_set_node_data() не вернула значение - проверьте!"); }
}
else
{
	if ($is_user_defined_save_function)
	{ cms_set_node_data($_CMS["do"]); } else { set_node_data($_POST); }
}

if ($is_multi_langs_save)
{
	$prev_do = $_CMS["do"];

	// Т.к. это мультисохранение - проверим что операции происходит при добавлении и если $_POST["url_id"] === null
	// Это означает что url_id было автоматом сгенерировано - поэтому убираем его из массива, дабы не перезатереть сохранённое значение
	if ($prev_do == "add" && array_key_exists("url_id", $_POST) && $_POST["url_id"] === null) { unset($_POST["url_id"]); }

	$prev_lang = $_POST["lang"];
	foreach ($all_languages as $lang => $info)
	{
		if ($prev_lang != $lang)
		{
			$_POST["lang"] = $lang;
			$_CMS["do"] = "mod";
			if ($is_user_defined_save_function) { cms_set_node_data($_CMS["do"]); } else { set_node_data($_POST); }
		}
	}
	$_CMS["do"] = $prev_do;
	$_POST["lang"] = $prev_lang;
}