<?php
// Нужно определить $parent_id для вывода списка возможных добавляемых объектов
$one2many_field = db_select_cell("SELECT one2many_field FROM cms_node_types WHERE type = ?", $_POST["type"]);
$one2many_type = db_select_cell("SELECT select_type FROM cms_metadata WHERE type = ? AND field_name = ?", $_POST["type"], $one2many_field);
$one2many_method = db_select_cell("SELECT select_method FROM cms_metadata WHERE type = ? AND field_name = ?", $_POST["type"], $one2many_field);

if (is_empty($one2many_field)) { db_run_error("Невозможно использовать данный вид формы, не определено имя поля для данного типа объектов!"); }
if ($one2many_type === false) { db_run_error("Невозможно использовать данный вид формы, поле с именем \"" . $one2many_field . "\" не существует для данного типа объектов!"); }

// Обработка формы после постинга данных
if (isset($_CMS["do"]))
{
	if (!cms_is_have_auth_for_type($_POST["type"], CMS_AUTH_CREATE))
	{ db_run_error("У вас нет прав на создание данного типа объектов"); }

	if (isset($_CMS["selected_nodes"]) && is_array($_CMS["selected_nodes"]))
	{
		db_begin();
		$nodes_count = count($_CMS["selected_nodes"]);
		$nodes_current_number = 0;
		foreach ($_CMS["selected_nodes"] as $_POST[$one2many_field])
		{
			require("form-standard-check.php");

			if (!$form->is_form_error()) { require("form-standard-save.php"); }
			else { if (!include("form-standard-error.php")) return 0; }

			// Делаем этот трюк, потому что после добавления ноды заполняеться $_POST["id"]
			// и без этого трюка в следующем проходе цикла будет просто изменяться только что добавленная нода
			// а не добавляться новая - это нам не нужно.
			if ($nodes_count != $nodes_current_number) { unset($_POST["id"]); }

			$nodes_current_number++;
		}
		db_commit();
	}
	layout(CMS_PREFIX . "/html-empty");
	return render_view("form_saved");
}
else
{
	$_CMS["do"] = "add";
	$one2many_parent_id = null;
	/*
	Пользовательская функция определения корневой node_id для вывода списка возможных добавляемых
	объектов для указанного поля - используеться только для функции pick_node или в форме one2many,
	если она определена - то берёться её возвращаемое значение, иначе будет использоваться id ноды
	с системным именем указанным в "select_root_name" метаданных данного добавляемого типа.
	В данной функции доступен массив $_POST с как минимум доступными полями
	 - type (имя типа)
	 - parent_id (id родительской ноды к которой добавляеться данный тип)
	*/
	if (function_exists("cms_get_root_node_for_field_name"))
	{
		$node_id = cms_get_root_node_for_field_name($one2many_field);
		if ($node_id) { $one2many_parent_id = $node_id; }
	}

	if ($one2many_parent_id === null)
	{ $one2many_parent_id = get_id_by_system_name(db_select_cell("SELECT select_root_name FROM cms_metadata WHERE type = ? AND field_name = ?", $_POST["type"], $one2many_field)); }
}

// Перед выводом формы возвращаем все поля из $_CMS обратно в $_POST
foreach ($_CMS as $key => $val) { $_POST["__cms__" . $key] = $val; } unset($_CMS);