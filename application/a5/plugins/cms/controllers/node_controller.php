<?php
require_once("include/setup.php");

if (action() == "index")
{
	layout(CMS_PREFIX . "/frames");

	// Есть ли возможность создания/редактирования с помощью формы один ко многим
	$is_one2many = false;

	// Если parent_id не передали - значит добавляем (при добавлении) в корень дерева
	if (!isset($_GET["parent_id"])) { $_GET["parent_id"] = null; }

	// Если передали id конкретного объекта - получаем его тип и его родителя
	if (isset($_GET["id"]))
	{
		$node = db_select_row("SELECT parent_id, type FROM cms_nodes WHERE id = ?i", $_GET["id"]);
		if ($node !== false) { $_GET = array_merge($_GET, $node); }
	}

	// Если тип объекта до сих пор не известен - пытаемся его определить если указана родительская нода
	if (!isset($_GET["type"]))
	{
		if (isset($_GET["parent_id"]))
		{
			$child_types = cms_get_child_types($_GET["parent_id"]);
			if (count($child_types) == 1) { $_GET["type"] = $child_types[0]; }
		}
	}
	else
	{
		$type_info = db_select_row("SELECT type, one2many_field FROM cms_node_types WHERE type = ?", $_GET["type"]);
		if ($type_info !== false)
		{
			// Если указано поле one2many
			if (!is_empty($type_info["one2many_field"]))
			{
				// Есть ли в таблице другие поля являющиеся обязательными и не имеющие значения по-умолчанию
				$is_req_count = db_select_cell("
				SELECT
					COUNT(*)
				FROM
					cms_metadata
				WHERE
					type = ?
					AND field_name != ?
					AND is_req = 1
					AND default_value IS NULL
				", $_GET["type"], $type_info["one2many_field"]);
				if (!$is_req_count) { $is_one2many = true; }
			}
		}
	}
}

if (action() == "select-buttons") { $_PAGE["main_bg"] = "threedface-bg"; }

if (action() == "select-form")
{
	$_PAGE["main_bg"] = "threedface-bg";
	$_PAGE["body_overflow"] = "auto";

	$where = array();
	$where["@order"] = "name";
	if (@$_GET["parent_id"]) { $where["type IN (?)"] = cms_get_child_types($_GET["parent_id"]); }

	$child_types = db_select_all("
	SELECT
		type,
		name
	FROM
		cms_node_types
	", $where);
}

if (action() == "header" || action() == "header-one2many")
{
	$_PAGE["main_bg"] = "threedface-bg";
	$is_can_read = cms_is_have_auth_for_type($_GET["type"], CMS_AUTH_READ);
	$type_name = db_select_cell("SELECT name FROM cms_node_types WHERE type = ?", $_GET["type"]);
}

if (action() == "buttons" || action() == "buttons-one2many")
{
	$form = new FormProcessor();

	$_PAGE["main_bg"] = "threedface-bg";
	$is_can_read = cms_is_have_auth_for_type($_GET["type"], CMS_AUTH_READ);
	$is_can_create = cms_is_have_auth_for_type($_GET["type"], CMS_AUTH_CREATE);
	$is_can_update = cms_is_have_auth_for_type($_GET["type"], CMS_AUTH_UPDATE);

	$type_info = db_select_row("SELECT type, is_lang FROM cms_node_types WHERE type = ?", $_GET["type"]);
	$languages = cms_get_all_languages();

	$is_multi_langs_save = ($type_info["is_lang"] && count($languages) > 1);
	if ($is_multi_langs_save && !isset($_GET["id"])) { $_GET["chk_multi_save"] = "on"; }

	// Получим предыдущего и следующего дитя этого же parent_id
	if (action() == "buttons" && isset($_GET["id"]))
	{
		$node = db_select_row("SELECT id, type, parent_id, sibling_index FROM v_cms_nodes WHERE id = ?i", $_GET["id"]);
		if ($node !== false)
		{
			$prev_node_id = db_select_cell("SELECT id FROM v_cms_nodes", array("type" => $node["type"], "parent_id" => $node["parent_id"], "sibling_index < ?i" => $node["sibling_index"], "@order" => "sibling_index DESC"));
			$next_node_id = db_select_cell("SELECT id FROM v_cms_nodes", array("type" => $node["type"], "parent_id" => $node["parent_id"], "sibling_index > ?i" => $node["sibling_index"], "@order" => "sibling_index"));
		}
	}
}

if (action() == "form" || action() == "form-one2many")
{
	$_PAGE["main_bg"] = "threedface-bg";

	$form = new FormProcessor();
	$form->persistent_passwords(true);

	// Все параметры переносим в массив $_POST - так удобнее работать
	$_POST = array_merge($_GET, $_POST);
	require("node/form.helpers.php");
	require("node/form.prepare.php");
	require("node/" . action() . ".php");
}