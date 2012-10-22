<?php
// Подготовка для работы с формой

db_add_error_handler('cms_node_form_database_error');

// Выносим поля с префиксом __cms__ в отдельный массив
$_CMS = array();
foreach ($_POST as $key => $val)
{
	if (strpos($key, "__cms__") === 0)
	{ $_CMS[substr($key, 7)] = $val; unset($_POST[$key]); }
}

// Если модифицируем объект и находимся не в DEBUG режиме - тип менять нельзя - вытаскиваем тип из базы
if (@$_CMS["do"] == "mod" && !DEBUG_MODE) { $_POST["type"] = db_select_cell("SELECT type FROM v_cms_nodes WHERE id = ?i", $_POST["id"]); }
elseif (!isset($_POST["type"])) { db_run_error("Вы должны указать тип"); }
elseif (false === $_POST["type"] = db_select_cell("SELECT type FROM cms_node_types WHERE type = ?", $_POST["type"]))
{ db_run_error("Такой тип не существует"); }

// Если просят выводить другой язык - устанавливаем его
if (isset($_POST["lang"])) { cms_set_language($_POST["lang"]); }
$_POST["lang"] = cms_get_language();

$type = db_select_row("SELECT is_shared_fields_disabled, is_additional_tab_disabled, disable_fields, disable_childs FROM cms_node_types WHERE type = ?", $_POST["type"]);
if ($type !== false)
{
	disable_shared_fields($type["is_shared_fields_disabled"]);
	disable_additional_tab($type["is_additional_tab_disabled"]);
	$type["disable_fields"] = preg_split("/\s*,\s*/u", $type["disable_fields"], -1, PREG_SPLIT_NO_EMPTY);
	if (count($type["disable_fields"])) { disable_fields($type["disable_fields"]); }
	$type["disable_childs"] = preg_split("/\s*,\s*/u", $type["disable_childs"], -1, PREG_SPLIT_NO_EMPTY);
	if (count($type["disable_childs"])) { disable_childs($type["disable_childs"]); }
}

// Проверим - есть ли пользовательское определение типа, или системное. Если ничего нет, то используем стандартные
if (defined('CMS_MODULES_DIR'))
{
	$module_user_file = CMS_MODULES_DIR . "/" . $_POST["type"] . ".php";
	$module_system_file = __DIR__ .  "/../modules/" . $_POST["type"] . ".php";
	if (file_exists($module_user_file)) { require($module_user_file); }
	elseif (file_exists($module_system_file)) { require($module_system_file); }
}