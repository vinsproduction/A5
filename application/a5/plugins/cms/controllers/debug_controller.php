<?php
require_once("include/setup.php");

function copy_type_data($type, $from_lang, $to_lang)
{
	static $extra_fields = null;
	static $extra_select_fields = null;

	if ($extra_fields === null && $extra_select_fields === null)
	{
		$extra_fields = db_get_field_types("cms_node_extras");
		unset($extra_fields["lang"]); $extra_fields = $extra_select_fields = array_keys($extra_fields);
		foreach ($extra_fields as $i => $field_name) { $extra_fields[$i] = db_escape_ident($field_name); }
		foreach ($extra_select_fields as $i => $field_name) { $extra_select_fields[$i] = db_escape_ident("e." . $field_name); }
	}

	// Копируем данные одного языка в другой для типовой таблицы
	$fields = array_keys(cms_get_type_fields($type));
	foreach ($fields as $i => $field_name) { $fields[$i] = db_escape_ident($field_name); }

	db_query("
	INSERT INTO " . db_escape_ident($type) . "
		(id, lang" . (count($fields) ? ", " . implode(", ", $fields) : null) . ")
	SELECT
		id, ?" . (count($fields) ? ", " . implode(", ", $fields) : null) . "
	FROM
		 " . db_escape_ident($type) . "
	?filter
	", $to_lang, array("lang" => $from_lang));

	// Тоже самое делаем с таблицей node_extras
	db_query("
	INSERT INTO cms_node_extras
		(lang" . (count($extra_fields) ? ", " . implode(", ", $extra_fields) : null) . ")
	SELECT
		?" . (count($extra_select_fields) ? ", " . implode(", ", $extra_select_fields) : null) . "
	FROM
		cms_node_extras e
		JOIN cms_nodes n ON n.id = e.id
	?filter
	", $to_lang, array("n.type" => $type, "e.lang" => $from_lang));
}

if (!DEBUG_MODE) { render_text("You are not authorized to view this page"); }

if (action() == "run-sql")
{
	$_PAGE["main_bg"] = "threedface-bg";
	$_PAGE["body_overflow"] = "auto";

	$form = new FormProcessor();

	if (@$_POST["do"] == "run")
	{
		$form->check("sql", "entered");
		if (!$form->is_form_error())
		{
			db_begin();

			cms_catch_db_error();
			$start = microtime(true);
			$result = db_query($_POST["sql"]);
			$query_time = microtime(true) - $start;
			$db_error = cms_get_db_error();

			if ($db_error) { $form->set_error("sql", $db_error); db_rollback(); } else { db_commit(); }
		}
	}
}

if (action() == "types-list")
{
	$where = array();

	if (isset($_GET["sort_cond"])) { $where["@order"] = $_GET["sort_cond"]; } else { $where["@order"] = "type ASC"; }

	$types = db_select_all("
	SELECT
		type,
		name
	FROM
		cms_node_types
	", $where);
}

if (action() == "types-recreate-views")
{
	layout(false);

	$error_string = false;
	if (isset($_POST["nodes"]) && is_array($_POST["nodes"]) && count($_POST["nodes"]))
	{
		db_begin();
		cms_catch_db_error();
		foreach ($_POST["nodes"] as $type)
		{
			$type = db_select_cell("SELECT type FROM cms_node_types WHERE type = ?", $type);
			if ($type !== false) { cms_create_or_replace_type_view($type); }
		}
		$error_string = cms_get_db_error();
		if ($error_string) { db_rollback(); } else { db_commit(); }
	}
}

if (action() == "types-edit" || action() == "types-add")
{
	$_PAGE["main_bg"] = "threedface-bg";
	$_PAGE["body_overflow"] = "auto";

	$form = new FormProcessor();

	if (action() == "types-edit")
	{
		$type_info = db_select_row("
		SELECT
			type,
			tree_name,
			icon,
			is_hidden_tree,
			is_lang,
			name,
			name_list,
			controller,
			action,
			is_shared_fields_disabled,
			is_additional_tab_disabled,
			one2many_field,
			disable_fields,
			disable_childs
		FROM
			cms_node_types
		WHERE
			type = ?
		", $_GET["id"]);
	}

	if (@$_POST["do"] == "save")
	{
		if ($form->check("type", "entered"))
		{
			if (!preg_match("/^[a-z0-9_]+$/su", trim($_POST["type"]))) { $form->set_error("type", "Не верно (может содержать только: a-z, 0-9 и _)"); }
			elseif (preg_match("/^cms_/su", trim($_POST["type"]))) { $form->set_error("type", "Не может начинаться с \"cms_\""); }
			else
			{
				if (action() == "types-add")
				{
					if (false !== db_select_cell("SELECT type FROM cms_node_types WHERE type = ?", $_POST["type"]))
					{ $form->set_error("type", "Такой уже есть"); }
				}
				else
				{
					if (false !== db_select_cell("SELECT type FROM cms_node_types WHERE type = ? AND type != ?", $_POST["type"], $_GET["id"]))
					{ $form->set_error("type", "Такой уже есть"); }
				}
			}
		}

		if ($form->validate("one2many_field", "entered") && !preg_match("/^[a-z0-9_]+$/u", trim($_POST["one2many_field"])))
		{ $form->set_error("one2many_field", "Не верно"); }

		$form->check("name", "entered");
		$form->check("name_list", "entered");
		$form->check("icon", "selected");

		if ($form->check("controller", "entered"))
		{
			if (!cms_valid_controller($_POST["controller"]))
			{ $form->set_error("controller", "Неверно (может содержать только: a-z, A-Z, 0-9, _, - и /)"); }
		}

		if ($form->check("action", "entered"))
		{
			if (!cms_valid_action($_POST["action"]))
			{ $form->set_error("action", "Неверно (может содержать только: a-z, A-Z, 0-9, _, и -)"); }
		}

		if (!$form->is_form_error())
		{
			$fields = array
			(
				"type" => $_POST["type"],
				"name" => $_POST["name"],
				"name_list" => $_POST["name_list"],
				"tree_name" => $_POST["tree_name"],
				"icon" => $_POST["icon"],
				"controller" => $_POST["controller"],
				"action" => $_POST["action"],
				"is_hidden_tree" => @$_POST["is_hidden_tree"],
				"is_lang" => @$_POST["is_lang"],
				"is_shared_fields_disabled" => @$_POST["is_shared_fields_disabled"],
				"is_additional_tab_disabled" => @$_POST["is_additional_tab_disabled"],
				"disable_fields" => $_POST["disable_fields"],
				"disable_childs" => $_POST["disable_childs"],
				"one2many_field" => $_POST["one2many_field"]
			);

			if (action() == "types-add")
			{
				db_begin();
				cms_catch_db_error();
				cms_create_type($_POST["type"], $fields);
				$db_error = cms_get_db_error();
				if ($db_error) { $form->set_error("type", $db_error); db_rollback(); } else { db_commit(); }
				if (!$form->is_form_error()) { redirect_to("action", "types-fields", "id", $_POST["type"]); }
			}
			elseif (action() == "types-edit")
			{
				db_begin();
				cms_catch_db_error();
				// Если у типа изменили языковый флаг
				if (!!$type_info["is_lang"] != !!@$_POST["is_lang"])
				{
					// Если флаг установили - нужно скопировать данные в выбранный язык или во все если не выбран
					if (@$_POST["is_lang"])
					{
						// Сначала модифицируем данные, т.к. при этом модифицируются и индексы (уникальные)
						cms_edit_type($_GET["id"], $fields);

						// Выбран язык - просто выставляем его везде
						if (!@is_empty($_POST["use_lang"]))
						{
							db_update($type_info["type"], array("lang" => $_POST["use_lang"]));
							db_update("cms_node_extras", array("lang" => $_POST["use_lang"]), array("id IN (SELECT id FROM cms_nodes WHERE type = ?)" => $type_info["type"]));
						}
						else
						{
							$from_lang = null;
							$copy_langs = array_keys(cms_get_all_languages());
							foreach ($copy_langs as $lang)
							{
								// Заменяем текущие данные на первый язык из списка
								if ($from_lang === null)
								{
									$from_lang = $lang;
									db_update($type_info["type"], array("lang" => $from_lang));
									db_update("cms_node_extras", array("lang" => $from_lang), array("id IN (SELECT id FROM cms_nodes WHERE type = ?)" => $type_info["type"]));
									continue;
								}
								// Остальные тупо копируем из него
								copy_type_data($type_info["type"], $from_lang, $lang);
							}
						}
					}
					// Если флаг сняли - нужно оставить дефолтный язык или же выбранный
					else
					{
						if (!@is_empty($_POST["use_lang"])) { $use_lang = $_POST["use_lang"]; }
						else { $use_lang = cms_get_default_language(); $use_lang = @$use_lang["lang"]; }

						// Удаляем из типовой таблицы все языки кроме выбранного и устанавливаем язык в null
						db_delete($type_info["type"], array("lang != ?" => $use_lang));
						db_update($type_info["type"], array("lang" => null));
						// Удаляем из общей таблицы все языки кроме выбранного и устанавливаем язык в null
						db_delete("cms_node_extras", array("lang != ?" => $use_lang, "id IN (SELECT id FROM cms_nodes WHERE type = ?)" => $type_info["type"]));
						db_update("cms_node_extras", array("lang" => null), array("id IN (SELECT id FROM cms_nodes WHERE type = ?)" => $type_info["type"]));

						// Теперь модифицируем данные, т.к. при этом модифицируются и индексы (уникальные)
						cms_edit_type($_GET["id"], $fields);
					}
				}
				// Если язык не меняли - просто изменяем его данные
				else { cms_edit_type($_GET["id"], $fields); }

				$db_error = cms_get_db_error();
				if ($db_error) { $form->set_error("type", $db_error); db_rollback(); } else { db_commit(); }
				if (!$form->is_form_error()) { redirect_to("action", "types-edit", "id", $_POST["type"]); }
			}
		}
	}
	elseif (action() == "types-edit") { $_POST = $type_info; }
	else
	{
		$_POST["is_lang"] = "on";
		$_POST["tree_name"] = "{name}";
		$_POST["controller"] = "index";
		$_POST["action"] = "index";
	}

	render_view("types-form");
}

if (action() == "types-delete")
{
	layout(false);

	$error_string = false;
	if (isset($_POST["nodes"]) && is_array($_POST["nodes"]) && count($_POST["nodes"]))
	{
		db_begin();
		cms_catch_db_error();
		foreach ($_POST["nodes"] as $type) { cms_drop_type($type); }
		$error_string = cms_get_db_error();
		if ($error_string) { db_rollback(); } else { db_commit(); }
	}
}

if (action() == "types-childs")
{
	$_PAGE["main_bg"] = "threedface-bg";

	$form = new FormProcessor();

	if (@$_POST["do"] == "save")
	{
		if (!$form->is_form_error())
		{
			if (!isset($_POST["child_types"]) || !is_array($_POST["child_types"])) { $_POST["child_types"] = array(); }
			// Добавляем те, которых нет
			foreach ($_POST["child_types"] as $type)
			{
				if (false === db_select_cell("SELECT type FROM cms_node_type_childs WHERE type = ? AND child_type = ?", $_GET["id"], $type))
				{ db_insert("cms_node_type_childs", array("type" => $_GET["id"], "child_type" => $type)); }
			}

			// Удаляем те, которых в этом списке нет
			if (count($_POST["child_types"]))
			{ db_delete("cms_node_type_childs", array("type" => $_GET["id"], "child_type NOT IN (?)" => $_POST["child_types"])); }
			else { db_delete("cms_node_type_childs", array("type" => $_GET["id"])); }

			redirect_to("action", "types-childs", "id", $_GET["id"]);
		}
	}
	else
	{ $_POST["child_types"] = db_select_col("SELECT child_type FROM cms_node_type_childs WHERE type = ?", $_GET["id"]); }

	$types = db_select_all("
	SELECT
		type,
		name
	FROM
		cms_node_types
	ORDER BY
		type
	", array("@key" => "type"));
}

if (preg_match("/^types-fields/u", action()))
{
	$field_types = cms_get_column_types();
	$types_list = db_select_all("SELECT type, name FROM cms_node_types ORDER BY type", array("@key" => "type"));

	$select_methods = array
	(
		"linear" => "Списком",
		"catalog" => "Список с деревом слева",
		"linear_dropdown" => "Обычный <SELECT>",
	);

	$foreign_key_actions = array("NO ACTION", "RESTRICT", "CASCADE", "SET NULL", "SET DEFAULT");
}

if (action() == "types-fields")
{
	// Список системных полей, имена которых нельзя использовать в обычных типовых таблицах.
	$system_fields = array
	(
		"id",
		"lang",
		"url_id",
		"parent_id",
		"type",
		"controller",
		"action",
		"system_name",
		"title",
		"meta_keywords",
		"meta_description",
		"is_hidden",
		"is_menuitem",
		"sibling_index",
	);

	$where = array();
	if (isset($_GET["sort_cond"])) { $where["@order"] = $_GET["sort_cond"]; } else { $where["@order"] = "id ASC"; }
	$nodes = cms_get_type_columns($_GET["id"], $where);
}

if (action() == "types-fields-move")
{
	layout(false);

	$error_string = false;
	if (isset($_POST["nodes"]) && is_array($_POST["nodes"]) && count($_POST["nodes"]))
	{
		db_begin();
		cms_catch_db_error();

		$direction = $_POST["direction"] == "up" ? "DESC" : "ASC";
		$_POST["nodes"] = db_select_col("SELECT id FROM cms_metadata WHERE id IN (?i) ORDER BY id " . ($_POST["direction"] == "up" ? "ASC" : "DESC"), $_POST["nodes"]);

		$affected_nodes = array();
		foreach ($_POST["nodes"] as $node_id)
		{
			$sign = $_POST["direction"] == "up" ? "<" : ">";
			$max_id = db_select_cell("SELECT MAX(id) FROM cms_metadata");
			$id = db_select_cell("SELECT id FROM cms_metadata WHERE type = ? AND id $sign ?i ORDER BY id $direction", $_GET["id"], $node_id);
			if ($id !== false && $max_id > 0)
			{
				$affected_nodes[$node_id] = $id;
				db_update("cms_metadata", array("id" => $max_id + 1), array("id" => $node_id));
				db_update("cms_metadata", array("id" => $node_id), array("id" => $id));
				db_update("cms_metadata", array("id" => $id), array("id" => $max_id + 1));
			}
		}
		$affected_nodes = array_values($affected_nodes);

		$error_string = cms_get_db_error();
		if ($error_string) { db_rollback(); } else { db_commit(); }
	}
}

if (action() == "types-fields-add")
{
	$_PAGE["main_bg"] = "threedface-bg";
	$_PAGE["body_overflow"] = "auto";

	$form = new FormProcessor();
	$node_types = db_select_all("SELECT type, name FROM cms_node_types ORDER BY type", array("@key" => "type"));

	if (@$_POST["do"] == "save")
	{
		$form->check("name", "entered");

		if ($form->check("field_name", "entered"))
		{
			if (!preg_match("/^[a-z0-9_]+$/u", trim($_POST["field_name"]))) { $form->set_error("field_name", "Не верно"); }
			else
			{
				if (false !== db_select_cell("SELECT id FROM cms_metadata WHERE type = ? AND field_name = ?", $_GET["id"], $_POST["field_name"]))
				{ $form->set_error("field_name", "Уже есть"); }
			}
		}

		if ($form->check("field_type", "entered"))
		{
			if (!$field_types[$_POST["field_type"]]) { $form->set_error("field_type", "Неизвестный тип"); }
			elseif ($_POST["field_type"] == "fti")
			{
				if ($form->check("fti_fields", "entered"))
				{
					$fields = explode(",", $_POST["fti_fields"]);
					foreach ($fields as $name)
					{
						if (!preg_match("/^[a-z0-9_]+$/u", trim($name)))
						{ $form->set_error("fti_fields", "Одно из полей имеет недопустимое имя"); }
					}
				}
			}
		}

		if ($form->validate("field_type_length", "entered"))
		{
			if (!preg_match("/^[0-9,]*$/sux", trim($_POST["field_type_length"])))
			{ $form->set_error("field_type_length", "Размерность может содержать только цифры и запятую"); }
		}

		if (!$form->is_form_error())
		{
			db_begin();
			cms_catch_db_error();
			cms_create_type_column($_GET["id"], array
			(
				"type" => $_GET["id"],
				"name" => $_POST["name"],
				"field_name" => $_POST["field_name"],
				"field_type" => $_POST["field_type"],
				"field_type_length" => $_POST["field_type_length"],
				"on_update" => $_POST["on_update"],
				"on_delete" => $_POST["on_delete"],
				"is_req" => @$_POST["is_req"],
				"is_idx" => @$_POST["is_idx"],
				"is_uniq" => @$_POST["is_uniq"],
				"is_ncs" => @$_POST["is_ncs"],
				"fti_fields" => $_POST["fti_fields"],
				"select_root_name" => $_POST["select_root_name"],
				"select_type" => $_POST["select_type"],
				"select_method" => $_POST["select_method"],
				"root_folder" => $_POST["root_folder"],
				"default_value" => $_POST["default_value"],
			));
			$error_string = cms_get_db_error();
			if ($error_string) { $form->set_error("field_name", $error_string); db_rollback(); }
			else { db_commit(); redirect_to("@overwrite", true, "action", @$_POST["is_one_more"] ? "types-fields-add" : "types-fields"); }
		}
	}
	else
	{ $_POST["is_one_more"] = "on"; }
}

if (action() == "types-fields-change")
{
	layout(false);

	$error_string = false;
	$form = new FormProcessor();
	$form->set_check_array($_GET);

	$fields = array();
	$type_info = db_select_row("SELECT * FROM cms_node_types WHERE type = ?", $_GET["id"]);

	if (!$form->validate("field_id", "entered,valid_integer")) { $error_string = "Неверное id поля для изменения!"; }

	if (!$error_string)
	{
		if (@$_GET["do"] == "change_name")
		{
			if (!$form->validate("param_value", "entered")) { $error_string = "Название поля не может быть пустым!"; }
			else { $fields["name"] = $_GET["param_value"]; }
		}

		if (@$_GET["do"] == "change_field_name")
		{
			if (!$form->validate("param_value", "entered")) { $error_string = "Название поля не может быть пустым!"; }
			elseif (!preg_match("/^[a-z0-9_]+$/u", trim($_GET["param_value"]))) { $error_string = "Неверное имя поля!"; }
			elseif (false !== db_select_cell("SELECT id FROM cms_metadata WHERE type = ? AND field_name = ? AND id != ?i", $_GET["id"], $_GET["param_value"], $_GET["field_id"])) { $error_string = "Такое поле уже есть!"; }
			else { $fields["field_name"] = $_GET["param_value"]; }
		}

		if (@$_GET["do"] == "change_field_type")
		{
			if (!$field_types[$_GET["param_value"]]) { $error_string = "Неизвестный тип!"; }
			else
			{
				// Если меняем тип поля - размерность нужно убрать, т.к.
				// новый тип может не поддерживать указание размерности
				$fields["field_type"] = $_GET["param_value"];
				$fields["field_type_length"] = null;
			}
		}

		if (@$_GET["do"] == "change_field_type_length")
		{
			if (!preg_match("/^[0-9,]*$/sux", trim($_GET["param_value"])))
			{ $error_string = "Размерность может содержать только цифры и запятую!"; }
			else { $fields["field_type_length"] = $_GET["param_value"]; }
		}

		if (@$_GET["do"] == "change_is_req") { $fields["is_req"] = @$_GET["param_value"]; }
		if (@$_GET["do"] == "change_is_idx") { $fields["is_idx"] = @$_GET["param_value"]; }
		if (@$_GET["do"] == "change_is_uniq") { $fields["is_uniq"] = @$_GET["param_value"]; }
		if (@$_GET["do"] == "change_is_ncs") { $fields["is_ncs"] = @$_GET["param_value"]; }
		if (@$_GET["do"] == "change_select_root_name") { $fields["select_root_name"] = $_GET["param_value"]; }
		if (@$_GET["do"] == "change_select_type") { $fields["select_type"] = $_GET["param_value"]; }
		if (@$_GET["do"] == "change_select_method") { $fields["select_method"] = $_GET["param_value"]; }
		if (@$_GET["do"] == "change_root_folder") { $fields["root_folder"] = $_GET["param_value"]; }
		if (@$_GET["do"] == "change_on_update") { $fields["on_update"] = $_GET["param_value"]; }
		if (@$_GET["do"] == "change_on_delete") { $fields["on_delete"] = $_GET["param_value"]; }
		if (@$_GET["do"] == "change_default_value") { $fields["default_value"] = $_GET["param_value"]; }

		if (!$error_string)
		{
			db_begin();
			cms_catch_db_error();
			$column = db_select_row("SELECT type, field_name, field_type FROM cms_metadata WHERE id = ?i", $_GET["field_id"]);
			if ($column !== false) { cms_edit_type_column($column["type"], $column["field_name"], $fields); }
			$error_string = cms_get_db_error();
			if ($error_string) { db_rollback(); } else { db_commit(); }
		}
	}
}

if (action() == "types-fields-copy")
{
	layout(false);
	$error_string = false;
	if (isset($_POST["destination_type"]) && isset($_POST["nodes"]) && is_array($_POST["nodes"]) && count($_POST["nodes"]))
	{
		db_begin();
		cms_catch_db_error();
		foreach ($_POST["nodes"] as $node_id)
		{
			$metadata = cms_get_column_metadata($node_id);
			if ($metadata !== false)
			{
				unset($metadata["id"]);
				$column_id = cms_create_type_column($_POST["destination_type"], $metadata);
				if (!$column_id) { break; }
			}
		}
		$error_string = cms_get_db_error();
		if ($error_string) { db_rollback(); } else { db_commit(); }
	}
}

if (action() == "types-fields-delete")
{
	layout(false);
	$error_string = false;
	if (isset($_POST["nodes"]) && is_array($_POST["nodes"]) && count($_POST["nodes"]))
	{
		db_begin();
		cms_catch_db_error();
		foreach ($_POST["nodes"] as $node_id) { cms_drop_type_column($_GET["id"], db_select_cell("SELECT field_name FROM cms_metadata WHERE id = ?i", $node_id)); }
		$error_string = cms_get_db_error();
		if ($error_string) { db_rollback(); } else { db_commit(); }
	}
}

if (action() == "langs-list")
{
	$where = array();

	if (isset($_GET["sort_cond"])) { $where["@order"] = $_GET["sort_cond"]; } else { $where["@order"] = "name ASC"; }

	$langs = db_select_all("
	SELECT
		lang,
		name,
		is_default
	FROM
		cms_languages
	", $where);
}

if (action() == "langs-edit" || action() == "langs-add")
{
	$_PAGE["main_bg"] = "threedface-bg";
	$_PAGE["body_overflow"] = "auto";

	$form = new FormProcessor();

	if (@$_POST["do"] == "save")
	{
		if ($form->check("lang", "entered,max_len[2]"))
		{
			if (action() == "langs-add" && false !== db_select_cell("SELECT lang FROM cms_languages WHERE lang = ?", $_POST["lang"])) { $form->set_error("lang", "Такой уже есть"); }
			if (action() == "langs-edit" && false !== db_select_cell("SELECT lang FROM cms_languages WHERE lang = ? AND lang != ?", $_POST["lang"], $_GET["id"])) { $form->set_error("lang", "Такой уже есть"); }
		}

		$form->check("name", "entered");

		if (@$_POST["is_default"])
		{
			if (action() == "langs-add" && false !== db_select_cell("SELECT lang FROM cms_languages WHERE is_default = 1")) { $form->set_error("is_default", "Уже есть"); }
			if (action() == "langs-edit" && false !== db_select_cell("SELECT lang FROM cms_languages WHERE is_default = 1 AND lang != ?", $_GET["id"])) { $form->set_error("is_default", "Уже есть"); }
		}

		if (!$form->is_form_error())
		{
			$fields = array
			(
				"lang" => $_POST["lang"],
				"name" => $_POST["name"],
				"is_default" => @$_POST["is_default"],
			);

			if (action() == "langs-add")
			{
				db_begin();
				cms_catch_db_error();
				db_insert("cms_languages", $fields);

				// Если указали сделать копию данных с некоевого языка - делаем :)
				if (!@is_empty($_POST["copy_from"]))
				{
					// Операция может быть достаточно длительной
					set_time_limit(0);
					// Выбираем все типы, которые у нас есть имеющие признак "языковый"
					$lang_types = db_select_col("SELECT type FROM cms_node_types WHERE is_lang = 1");
					// Копируем данные каждого типа
					foreach ($lang_types as $type) { copy_type_data($type, $_POST["copy_from"], $_POST["lang"]); }
				}

				$db_error = cms_get_db_error();
				if ($db_error) { $form->set_error("lang", $db_error); db_rollback(); } else { db_commit(); }
				if (!$form->is_form_error()) { redirect_to("action", "langs-list"); }
			}
			elseif (action() == "langs-edit")
			{
				db_begin();
				cms_catch_db_error();
				db_update("cms_languages", $fields, array("lang" => $_GET["id"]));
				$db_error = cms_get_db_error();
				if ($db_error) { $form->set_error("lang", $db_error); db_rollback(); } else { db_commit(); }
				if (!$form->is_form_error()) { redirect_to("action", "langs-list"); }
			}
		}
	}
	elseif (action() == "langs-edit")
	{
		$_POST = db_select_row("
		SELECT
			lang,
			name,
			is_default
		FROM
			cms_languages
		WHERE
			lang = ?
		", $_GET["id"]);
	}

	render_view("langs-form");
}

if (action() == "langs-delete")
{
	layout(false);

	$error_string = false;
	if (isset($_POST["nodes"]) && is_array($_POST["nodes"]) && count($_POST["nodes"]))
	{
		db_begin();
		cms_catch_db_error();
		foreach ($_POST["nodes"] as $lang) { db_delete("cms_languages", array("lang" => $lang)); }
		$error_string = cms_get_db_error();
		if ($error_string) { db_rollback(); } else { db_commit(); }
	}
}
