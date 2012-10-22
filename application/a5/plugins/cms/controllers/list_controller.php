<?php
require_once("include/setup.php");

if (action() == "index")
{
	if (!isset($_GET["id"])) { die("<b>Неисправимая ошибка:</b> Не указано id родительской ноды для отображения данных"); }

	// Получим список дочерних типов указанной родительской ноды
	$child_types = cms_get_child_types($_GET["id"]);

	$where = array();
	$where["parent_id"] = $_GET["id"];
	if (count($child_types)) { $where["type NOT IN (?)"] = $child_types; }
	$where["@order"] = "type";
	$additional_child_types = db_select_col("SELECT DISTINCT type FROM cms_nodes", $where);

	$child_types = array_merge($child_types, $additional_child_types);
	if (isset($_GET["only_type"])) { $child_types = array_values(array_intersect($child_types, array($_GET["only_type"]))); }

	$types = db_select_all("
	SELECT
		type,
		name,
		name_list
	FROM
		cms_node_types
	WHERE
		type IN (?)
	", $child_types, array("@key" => "type"));
}

if (action() == "type-nodes")
{
	if (!isset($_GET["id"])) { $_GET["id"] = null; }
	if (isset($_POST["lang"])) { redirect_to("@overwrite", true, "lang", $_POST["lang"]) ;}

	if (!isset($_GET["type"])) { render_text("Вы должны указать тип"); }
	$type_info = db_select_row("SELECT type, name, name_list, is_lang FROM cms_node_types WHERE type = ?", $_GET["type"]);
	if ($type_info === false) { render_text("Такой тип не существует"); }

	$_GET["type"] = $type_info["type"];
	$type_name_list = $type_info["name_list"];

	$languages_count = count(cms_get_all_languages());

	$is_auth_type_read = cms_is_have_auth_for_type($_GET["type"], CMS_AUTH_READ);
	$is_auth_type_create = cms_is_have_auth_for_type($_GET["type"], CMS_AUTH_CREATE);
	$is_auth_type_update = cms_is_have_auth_for_type($_GET["type"], CMS_AUTH_UPDATE);
	$is_auth_type_delete = cms_is_have_auth_for_type($_GET["type"], CMS_AUTH_DELETE);

	// Проверим - есть ли пользовательское определение типа, или системное. Подключим если есть.
	$module_user_file = null;
	if (defined('CMS_MODULES_DIR')) { $module_user_file = normalize_patH(CMS_MODULES_DIR . "/" . $_GET["type"] . ".php"); }
	$module_system_file = __DIR__ .  "/modules/" . $_GET["type"] . ".php";
	if ($module_user_file !== null && file_exists($module_user_file)) { require($module_user_file); }
	elseif (file_exists($module_system_file)) { require($module_system_file); }

	// Всё это нужно только если право на чтение данных
	if ($is_auth_type_read)
	{
		// Если просят выводить другой язык - устанавливаем его
		if (isset($_GET["lang"])) { cms_set_language($_GET["lang"]); }
		$_POST["lang"] = $_GET["lang"] = cms_get_language();

		/*
		Данную функцию нужно определить в модуле типа
		Функция должна возвращать список полей, которые должны выводиться
		в списке, пример такого массива следующий
		array
		(
			array("name", "Название", "string"),
			array("email", "E-mail", "email"),
		);

		Каждый элемент данного массива должен содержать от одного до трёх элементов:
		1) SQL имя данного поля - именно это имя будет передаваться как поле для сортировки
		2) Человекопонятное название
		3) Тип поля из списка cms-типов полей - на основе его данные будут конвертироваться и выводиться

		Обязателен только первый элемент. По остальным полям будет произведена попытка автоматического
		их определения из таблицы cms_metadata. Вы можете пропустить один из параметров указав его как null

		Как упрощенный вариант - элемент может быть просто строкой, в таком случае будет считаться что
		передан массив с одним элементом.

		На вход функция должна принимать один параметр: имя типа
		*/
		$is_field_list_defined_by_user = false;
		if (function_exists("cms_get_type_field_list"))
		{
			$_field_list = cms_get_type_field_list($_GET["type"]);
			if (is_array($_field_list)) { $is_field_list_defined_by_user = true; }
		}

		// Первое поле всегда - id
		$field_list = array("id" => array("name" => "ID", "type" => "integer", "is_req" => 1));

		// Если поля определены пользователем - заполняем массив списка полей сразу же
		if ($is_field_list_defined_by_user)
		{
			foreach ($_field_list as $field)
			{
				if (!is_array($field)) { $field = array($field); }
				if (count($field) >= 1)
				{
					$field_list[$field[0]] = array
					(
						"name" => (isset($field[1]) ? $field[1] : null),
						"type" => (isset($field[2]) ? $field[2] : null),
						"is_req" => null
					);
				}
			}
		}

		// Получим список полей для объекта
		$type_fields = cms_get_type_fields($_GET["type"]);

		// Если поля не определены пользователем - заполняем полностью из мета-данных
		if (!$is_field_list_defined_by_user)
		{
			foreach ($type_fields as $field_name => $field)
			{
				// Данные типы полей пропускаем
				if (!in_array($field["field_type"], array("blob", "fti")))
				{ $field_list[$field["field_name"]] = array("name" => $field["name"], "type" => $field["field_type"], "is_req" => $field["is_req"]); }
			}
		}
		else
		{
			// Если поля определены пользователем - дополняем о них недостающую информацию	из метаданных если это возможно
			foreach ($field_list as $field_name => $field)
			{
				if ($field_list[$field_name]["name"] === null)
				{ $field_list[$field_name]["name"] = array_key_exists($field_name, $type_fields) ? $type_fields[$field_name]["name"] : $field_name; }

				if ($field_list[$field_name]["type"] === null)
				{ $field_list[$field_name]["type"] = array_key_exists($field_name, $type_fields) ? $type_fields[$field_name]["field_type"] : "string"; }

				if ($field_list[$field_name]["is_req"] === null)
				{ $field_list[$field_name]["is_req"] = array_key_exists($field_name, $type_fields) ? $type_fields[$field_name]["is_req"] : 0; }
			}
		}

		/*
		Пользовательская функция подсчёта количества выводимых строк
		На вход принимает следующие параметры:
		1) тип
		2) id родительской ноды
		Должна возвращать число строк для этих условий
		*/
		if (function_exists("cms_get_type_nodes_count")) { $type_rows_count = cms_get_type_nodes_count($_GET["type"], $_GET["id"]); }
		else { $type_rows_count = db_select_cell("SELECT COUNT(*) FROM " . db_escape_ident("v_" . $_GET["type"]), array("parent_id" => $_GET["id"])); }

		$page_nav = Pagination::construct($type_rows_count, array("limit" => 50, "pages_limit" => 20));
		$is_list_all_type_nodes = @$_GET[$page_nav["varname"]] == "-1" ? true : false;

		/*
		Пользовательская функция выборки данных
		Функция должна возвращать массив - список данных для данного типа.

		На вход функция должна принимать следующие параметры:
		1) тип
		2) id родительской ноды
		3) смещение (offset)
		4) кол-во (limit)

		Также нужно учесть что в массиве $_GET может содержаться параметр "sort_cond" - условие для сортировки.
		Параметр пригоден для прямого использования в качестве значения для "@order" условия в sql-запросах.

		Внимание!!!
		Каждое имя поля в возвращаемой строке ДОЛЖНО совпадать с именами полей возвращаемых функцией
		cms_get_type_field_list иначе либо будет выведено пустое значение, либо поле вообще показано не будет.
		Функция ОБЯЗАТЕЛЬНО должна возвращать поле с именем "id" - иначе вернётся ошибка
		*/
		if (function_exists("cms_get_type_nodes"))
		{
			$final_field_list = $field_list;
			if (!$is_list_all_type_nodes) { $type_rows = cms_get_type_nodes($_GET["type"], $_GET["id"], $page_nav["offset"], $page_nav["limit"]); }
	 		else { $type_rows = cms_get_type_nodes($_GET["type"], $_GET["id"]); }
	 		if (!is_array($type_rows)) { $type_rows = array(); }
	 	}
		else
		{
			$final_field_list = array();
			$table_fields = array_keys($field_list);

			$join_table_number = 1;
			$join_tables = array();

			foreach ($table_fields as $i => $name)
			{
				if ($field_list[$name]["type"] == "object")
				{
					if ($field_list[$name]["is_req"])
					{
						$table_fields[$i] = "t" . $join_table_number . ".name as " . db_escape_ident($name . "_name");
						$join_tables[] = "JOIN v_cms_nodes t" . $join_table_number . " ON t" . $join_table_number . ".id = t0." . db_escape_ident($name);
						$join_table_number++;
					}
					else
					{ $table_fields[$i] = "(SELECT name FROM v_cms_nodes WHERE id = t0." . db_escape_ident($name) . ") as " . db_escape_ident($name . "_name"); }
					$final_field_list[$name . "_name"] = array("name" => $field_list[$name]["name"], "type" => "string");
				}
				else
				{
					$table_fields[$i] = "t0." . db_escape_ident($name);
					$final_field_list[$name] = $field_list[$name];
				}
			}

			$where = array();
			$where["t0.parent_id"] = $_GET["id"];

			// Добавляем условие для сортировки - или выбираем дефолтное условие сортировки
			if (isset($_GET["sort_cond"])) { $where["@order"] = $_GET["sort_cond"]; } else { $where["@order"] = "t0.sibling_index ASC"; }

			if (!$is_list_all_type_nodes)
			{
				$where["@offset"] = $page_nav["offset"];
				$where["@limit"] = $page_nav["limit"];
			}

			$type_rows = db_select_all("
			SELECT
				" . implode(", ", $table_fields) . "
			FROM
				" . db_escape_ident("v_" . $_GET["type"]) . " t0
				" . implode("\n", $join_tables) . "
			", $where);
		}

		$where = array();
		$where["parent_id"] = $_GET["id"];
		$where["type"] = $_GET["type"];
		$node_ids = array();
		foreach ($type_rows as $item)
		{
			if (!array_key_exists("id", (array) $item))
			{ die("<b>Неисправимая ошибка:</b> набор данных для типа \"" . h($_GET["type"]) . "\" не содержит поля с именем \"id\""); }
			$node_ids[] = $item["id"];
		}
		$where["id IN (?i)"] = $node_ids;
		$where["@key"] = "id";

		$node_names = db_select_all("
		SELECT
			id,
			name
		FROM
			v_cms_nodes
		", $where);

		function cms_format_by_type($value, $type)
		{
			switch ($type)
			{
				case 'plain_text':
				case 'url': $value = cms_url_replace($value); break;
				case 'date': $value = Date::format($value, "d.m.Y"); break;
				case 'time': $value = Date::format($value, "H:i:s"); break;
				case 'datetime': $value = Date::format($value, "d.m.Y H:i:s"); break;
				case 'sex': $value = cms_get_gender($value); break;
				case 'price':
				case 'cost':
				case 'sum': $value = str_replace(" ", "&nbsp;", number_format($value, 2, ",", " ")); break;
				case 'number': $value = (float) $value; break;
				case 'filesize': $value = human_size($value); break;
			}
			return text2string($value);
		}
	}
}

if (action() == "type-nodes-delete")
{
	layout(false);
	if (isset($_POST["nodes"]))
	{
		$error_message = null;
		$affected_nodes = array();

		if (isset($_GET["id"]))
		{
			// Перед удалением - получим parent_id от parent_id удаляемых нод
			$affected_nodes[] = $_GET["id"];
			$parent_id = db_select_cell("SELECT parent_id FROM cms_nodes WHERE id = ?i", $_GET["id"]);
			if ($parent_id) { $affected_nodes[] = $parent_id; }
		}

		$result = cms_check_auth_for_delete($_POST["nodes"]);
		if ($result) { $result = cms_delete_nodes($_POST["nodes"]); }
		if ($result === false) { $error_message = $GLOBALS["php_errormsg"]; }

		// Оставим только те parent_id которые остались после удаления
		$affected_nodes = db_select_col("SELECT id FROM cms_nodes WHERE id IN (?i)", $affected_nodes);
		// Если в итоге не осталось ни одного родителя которого нужно подгрузить - грузим "root"
		if (!count($affected_nodes)) { $affected_nodes[] = ""; }
	}
	else { render_nothing(); }
}

if (action() == "type-nodes-move")
{
	layout(false);
	if (isset($_POST["nodes"]))
	{
		if (@$_POST["do"] == "one_move")
		{ $status = cms_one_move_nodes($_POST["nodes"], $_POST["direction"]); }

		if (@$_POST["do"] == "full_move")
		{ $status = cms_full_move_nodes($_POST["nodes"], $_POST["direction"]); }
	}
	else { render_nothing(); }
}

if (action() == "copy-frames") { layout(CMS_PREFIX . "/frames"); }
if (action() == "copy-buttons") { $_PAGE["main_bg"] = "caption-bg"; }

if (action() == "copy-nodes")
{
	layout(false);
	if (isset($_POST["copy_to"]) && is_array($_POST["copy_to"]) && isset($_POST["nodes"]) && is_array($_POST["nodes"]))
	{
		$error_message = "";
		cms_catch_db_error();
		foreach ($_POST["copy_to"] as $parent_id) { cms_copy_nodes($_POST["nodes"], $parent_id); }
		$error_message = cms_get_db_error();
		if (!is_empty($error_message)) { db_rollback(); } else { db_commit(); }
	}
	else { render_nothing(); }
}

if (action() == "move-frames") { layout(CMS_PREFIX . "/frames"); }
if (action() == "move-buttons") { $_PAGE["main_bg"] = "caption-bg"; }

if (action() == "move-nodes")
{
	layout(false);
	if (isset($_POST["move_to"]) && isset($_POST["nodes"]) && is_array($_POST["nodes"]))
	{
		$old_parents = db_select_col("SELECT DISTINCT parent_id FROM cms_nodes WHERE id IN (?i)", $_POST["nodes"]);
		$error_message = "";
		cms_catch_db_error();
		cms_move_nodes($_POST["nodes"], $_POST["move_to"]);
		$error_message = cms_get_db_error();
		if (!is_empty($error_message)) { db_rollback(); } else { db_commit(); }
	}
	else { render_nothing(); }
}
