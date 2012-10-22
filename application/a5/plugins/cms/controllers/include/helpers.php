<?php
define("CMS_AUTH_CREATE", 1);
define("CMS_AUTH_UPDATE", 2);
define("CMS_AUTH_DELETE", 4);
define("CMS_AUTH_READ", 8);

function cms_is_blocked_ip($ip, $return = 0)
{
	$is_blocked = false;

	$result = db_query("
	SELECT
		ip,
		failed_count,
		EXTRACT(EPOCH FROM failed_time) as failed_time
	FROM
		cms_auth_blocks
	WHERE
		ip = ?
	", $ip);

	if (false !== $row = db_fetch($result))
	{
		if ($row["failed_count"] >= 3)
		{
			if (time() - $row["failed_time"] < 60 * 10) { $is_blocked = true; }
			else { db_delete("cms_auth_blocks", array("ip" => $ip)); $row = false; }
		}
	}

	if ($return == 0) { return $is_blocked; }
	if ($return == 1) { return $row ? 3 - $row["failed_count"] : 3; }
}

// Для текущего авторизованного пользователя
// Возвращает массив в котором ключ - тип объекта, значение - тип доступа к нему
// На основе текущих назначенных ролей пользователю
// Первым параметром можно передать название одного или массив названий типов
// По которым нас интересуют права
function cms_get_auth_types($types = null)
{
	global $_AUTH;
	static $cached_auth_types = null;
	if ($types === null && $cached_auth_types !== null) { return $cached_auth_types; }

	// Доступ к любому объекту по-умолчанию (если не назначено иное в одной из ролей)
	// Доступ по-умолчанию складывается из всех доступов по-умолчанию по каждой из ролей
	$default_auth = 0;

	$roles = db_select_all("
	SELECT
		id,
		default_auth
	FROM
		cms_auth_roles
	WHERE
		id IN (?i)
	", $_AUTH["roles"]);

	foreach ($roles as $role)
	{ $default_auth = ($default_auth | $role["default_auth"]); }

	$where = array();
	if ($types !== null) { $where["nt.type IN (?)"] = $types; }

	// Получаем список всех объектов и все доступы по каждой из ролей к ним
	$auths = db_select_all("
	SELECT
		nt.type,
		at.role_id as auth_role_id,
		at.auth as auth_type_auth
	FROM
		cms_node_types nt
		LEFT JOIN cms_auth_types at ON at.role_id IN (?i) AND at.type = nt.type
	", $_AUTH["roles"], $where);

	// Формируем массив где ключ - тип объекта, значение - тип доступа к данному типу объектов
	$auth_types = array();
	foreach ($auths as $auth)
	{
		// Если по данному типу нет информации - запоминаем это
		if (!array_key_exists($auth["type"], $auth_types)) { $auth_types[$auth["type"]] = null; }
		// Если для данного типа назначено особое право - сливаем его с предыдущим правом (если оно было)
		if ($auth["auth_role_id"] !== null)
		{
			if ($auth_types[$auth["type"]] !== null) { $auth_types[$auth["type"]] = ($auth_types[$auth["type"]] | $auth["auth_type_auth"]); }
			else { $auth_types[$auth["type"]] = $auth["auth_type_auth"]; }
		}
	}

	// Пробегаемся по всему массиву и там где неизвестно какие должны быть права назначаем правп по-умолчанию
	foreach ($auth_types as $type => $auth) { if ($auth === null) $auth_types[$type] = $default_auth; }

	// Кэшируем эту информацию для запросов по всем типам объектов
	if ($types === null && $cached_auth_types === null) { $cached_auth_types = $auth_types; }

	return $auth_types;
}

// Для текущего авторизованного пользователя
// Получает список объектов, на которые у него есть указанные права
// Если передан второй параметр то получает список объектов на которые НЕТ таких прав
function cms_get_types_list_for_auth($permission, $is_inverse = false)
{
	$auth_types = cms_get_auth_types();
	$types = array();
	foreach ($auth_types as $type => $type_auth)
	{
		// Если на этот объект есть запрашиваемые права
		if ($type_auth & $permission && !$is_inverse) { $types[] = $type; }
		elseif (!($type_auth & $permission) && $is_inverse) { $types[] = $type; }
	}
	return $types;
}

// Для текущего авторизованного пользователя
// Возвращает true если есть запрошенные права на указанный тип объектов false в другом случае
function cms_is_have_auth_for_type($type, $permission)
{
	$auth_types = cms_get_auth_types();
	if (array_key_exists($type, $auth_types) && ($auth_types[$type] & $permission)) { return true; }
	return false;
}

function cms_one_move_nodes($nodes, $direction, $move_style = 0)
{
	db_begin();

	if (!is_array($nodes)) { $nodes = array($nodes); }

	$nodes = db_select_col("
	SELECT
		id
	FROM
		cms_nodes
	WHERE
		id IN (?i)
	ORDER BY
		level,
		parent_id,
		sibling_index " . ($direction == "up" ? "ASC" : "DESC") . "
	", $nodes);

	$affected_nodes = array();
	foreach ($nodes as $node_id)
	{
		$item = db_select_row("
		SELECT
			id,
			name,
			parent_id,
			type,
			level,
			sibling_index
		FROM
			v_cms_nodes
		WHERE
			id = ?i
		", $node_id);

		if ($item !== false)
		{
			if (!cms_is_have_auth_for_type($item["type"], CMS_AUTH_UPDATE)) { return array("errmsg" => "Недостаточно прав для изменения документа \"" . $item["name"] . "\""); }

			$where = array();
			$where["n.parent_id"] = $item["parent_id"];
			// Всегда двигаем только между объектами одного и того же типа
			$where["n.type"] = $item["type"];

			// $move_style == 0 - двигаем все объекты
			// $move_style == 1 - двигаем только между объектами видимыми в дереве документов
			switch ($move_style)
			{
				case 1: $where["nt.is_hidden_tree"] = 0; break;
			}

			if ($direction == "up") { $where["n.sibling_index < ?i"] = $item["sibling_index"]; }
			else { $where["n.sibling_index > ?i"] = $item["sibling_index"]; }

			$where["@order"] = "n.sibling_index " . ($direction == "up" ? "DESC" : "ASC");
			$where["@limit"] = 1;

			$sibling_item = db_select_row("
			SELECT
				n.id,
				n.sibling_index
			FROM
				cms_nodes n
				JOIN cms_node_types nt ON n.type = nt.type
			", $where);

			if (false !== $sibling_item)
			{
				db_update("cms_nodes", array("sibling_index" => $sibling_item["sibling_index"]), array("id" => $item["id"]));
				db_update("cms_nodes", array("sibling_index" => $item["sibling_index"]), array("id" => $sibling_item["id"]));
				$affected_nodes[] = $item;
			}
		}
	}

	db_commit();
	return array("affected_nodes" => $affected_nodes);
}

function cms_full_move_nodes($nodes, $direction)
{
	db_begin();

	if (!is_array($nodes)) { $nodes = array($nodes); }

	$nodes = db_select_col("
	SELECT
		id
	FROM
		cms_nodes
	WHERE
		id IN (?i)
	ORDER BY
		level,
		parent_id,
		sibling_index " . ($direction == "up" ? "DESC" : "ASC") . "
	", $nodes);

	$affected_nodes = array();
	foreach ($nodes as $node_id)
	{
		$item = db_select_row("
		SELECT
			id,
			name,
			parent_id,
			type,
			level,
			sibling_index
		FROM
			v_cms_nodes
		WHERE
			id = ?i
		", $node_id);

		if ($item !== false)
		{
			if (!cms_is_have_auth_for_type($item["type"], CMS_AUTH_UPDATE)) { return array("errmsg" => "Недостаточно прав для изменения документа \"" . $item["name"] . "\""); }

			$where = array();
			$where["n.parent_id"] = $item["parent_id"];

			if ($direction == "up") { $where["n.sibling_index < ?i"] = $item["sibling_index"]; }
			else { $where["n.sibling_index > ?i"] = $item["sibling_index"]; }

			$where["@order"] = "n.sibling_index " . ($direction == "up" ? "ASC" : "DESC");
			$where["@limit"] = 1;

			$sibling_item = db_select_row("
			SELECT
				n.id,
				n.name,
				n.sibling_index
			FROM
				v_cms_nodes n
				JOIN cms_node_types nt ON n.type = nt.type
			", $where);

			if (false !== $sibling_item)
			{
				db_update("cms_nodes", array("sibling_index" => db_raw("sibling_index " . ($direction == "up" ? "+ 1" : "- 1"))), array("parent_id" => $item["parent_id"], "sibling_index " . ($direction == "up" ? "<" : ">") . " ?i" => $item["sibling_index"]));
				db_update("cms_nodes", array("sibling_index" => $sibling_item["sibling_index"]), array("id" => $item["id"]));
				$affected_nodes[] = $item;
			}
		}
	}

	db_commit();
	return array("affected_nodes" => $affected_nodes);
}

// Функция производит копирование $nodes (массив с id объектов), в качестве нового родительского берёться $parent_id
// Если копирование прошло успешно - возвращаеться true - иначе false
function cms_copy_nodes($nodes, $parent_id)
{
	if (!is_array($nodes)) { $nodes = array($nodes); }

	foreach ($nodes as $node_id)
	{
		$node = db_select_row("
		SELECT
			id,
			url_id,
			parent_id,
			type,
			controller,
			action,
			system_name,
			is_menuitem
		FROM
			cms_nodes
		WHERE
			id = ?i
		", $node_id);

		if (false !== $node)
		{
			$new_node = $node;
			$node_childs = db_select_col("SELECT id FROM v_cms_nodes WHERE parent_id = ?i ORDER BY sibling_index", $node["id"]);

			if (!cms_is_have_auth_for_type($new_node["type"], CMS_AUTH_CREATE))
			{
				db_run_error("Недостаточно прав для создания документа");
				return false;
			}

			// Подставляем новый parent
			$new_node["parent_id"] = $parent_id;

			// Если указан пользовательский url_id - проверяем не будет ли совпадения и если будет генерируем новое url_id
			if (!is_empty($new_node["url_id"]) && $new_node["url_id"] != $new_node["id"])
			{
				$idx = 0; $src = $new_node["url_id"]; $new_node["url_id"] = $src . "-" . ++$idx;
				while (false !== db_select_cell("SELECT url_id FROM cms_nodes WHERE type = ? AND url_id = ? AND id != ?i", $new_node["type"], $new_node["url_id"], $node["id"]))
				{ $new_node["url_id"] = $src . "-" . ++$idx; }
			}
			else { $new_node["url_id"] = ""; }

			// Если указан system_name - генерируем новый
			if (!is_empty($new_node["system_name"]))
			{
				$idx = 0; $src = $new_node["system_name"]; $new_node["system_name"] = $src . "-" . ++$idx;
				while (false !== db_select_cell("SELECT id FROM cms_nodes WHERE system_name = ?", $new_node["system_name"]))
				{ $new_node["system_name"] = $src . "-" . ++$idx; }
			}

			// Убираем id чтобы создать новую ноду
			unset($new_node["id"]);

			// Теперь копируем все данные для каждого из языков
			$langs = cms_get_all_languages();
			foreach ($langs as $lang)
			{
				cms_set_language($lang["lang"]);

				// Вытаскиваем общие данные
				$node_data = db_select_row("
				SELECT
					is_hidden,
					title,
					meta_keywords,
					meta_description
				FROM
					v_cms_nodes
				WHERE
					id = ?i
				", $node["id"]);

				if ($node_data !== false)
				{
					// Добавляем данные специфичных для типа полей
					$node_type_data = db_select_row("SELECT * FROM " . db_escape_ident("v_" . $node["type"]) . " WHERE id = ?i", $node["id"]);
					if ($node_type_data !== false)
					{
						$node_data = array_merge($node_data, $node_type_data);
						// Эти поля являются общими во вьюшке - они нам не нужны, т.к. уже есть в $new_node
						unset($node_data["id"]);
						unset($node_data["url_id"]);
						unset($node_data["parent_id"]);
						unset($node_data["system_name"]);
						unset($node_data["sibling_index"]);
						$new_node["id"] = set_node_data(array_merge($new_node, $node_data));
						if ($new_node["id"] === false) { break; }
					}
				}
			}

			if (isset($new_node["id"]) && $new_node["id"])
			{
				// Если нода имеет каких-то специфических детей, копируем эту информацию тоже
				db_query("
				INSERT INTO
					cms_node_childs
					(
						id,
						child_type,
						sibling_index
					)
				SELECT
					?i,
					child_type,
					sibling_index
				FROM
					cms_node_childs
				WHERE
					id = ?i
				", $new_node["id"], $node["id"]);

				// Теперь копируем всех детей объекта
				if ($node_childs !== false && count($node_childs)) { cms_copy_nodes($node_childs, $new_node["id"]); }
			}
		}
		else
		{
			db_run_error("Исходный документ не существует");
			return false;
		}
	}
}

// Функция производит перемещение $nodes (массив с id объектов), в качестве нового родительского берёться $parent_id
// Если перемещение прошло успешно - возвращаеться true - иначе false
function cms_move_nodes($nodes, $parent_id)
{
	if (!is_array($nodes)) { $nodes = array($nodes); }

	foreach ($nodes as $node_id)
	{
		$node = db_select_row("
		SELECT
			id,
			type
		FROM
			cms_nodes
		WHERE
			id = ?i
		", $node_id);

		if (false !== $node)
		{
			if (!cms_is_have_auth_for_type($node["type"], CMS_AUTH_UPDATE))
			{
				db_run_error("Недостаточно прав для изменения документа \"" . (db_select_cell("SELECT name FROM v_cms_nodes WHERE id = ?i", $node_id)) . "\"");
				return false;
			}
			set_node_data(array("id" => $node["id"], "parent_id" => $parent_id));
		}
		else
		{
			db_run_error("Исходный документ не существует");
			return false;
		}
	}
}

// Функция для проверки прав на удаление объекта(ов)
// Возвращает true - если есть права на удаление, иначе false
// Сообщение об ошибке можно получить из $GLOBALS["php_errormsg"]
function cms_check_auth_for_delete($nodes)
{
	if (!is_array($nodes)) { $nodes = array($nodes); }

	foreach ($nodes as $node_id)
	{
		// Список объектов, которые мы НЕ можем удалять
		$auth_types = cms_get_types_list_for_auth(CMS_AUTH_DELETE, true);

		// Документ и список его дочерних документов, которые не можем удалять
		$child_node = db_select_row("
		SELECT
			n.id,
			n.name
		FROM
			v_cms_nodes n
			JOIN cms_get_nodes_tree(?i) t ON n.id = t.node_id
		WHERE
			n.type IN (?)
		ORDER BY
			n.mt_path
		LIMIT
			1
		", $node_id, $auth_types);

		if (false !== $child_node)
		{
			$GLOBALS["php_errormsg"] = "Недостаточно прав для удаления документа \"" . $child_node["name"] . "\"";
			return false;
		}
	}

	return true;
}

// Возвращает список типов которые может иметь документ с указанным id
function cms_get_child_types($node_id)
{
	$child_types = array();

	if (false !== $node = db_select_row("SELECT id, type FROM cms_nodes WHERE id = ?i", $node_id))
	{
		// Собственные дочерние типы
		$child_types = db_select_col("
		SELECT
			nc.child_type
		FROM
			cms_node_childs nc
			JOIN cms_node_types nt ON nt.type = nc.child_type
		WHERE
			nc.id = ?i
		ORDER BY
			nc.sibling_index,
			nc.child_type
		", $node["id"]);

		if (!count($child_types))
		{
			$child_types = db_select_col("
			SELECT
				ntc.child_type
			FROM
				cms_node_type_childs ntc
				JOIN cms_node_types nt ON nt.type = ntc.child_type
			WHERE
				ntc.type = ?
			ORDER BY
				ntc.sibling_index,
				ntc.child_type
			", $node["type"]);
		}
	}

	return $child_types;
}

function cms_get_genders_list()
{
	$genders = Array
	(
		"ru" => Array("Жен", "Муж"),
		"en" => Array("Female", "Male"),
	);
	if (array_key_exists(cms_get_language(), $genders)) { return $genders[cms_get_language()]; }
	else { return $genders["ru"]; }
}

function cms_get_gender($gender)
{
	$genders = cms_get_genders_list();
	return $gedners[$gender];
}

function cms_list_header_field($name, $display_name, $attr = array())
{
	if ($name !== null)
	{
		$sort_cond = $name;
		$is_already_sorted = false;
		$sort_direction = null;
		// Если уже имеется какое-то условие сортировки
		if (isset($_GET["sort_cond"]))
		{
			$sort_chunks = explode(",", $_GET["sort_cond"]);
			foreach ($sort_chunks as $i => $sort_chunk)
			{
				if (preg_match("/^\s*(.*)\s+(asc|desc)\s*$/sixu", $sort_chunk, $matches))
				{ $sort_field = $matches[1]; $sort_direction = $matches[2]; }
				else { $sort_field = trim($sort_chunk); $sort_direction = "ASC"; }
				// Если какая-либо сортировка по этому полю уже есть
				if ($sort_field == $name)
				{
					$is_already_sorted = true;
					if ($sort_direction == "DESC") { unset($sort_chunks[$i]); } else { $sort_chunks[$i] = $sort_field . " DESC"; }
					break;
				}
			}
			if (!$is_already_sorted) { $sort_chunks[] = $name; }
			if (count($sort_chunks)) { $sort_cond = implode(",", $sort_chunks); } else { $sort_cond = null; }
		}
	}

	$attr_string = null;
	foreach ($attr as $key => $value) { $attr_string .= " " . h($key) . "=\"" . h($value) . "\""; }

	if ($name !== null)
	{
		?>
		<th onclick="location.href = '<?= url_for("@overwrite", true, "@jsescape", true, "sort_cond", $sort_cond) ?>';"
		<? if ($is_already_sorted): ?>class="<?= $sort_direction == "DESC" ? "sorted_desc" : "sorted_asc" ?>"<? endif ?><?= $attr_string ?>><?= h($display_name) ?></th>
		<?
	}
	else { ?><th<?= $attr_string ?>><?= h($display_name) ?></th><? }
}

function cms_db_error_handler($is_manual)
{
	if (@is_empty($GLOBALS["__cms__"]["latest_db_error"]))
	{ $GLOBALS["__cms__"]["latest_db_error"] = ($is_manual ? db_last_manual_error() : db_last_error(true) . "\nQuery: " . db_last_query()); }
	return true;
}

function cms_catch_db_error()
{
	db_add_error_handler('cms_db_error_handler');
	$GLOBALS["__cms__"]["latest_db_error"] = false;
}

function cms_get_db_error()
{
	db_clear_error_handler();
	return $GLOBALS["__cms__"]["latest_db_error"];
}

function cms_get_column_types()
{
	return array
	(
		"string" => array("name" => "Строка", "dbtype" => "varchar(255)", "dbmarker" => "?"),
		"email" => array("name" => "E-mail", "dbtype" => "varchar(128)", "dbmarker" => "?"),
		"password" => array("name" => "Пароль", "dbtype" => "varchar(64)", "dbmarker" => "?"),
		"url" => array("name" => "URL", "dbtype" => "varchar(255)", "dbmarker" => "?"),
		"plain_text" => array("name" => "Простой текст", "dbtype" => "text", "dbmarker" => "?"),
		"html" => array("name" => "HTML", "dbtype" => "text", "dbmarker" => "?"),
		"date" => array("name" => "Дата", "dbtype" => "date", "dbmarker" => "?d"),
		"time" => array("name" => "Время", "dbtype" => "time", "dbmarker" => "?t"),
		"datetime" => array("name" => "Дата + Время", "dbtype" => "timestamptz", "dbmarker" => "?dt"),
		"boolean" => array("name" => "Флаг (Да/Нет)", "dbtype" => "smallint", "dbmarker" => "?b"),
		"age" => array("name" => "Возраст", "dbtype" => "integer", "dbmarker" => "?i"),
		"integer" => array("name" => "Целое число", "dbtype" => "bigint", "dbmarker" => "?i"),
		"filesize" => array("name" => "Число байтов", "dbtype" => "bigint", "dbmarker" => "?i"),
		"number" => array("name" => "НЕ целое число", "dbtype" => "numeric(30,15)", "dbmarker" => "?r"),
		"sum" => array("name" => "Сумма (деньги)", "dbtype" => "numeric(15,2)", "dbmarker" => "?r"),
		"price" => array("name" => "Цена (деньги)", "dbtype" => "numeric(15,2)", "dbmarker" => "?r"),
		"cost" => array("name" => "Стоимость (деньги)", "dbtype" => "numeric(15,2)", "dbmarker" => "?r"),
		"gender" => array("name" => "Пол (0-Ж, 1-М)", "dbtype" => "smallint", "dbmarker" => "?i"),
		"blob" => array("name" => "Бинарные данные", "dbtype" => "bytea", "dbmarker" => "?B"),
		"object" => array("name" => "Объект", "dbtype" => "bigint", "dbmarker" => "?i"),
		"image" => array("name" => "Картинка", "dbtype" => "varchar(255)", "dbmarker" => "?"),
		"file" => array("name" => "Файл", "dbtype" => "varchar(255)", "dbmarker" => "?"),
		"flash" => array("name" => "Флэш", "dbtype" => "varchar(255)", "dbmarker" => "?"),
		"fti" => array("name" => "Поле для FTI", "dbtype" => "tsvector", "dbmarker" => "?"),
	);
}

function cms_create_type($type, $params)
{
	db_begin();

	$params["type"] = $type = trim($type);
	db_insert("cms_node_types", $params);

	if (isset($type))
	{
		db_query("
		CREATE TABLE " . db_escape_ident($type) . "
		(
			lang CHAR(2) REFERENCES cms_languages(lang) ON UPDATE CASCADE ON DELETE CASCADE,
			id BIGINT NOT NULL REFERENCES cms_nodes(id) ON UPDATE CASCADE ON DELETE CASCADE,
			url_id varchar(128),
			parent_id BIGINT REFERENCES cms_nodes(id) ON UPDATE CASCADE ON DELETE CASCADE,
			system_name varchar(32),
			is_hidden SMALLINT NOT NULL DEFAULT 0,
			sibling_index BIGINT
		) WITHOUT OIDS
		");

		db_query("CREATE UNIQUE INDEX " . db_escape_ident($type . "_id_and_lang_uniq_key") . " ON " . db_escape_ident($type) . " (\"id\", \"lang\")");
		db_query("CREATE UNIQUE INDEX " . db_escape_ident($type . "_id_uniq_key") . " ON " . db_escape_ident($type) . " (\"id\") WHERE lang IS NULL");
		db_query("CREATE INDEX " . db_escape_ident($type . "_lang_is_null_idx") . " ON " . db_escape_ident($type) . " (\"lang\") WHERE lang IS NULL");
		db_query("CREATE INDEX " . db_escape_ident($type . "_url_id_idx") . " ON " . db_escape_ident($type) . " (\"url_id\")");
		db_query("CREATE INDEX " . db_escape_ident($type . "_parent_id_idx") . " ON " . db_escape_ident($type) . " (\"parent_id\")");
		db_query("CREATE INDEX " . db_escape_ident($type . "_system_name_idx") . " ON " . db_escape_ident($type) . " (\"system_name\")");
		db_query("CREATE INDEX " . db_escape_ident($type . "_is_hidden_idx") . " ON " . db_escape_ident($type) . " (\"is_hidden\")");
		db_query("CREATE INDEX " . db_escape_ident($type . "_sibling_index_idx") . " ON " . db_escape_ident($type) . " (\"sibling_index\")");

		db_query("
		CREATE TRIGGER " . db_escape_ident($type . "_update_sf_biud_tr") . " BEFORE INSERT OR UPDATE OR DELETE
		ON " . db_escape_ident($type) . " FOR EACH ROW EXECUTE PROCEDURE \"cms_update_sf_biud_tr_func\"()
		");

		cms_create_or_replace_type_view($type);
	}

	db_commit();
}

function cms_edit_type($type, $params)
{
	db_begin();

	$type = trim($type);
	if (isset($params["type"])) { $params["type"] = trim($params["type"]); }

	$is_type_renamed = false;
	$is_lang_changed = false;
	if (array_key_exists("type", $params) && $type != $params["type"]) { $is_type_renamed = true; }
	if (array_key_exists("is_lang", $params)) { $is_lang_changed = (!!db_select_cell("SELECT is_lang FROM cms_node_types WHERE type = ?", $type) !== !!$params["is_lang"]); }

	if ($is_type_renamed)
	{
		cms_drop_type_view($type);
		db_query("ALTER TABLE cms_nodes DISABLE TRIGGER USER");
	}

	db_update("cms_node_types", $params, array("type" => $type));

	if ($is_type_renamed) { db_query("ALTER TABLE cms_nodes ENABLE TRIGGER USER"); }

	// Если переименовали имя типа
	if ($is_type_renamed)
	{
		// Переименуем все ограничения
		$constraints = db_select_all("
		SELECT
			c.conname as name,
			pg_get_constraintdef(c.oid) as def
		FROM
			pg_constraint c
		WHERE
			conrelid = ?::regclass
		", $type);

		foreach ($constraints as $constr)
		{
			db_query("ALTER TABLE " . db_escape_ident($type) . " DROP CONSTRAINT " . db_escape_ident($constr["name"]) . " RESTRICT");
			db_query("ALTER TABLE " . db_escape_ident($type) . " ADD CONSTRAINT " . db_escape_ident(preg_replace("/^" . preg_quote($type, "/") . "_/u", $params["type"] . "_", $constr["name"])) . " " . $constr["def"]);
		}

		// Переименуем все её индексы
		$indexes = db_select_all("
		SELECT
		    a.relname as name
		FROM
		    pg_index i
		    JOIN pg_class a ON a.oid = i.indexrelid
		WHERE
		    i.indrelid = ?::regclass
		", $type);

		foreach ($indexes as $index)
		{ db_query("ALTER INDEX " . db_escape_ident($index["name"]) . " RENAME TO " . db_escape_ident(preg_replace("/^" . preg_quote($type, "/") . "_/u", $params["type"] . "_", $index["name"]))); }

		// Переименуем всё её триггеры
		$triggers = db_select_all("
		SELECT
		    t.tgname as name
		FROM
		    pg_trigger t
		WHERE
		    t.tgrelid = ?::regclass
		    AND NOT t.tgisconstraint
		", $type);

		foreach ($triggers as $trigger)
		{ db_query("ALTER TRIGGER " . db_escape_ident($trigger["name"]) . " ON " . db_escape_ident($type) . " RENAME TO " . db_escape_ident(preg_replace("/^" . preg_quote($type, "/") . "_/u", $params["type"] . "_", $trigger["name"]))); }

		// Переименуем таблицу
		db_query("ALTER TABLE " . db_escape_ident($type) . " RENAME TO " . db_escape_ident($params["type"]));
	}

	// Если изменился флаг "Языковый", нужно пробежаться по всем полям, которые имеют статус "уникальное"
	// и если флаг "Языковый" установили - то нужно изменить все уникальные индексы с учётом поля "язык"
	// если же флаг "Языковый" сняли - то наоборот нужно убрать учитывание поля "язык" для таких индексов
	if ($is_lang_changed)
	{
		$unique_fields = db_select_all("SELECT field_name, is_uniq, is_ncs FROM cms_metadata WHERE type = ? AND is_uniq = 1", $params["type"]);
		foreach ($unique_fields as $column)
		{
			// Удаляем старый индекс
			cms_drop_type_index($params["type"], $column["field_name"], $column["is_uniq"], $column["is_ncs"]);
			cms_create_type_index($params["type"], $params["is_lang"], $column["field_name"], $column["is_uniq"], $column["is_ncs"]);
		}
	}

	cms_create_or_replace_type_view($params["type"]);

	db_commit();
}

function cms_drop_type($type)
{
	db_begin();

	$type = db_select_cell("SELECT type FROM cms_node_types WHERE type = ?", $type);
	if ($type !== false)
	{
		db_query("DROP VIEW " . db_escape_ident("v_" . $type));
		db_query("DROP TABLE " . db_escape_ident($type));
		db_query("DELETE FROM cms_node_types WHERE type = ?", $type);
	}

	db_commit();
}

function cms_create_type_index($type, $is_lang, $field_name, $is_uniq, $is_ncs)
{
	$index_name = $type . "_" . $field_name;
	$index_cond = db_escape_ident($field_name);

	if ($is_ncs)
	{
		$index_name .= "_ncs";
		$index_cond = "lower(" . db_escape_ident($field_name) . ")";
	}

	if ($is_uniq)
	{
		$index_name .= "_uniq";
		if ($is_lang) { $index_cond .= ", \"lang\""; }
	}

	db_query("CREATE " . ($is_uniq ? "UNIQUE" : null) . " INDEX " . db_escape_ident($index_name . "_idx") . " ON " . db_escape_ident($type) . " (" . $index_cond . ")");
}

function cms_drop_type_index($type, $field_name, $is_uniq, $is_ncs)
{
	$index_name = $type . "_" . $field_name;
	if ($is_ncs) { $index_name .= "_ncs"; }
	if ($is_uniq) { $index_name .= "_uniq"; }
	db_query("DROP INDEX " . db_escape_ident($index_name . "_idx"));
}

function cms_get_column_metadata($id)
{
	$columns = cms_get_columns(array("m.id = ?i" => $id));
	if (count($columns)) { return reset($columns); } else { return false; }
}

function cms_get_type_columns($type, $where = array()) { return cms_get_columns(array_merge(array("m.type" => $type), $where)); }

function cms_get_columns($where = array())
{
	if (!isset($where["@key"])) { $where["@key"] = "id"; }
	if (!isset($where["@order"])) { $where["@order"] = "m.id"; }

	return db_select_all("
	SELECT
		*,
		split_part(split_part(format_type(a.atttypid, a.atttypmod), '(', 2), ')', 1) as field_type_length
	FROM
		cms_metadata m
		LEFT JOIN pg_attribute a ON
		(
			a.attrelid = m.type::regclass
			AND a.attname = m.field_name
			AND a.attnum > 0
			AND NOT a.attisdropped
		)
	", $where);
}

function cms_create_type_column($type, $column)
{
	db_begin();

	$column_id = null;
	$column["type"] = $type = trim($type);
	$field_types = cms_get_column_types();
	$type_info = db_select_row("SELECT * FROM cms_node_types WHERE type = ?", $type);

	// Если поле уникальное или без различия регистра - должно индексироваться
	if (@$column["is_ncs"] || @$column["is_uniq"]) { @$column["is_idx"] = 1; }

	// boolean поле обязано иметь дефолт-значение
	if (@$column["field_type"] == "boolean" && @is_empty($column["default_value"])) { $column["default_value"] = 0; }

	$insert_fields = array();
	$table_columns = array_keys(db_get_field_types("cms_metadata"));

	// Фильтруем список полей
	foreach ($table_columns as $column_name)
	{ if (array_key_exists($column_name, $column)) $insert_fields[$column_name] = $column[$column_name]; }

	if (isset($column["field_name"]) && isset($column["field_type"]))
	{
		if (!isset($field_types[$column["field_type"]])) { db_run_error("Unknown field type '" . @h($column["field_type"]) . "'"); }

		$column_id = db_insert("cms_metadata", $insert_fields);

		$field_dbtype = $field_types[$column["field_type"]]["dbtype"];
		if (!@is_empty($column["field_type_length"])) { $field_dbtype = preg_replace("/^([a-z][a-z0-9_]+)(?:\([0-9,]+\))?(.*)$/suxi", "$1(" . $column["field_type_length"] . ")$3", $field_dbtype); }

		if ($column["field_type"] == "object")
		{
			$field_dbtype .= " REFERENCES cms_nodes(id)";
			if (!@is_empty($column["on_update"])) { $field_dbtype .= " ON UPDATE " . $column["on_update"]; }
			if (!@is_empty($column["on_delete"])) { $field_dbtype .= " ON DELETE " . $column["on_delete"]; }
		}

		db_query("ALTER TABLE " . db_escape_ident($type) . " ADD COLUMN " . db_escape_ident($column["field_name"]) . " " . $field_dbtype);

		// Если указано дефолт-значение
		if (!@is_empty($column["default_value"]))
		{
			db_query("
			ALTER TABLE " . db_escape_ident($type) . "
			ALTER COLUMN " . db_escape_ident($column["field_name"]) . "
			SET DEFAULT " . trim($column["default_value"]) . "
			");
		}

		// Если это boolean поле или это обязательное поле и оно не являеться FTI типом
		if ($column["field_type"] == "boolean" || (@$column["is_req"] && $column["field_type"] != "fti"))
		{
			// Перед установкой того что оно не пустое - пытаемся заполнить дефолт-значениями при условии что дефолт-значение имеется
			if (!@is_empty($column["default_value"])) { db_update($type, array($column["field_name"] => db_raw("DEFAULT"))); }
			db_query("
			ALTER TABLE " . db_escape_ident($type) . "
			ALTER COLUMN " . db_escape_ident($column["field_name"]) . " SET NOT NULL
			");
		}

		if ($column["field_type"] != "fti")
		{
			if (@$column["is_idx"] || @$column["is_uniq"])
			{ cms_create_type_index($type, $type_info["is_lang"], $column["field_name"], @$column["is_uniq"], @$column["is_ncs"]); }
		}

		// Для поля типа FTI нужно создать GIST индекс
		if ($column["field_type"] == "fti")
		{
			if (!isset($column["fti_fields"])) { $column["fti_fields"] = null; }
			db_query("
			CREATE INDEX " . db_escape_ident($type . "_" . $column["field_name"] . "_idx") . "
			ON " . db_escape_ident($type) . " USING gin (" . db_escape_ident($column["field_name"]) . ")
			");

			$fti_fields = explode(",", $column["fti_fields"]);
			array_walk($fti_fields, function(&$item) { $item = db_escape_ident(trim($item)); });

			// Получим дефолтный конфиг словарей поиска и создадим на его основе триггер
			// В будущем нужно будет это исправить и сделать возможность выбора
			$text_search_config = db_select_cell("SELECT CURRENT_SETTING('default_text_search_config')");

			db_query("
			CREATE TRIGGER " . db_escape_ident($type . "_" . $column["field_name"] . "_biu") . " BEFORE INSERT OR UPDATE
			ON " . db_escape_ident($type) . " FOR EACH ROW
			EXECUTE PROCEDURE \"tsvector_update_trigger\" (" . db_escape_ident($column["field_name"]) . ", " . db_str($text_search_config) . ", " . implode(",", $fti_fields) . ")
			");

			// (для заполнения FTI-полей данными)
			db_update($type, array("id" => db_raw("id")));
		}
	}

	cms_create_or_replace_type_view($type);

	db_commit();

	return $column_id;
}

function cms_edit_type_column($type, $column_name, $params)
{
	$field_types = cms_get_column_types();

	$old_column = db_select_row("
	SELECT
		m.*,
		split_part(split_part(format_type(a.atttypid, a.atttypmod), '(', 2), ')', 1) as field_type_length
	FROM
		cms_metadata m
		LEFT JOIN pg_attribute a ON
		(
			a.attrelid = m.type::regclass
			AND a.attname = m.field_name
			AND a.attnum > 0
			AND NOT a.attisdropped
		)
	WHERE
		m.type = ?
		AND m.field_name = ?
	", $type, $column_name);

	if ($old_column !== false)
	{
		array_walk($params, function(&$item) { $item = trim($item); });
		$new_column = array_merge($old_column, $params);

		if (!isset($field_types[$new_column["field_type"]])) { db_run_error("Unknown field type '" . @h($new_column["field_type"]) . "'"); }

		db_begin();

		// Если изменилось имя поля
		if ($old_column["name"] != $new_column["name"])
		{
			db_update("cms_metadata", array("name" => $new_column["name"]), array("id" => $new_column["id"]));
			cms_create_or_replace_type_view($new_column["type"]);
		}

		// Если изменилось sql-имя поля
		if ($old_column["field_name"] != $new_column["field_name"])
		{
			db_update("cms_metadata", array("field_name" => $new_column["field_name"]), array("id" => $new_column["id"]));

			db_query("
			ALTER TABLE " . db_escape_ident($new_column["type"]) . "
			RENAME COLUMN " . db_escape_ident($old_column["field_name"]) . " TO " . db_escape_ident($new_column["field_name"]) . "
			");

			// Переименуем все индексы связанные с этим полем
			$indexes = db_select_all("
			SELECT
			    a.relname as name
			FROM
			    pg_index i
			    JOIN pg_class a ON a.oid = i.indexrelid
			WHERE
			    i.indrelid = ?::regclass
			    AND a.relname LIKE ?like
			", $new_column["type"], $new_column["type"] . "\_" . $old_column["field_name"] . "\_%");

			foreach ($indexes as $index)
			{ db_query("ALTER INDEX " . db_escape_ident($index["name"]) . " RENAME TO " . db_escape_ident(preg_replace("/^" . preg_quote($new_column["type"], "/") . "_" . preg_quote($old_column["field_name"], "/") . "_/u", $new_column["type"] . "_" . $new_column["field_name"] . "_", $index["name"]))); }

			// Если тип поля - fti - нужно также переименовать триггер с учётом нового названия
			if ($old_column["field_type"] == "fti")
			{ db_query("ALTER TRIGGER " . db_escape_ident($new_column["type"] . "_" . $old_column["field_name"] . "_biu") . " ON " . db_escape_ident($new_column["type"]) . " RENAME TO " . db_escape_ident($new_column["type"] . "_" . $new_column["field_name"] . "_biu")); }

			cms_create_or_replace_type_view($new_column["type"]);
		}

		// Имя огарничения foreign key для поля типа объект
		$constraint_name = null;
		if ($old_column["field_type"] == "object")
		{
			// Получим имя foreign key ограничения для поля типа object
			$constraint_name = db_select_cell("
			SELECT
				c.conname
			FROM
				pg_constraint c
				LEFT JOIN pg_attribute a ON	a.attrelid = c.conrelid	AND a.attnum = ANY(c.conkey) AND NOT a.attisdropped
			WHERE
				c.contype = 'f'
				AND c.conrelid = ?::regclass
				AND a.attname = ?
			", $new_column["type"], $new_column["field_name"]);
		}

		// Если изменился тип поля или его длина
		if
		(
			$old_column["field_type"] != $new_column["field_type"]
			|| $old_column["field_type_length"] != $new_column["field_type_length"]
		)
		{
			cms_drop_type_view($new_column["type"]);

			// Если изменился типа поля
			if ($old_column["field_type"] != $new_column["field_type"])
			{
				// Если старый тип - объект и есть ограничение - удалим его
				if ($old_column["field_type"] == "object" && !is_empty($constraint_name))
				{ db_query("ALTER TABLE " . db_escape_ident($new_column["type"]) . " DROP CONSTRAINT " . db_escape_ident($constraint_name) . " RESTRICT"); }

				db_update("cms_metadata", array("field_type" => $new_column["field_type"]), array("id" => $new_column["id"]));
			}

			$field_dbtype = $field_types[$new_column["field_type"]]["dbtype"];
			if (!@is_empty($new_column["field_type_length"])) { $field_dbtype = preg_replace("/^([a-z][a-z0-9_]+)(?:\([0-9,]+\))?(.*)$/suxi", "$1(" . $new_column["field_type_length"] . ")$3", $field_dbtype); }

			// Выполняем sql модификации
			db_query("ALTER TABLE " . db_escape_ident($new_column["type"]) . " ALTER COLUMN " . db_escape_ident($new_column["field_name"]) . " TYPE " . $field_dbtype);

			cms_create_or_replace_type_view($new_column["type"]);
		}

		// Если изменился тип поля или тип каскада
		if
		(
			$old_column["field_type"] != $new_column["field_type"]
			|| $old_column["on_update"] != $new_column["on_update"]
			|| $old_column["on_delete"] != $new_column["on_delete"]
		)
		{
			if ($new_column["field_type"] == "object")
			{
				if ($old_column["on_update"] != $new_column["on_update"]) { db_update("cms_metadata", array("on_update" => $new_column["on_update"]), array("id" => $new_column["id"])); }
				if ($old_column["on_delete"] != $new_column["on_delete"]) { db_update("cms_metadata", array("on_delete" => $new_column["on_delete"]), array("id" => $new_column["id"])); }

				// Если ограничение есть - удалим его
				if (!is_empty($constraint_name)) { db_query("ALTER TABLE " . db_escape_ident($new_column["type"]) . " DROP CONSTRAINT " . db_escape_ident($constraint_name) . " RESTRICT"); }

				// Теперь создадим новое ограничение
				$foreign_key_def = "FOREIGN KEY (" . db_escape_ident($new_column["field_name"]) . ") REFERENCES cms_nodes(id)";
				if (!@is_empty($new_column["on_update"])) { $foreign_key_def .= " ON UPDATE " . $new_column["on_update"]; }
				if (!@is_empty($new_column["on_delete"])) { $foreign_key_def .= " ON DELETE " . $new_column["on_delete"]; }

				// Выполняем sql модификации
				db_query("ALTER TABLE " . db_escape_ident($new_column["type"]) . " ADD " . $foreign_key_def);
			}
		}

		// поле boolean обязано иметь дефолт-значение
		if ($new_column["field_type"] == "boolean" && is_empty($new_column["default_value"])) { $new_column["default_value"] = 0; }

		// Если изменилось поле "по-умолчанию"
		if ($old_column["default_value"] != $new_column["default_value"])
		{
			db_update("cms_metadata", array("default_value" => $new_column["default_value"]), array("id" => $new_column["id"]));
			if (!is_empty($new_column["default_value"]))
			{
				db_query("
				ALTER TABLE " . db_escape_ident($new_column["type"]) . "
				ALTER COLUMN " . db_escape_ident($new_column["field_name"]) . "
				SET DEFAULT " . trim($new_column["default_value"]) . "
				");
			}
			else
			{
				db_query("
				ALTER TABLE " . db_escape_ident($new_column["type"]) . "
				ALTER COLUMN " . db_escape_ident($new_column["field_name"]) . "
				DROP DEFAULT
				");
			}
		}

		// Если изменился флаг "Обязательное"
		if ($old_column["is_req"] != $new_column["is_req"])
		{
			db_update("cms_metadata", array("is_req" => $new_column["is_req"]), array("id" => $new_column["id"]));
			if (!in_array($new_column["field_type"], array("boolean", "fti")))
			{
				if ($new_column["is_req"])
				{
					// Если имеется дефолт-значение - заполним им пустые поля
					if (!is_empty($new_column["default_value"]))
					{ db_update($new_column["type"], array($new_column["field_name"] => db_raw("DEFAULT")), array(db_escape_ident($new_column["field_name"]) . " IS NULL")); }
					db_query("
					ALTER TABLE " . db_escape_ident($new_column["type"]) . "
					ALTER COLUMN " . db_escape_ident($new_column["field_name"]) . " SET NOT NULL
					");
				}
				else
				{
					db_query("
					ALTER TABLE " . db_escape_ident($new_column["type"]) . "
					ALTER COLUMN " . db_escape_ident($new_column["field_name"]) . " DROP NOT NULL
					");
				}
			}
		}

		if
		(
			$old_column["is_idx"] != $new_column["is_idx"]
			|| $old_column["is_uniq"] != $new_column["is_uniq"]
			|| $old_column["is_ncs"] != $new_column["is_ncs"]
		)
		{
			// Флаг зависимости от регистра не может быть установлен если поле не индексируемое
			if ($old_column["is_ncs"] != $new_column["is_ncs"] && $new_column["is_ncs"]) { $new_column["is_idx"] = 1; }
			// Если изменили флаг индексируемое и поставили что не индексируемое - то и не уникальное
			if ($old_column["is_idx"] != $new_column["is_idx"] && !$new_column["is_idx"]) { $new_column["is_uniq"] = 0; $new_column["is_ncs"] = 0; }
			// Если изменился флаг уникальное и поставили уникальное - то и индексируемое
			if ($old_column["is_uniq"] != $new_column["is_uniq"] && $new_column["is_uniq"]) { $new_column["is_idx"] = 1; }

			$type_is_lang = db_select_cell("SELECT is_lang FROM cms_node_types WHERE type = ?", $new_column["type"]);

			db_update("cms_metadata", array
			(
				"is_idx" => $new_column["is_idx"],
				"is_uniq" => $new_column["is_uniq"],
				"is_ncs" => $new_column["is_ncs"],
			), array("id" => $new_column["id"]));

			// Надо удалить старый индекс если он был так как либо мы создадим новый индекс, либо вообще создавать не будем
			if ($old_column["is_idx"]) { cms_drop_type_index($old_column["type"], $old_column["field_name"], @$old_column["is_uniq"], @$old_column["is_ncs"]); }

			//  Если нужен индекс
			if ($new_column["is_idx"]) { cms_create_type_index($new_column["type"], $type_is_lang, $new_column["field_name"], @$new_column["is_uniq"], @$new_column["is_ncs"]); }
		}

		// Поля ни с чем связанные
		db_update("cms_metadata", array(
		"select_root_name" => $new_column["select_root_name"],
		"select_type" => $new_column["select_type"],
		"select_method" => $new_column["select_method"],
		"root_folder" => $new_column["root_folder"],
		), array("id" => $new_column["id"]));

		db_commit();
	}
}

function cms_drop_type_column($type, $column)
{
	$type = trim($type);
	$column = db_select_row("SELECT id, field_name, field_type FROM cms_metadata WHERE type = ? AND field_name = ?", $type, $column);
	if ($column !== false)
	{
		db_begin();
		db_delete("cms_metadata", array("id" => $column["id"]));
		cms_create_or_replace_type_view($type);
		if ($column["field_type"] == "fti") { db_query("DROP TRIGGER " . db_escape_ident($type . "_" . $column["field_name"] . "_biu") . " ON " . db_escape_ident($type)); }
		db_query("ALTER TABLE " . db_escape_ident($type) . " DROP COLUMN " . db_escape_ident($column["field_name"]));
		db_commit();
	}
}

function cms_create_or_replace_type_view($type)
{
	db_begin();

	$type = db_select_row("SELECT type, is_lang FROM cms_node_types WHERE type = ?", $type);
	if ($type === false) { return false; }

	$type_fields = array_keys(cms_get_type_fields($type["type"]));
	cms_drop_type_view($type["type"]);

	$view_ddl = "CREATE OR REPLACE VIEW " . db_escape_ident("v_" . $type["type"]) . " AS
	SELECT
		t.id,
		t.url_id,
		t.parent_id,
		t.system_name,";

	foreach ($type_fields as $field_name)
	{
		$view_ddl .= "
		t." . db_escape_ident($field_name) . ",";
	}

	$view_ddl .= "
		t.sibling_index
	FROM
		cms_get_view_mode() vm(vm)
		" . ($type["is_lang"] ? "JOIN cms_get_current_language() cl(cl) ON 0 = 0" : "") . "
		JOIN " . db_escape_ident($type["type"]) . " t ON " . ($type["is_lang"] ? "t.lang = cl.cl" : "t.lang IS NULL") . "
	WHERE
		vm.vm = 0
		OR (vm.vm = 1 AND t.is_hidden = 0)
	";

	if (false !== db_query($view_ddl))
	{
		db_query("COMMENT ON VIEW " . db_escape_ident("v_" . $type["type"]) . " IS ?", ltrim_lines($view_ddl));

		$insert_rule_ddl = "
		CREATE OR REPLACE RULE " . db_escape_ident("v_" . $type["type"] . "_insert_rl") . " AS ON INSERT TO " . db_escape_ident("v_" . $type["type"]) . "
		DO INSTEAD
		(
			INSERT INTO
				cms_nodes
				(
					url_id,
					parent_id,
					system_name,
					type
				)
				VALUES
				(
					new.url_id,
					new.parent_id,
					new.system_name,
					" . db_str($type["type"]) . "
				);
			INSERT INTO
				" . db_escape_ident($type["type"]) . "
				(
					lang,
					id";

		foreach ($type_fields as $field_name)
		{
					$insert_rule_ddl .= ",
					" . db_escape_ident($field_name);
		}

		$insert_rule_ddl .= "
				)
				VALUES
				(
					" . ($type["is_lang"] ? "cms_get_current_language()" : "NULL") . ",
					currval('cms_nodes_id_seq')";

		foreach ($type_fields as $field_name)
		{
					$insert_rule_ddl .= ",
					new." . db_escape_ident($field_name);
		}

		$insert_rule_ddl .= "
				);
		);";

		// Правило для вставки во вьюшку
		db_query($insert_rule_ddl);
		db_query("COMMENT ON RULE " . db_escape_ident("v_" . $type["type"] . "_insert_rl") . " ON " . db_escape_ident("v_" . $type["type"]) . " IS ?", ltrim_lines($insert_rule_ddl));

		$update_rule_ddl = "
		CREATE OR REPLACE RULE " . db_escape_ident("v_" . $type["type"] . "_update_rl") . " AS ON UPDATE TO " . db_escape_ident("v_" . $type["type"]) . "
		DO INSTEAD
		(
			UPDATE
				cms_nodes
			SET
				url_id = new.url_id,
				parent_id = new.parent_id,
				system_name = new.system_name,
				sibling_index = new.sibling_index
			WHERE
				id = new.id;";

		// Если у типа нет ни одного поля - соотвественно нечего обновлять :)
		if (count($type_fields))
		{
			$update_rule_ddl .= "
			UPDATE
				" . db_escape_ident($type["type"]) . "
			SET";

			foreach ($type_fields as $i => $field_name)
			{
				$update_rule_ddl .= ($i ? "," : "") . "
				" . db_escape_ident($field_name) . " = new." . db_escape_ident($field_name);
			}

			$update_rule_ddl .= "
			WHERE
				id = new.id
				AND \"lang\" " . ($type["is_lang"] ? "= cms_get_current_language()" : "IS NULL") . ";";
		}

		$update_rule_ddl .= ");";

		// Правило для обновления во вьюшке
		db_query($update_rule_ddl);
		db_query("COMMENT ON RULE " . db_escape_ident("v_" . $type["type"] . "_update_rl") . " ON " . db_escape_ident("v_" . $type["type"]) . " IS ?", ltrim_lines($update_rule_ddl));

		$delete_rule_ddl = "
		CREATE OR REPLACE RULE " . db_escape_ident("v_" . $type["type"] . "_delete_rl") . " AS ON DELETE TO " . db_escape_ident("v_" . $type["type"]) . "
		DO INSTEAD (DELETE FROM cms_nodes WHERE cms_nodes.id = OLD.id;);
		";
		// Правило при удалении из вьюшки
		db_query($delete_rule_ddl);
		db_query("COMMENT ON RULE " . db_escape_ident("v_" . $type["type"] . "_delete_rl") . " ON " . db_escape_ident("v_" . $type["type"]) . " IS ?", ltrim_lines($delete_rule_ddl));

		db_commit();
		return true;
	}
	else
	{
		db_rollback();
		return false;
	}
}

function cms_drop_type_view($type)
{
	$type = db_select_row("SELECT type, is_lang FROM cms_node_types WHERE type = ?", $type);
	if ($type === false) { return false; }
	return (@db_query("DROP VIEW IF EXISTS " . db_escape_ident("v_" . $type["type"])) ? true : false);
}

function cms_valid_controller($val) { return preg_match("/^[a-zA-Z0-9_\/-]+$/su", trim($val)); }
function cms_valid_action($val) { return preg_match("/^[a-zA-Z0-9_-]+$/su", trim($val)); }
function cms_valid_system_name($val) { return preg_match("/^" . CMS_SYSNAME_REGEX . "$/su", trim($val)); }
function cms_valid_url_id($val) { return preg_match("/^[a-z0-9-]+$/u", trim($val)); }

function pick_image($name, $root_folder = null)
{
	static $pick_num = 0;
	$pick_num++;

	if (!$root_folder) { $root_folder = CMS_MEDIA_IMAGE_DIR; }
	else { $root_folder = normalize_path(CMS_MEDIA_IMAGE_DIR . "/" . $root_folder); }

	$is_image = false;
	$image["src"] = CMS_PUBLIC_URI . "/pics/spacer.gif";
	$image["width"] = "1";
	$image["height"] = "1";

	if (isset($_POST[$name]) && false !== $info = @getimagesize(PUBLIC_DIR . "/" . $_POST[$name]))
	{
		$is_image = true;
		$image["src"] = url_for($_POST[$name]);
		$image["width"] = $info[0];
		$image["height"] = $info[1];
	}
	?>
	<script type="text/javascript">
	function image_picker_set_<?= h($pick_num) ?>(url, params)
	{
		var div = $('image_picker_view_<?= h($pick_num) ?>');
		if (div) { div.innerHTML = '<img src="' + url + '" style="border: none" alt="">'; }
		var el = $('image_picker_field_<?= h($pick_num) ?>');
		if (el) { el.value = url; }
		var el = $('image_picker_clear_button_<?= h($pick_num) ?>');
		if (el) { el.style.display = ''; }
	}

	function image_picker_clear_<?= h($pick_num) ?>()
	{
		var div = $('image_picker_view_<?= h($pick_num) ?>');
		if (div) { div.innerHTML = '<img src="<?= CMS_PUBLIC_URI ?>/pics/spacer.gif" style="width: 1px; height: 1px; border: none" alt="">'; }
		var el = $('image_picker_field_<?= h($pick_num) ?>');
		if (el) { el.value = ''; }
		var el = $('image_picker_clear_button_<?= h($pick_num) ?>');
		if (el) { el.style.display = 'none'; }
	}
	</script>
	<table style="width: 100%;">
	<tr>
		<td class="threedface-bg">
			<input type="hidden" name="<?= h($name) ?>" id="image_picker_field_<?= h($pick_num) ?>">
			<input type="button" value="Обзор..."  onclick="browser_window('<?= url_for("-controller", "browser", "callback", "opener.image_picker_set_" . $pick_num, "root_path", substr_ltrim($root_folder, CMS_MEDIA_DIR . "/"), "find_path", isset($_POST[$name]) ? $_POST[$name] : null) ?>');">
			<input type="button" value="Убрать" id="image_picker_clear_button_<?= h($pick_num) ?>" <? if (!$is_image): ?>style="display: none;"<? endif ?> onclick="image_picker_clear_<?= h($pick_num) ?>();">
		<td>
	</tr>
	<tr>
		<td class="appworkspace-bg" style="width: 100%; height: 150px; vertical-align: top;">
			<div id="image_picker_view_<?= h($pick_num) ?>" style="width: 100%; height: 100%; overflow: auto;">
			<img src="<?= h($image["src"]) ?>" alt="" style="width: <?= h($image["width"]) ?>px; height: <?= h($image["height"]) ?>px; border: none;">
			</div>
		</td>
	</tr>
	</table>
	<?
}

function pick_flash($name, $root_folder = null)
{
	static $pick_num = 0;
	$pick_num++;

	if (!$root_folder) { $root_folder = CMS_MEDIA_FLASH_DIR; }
	else { $root_folder = normalize_path(CMS_MEDIA_FLASH_DIR . "/" . $root_folder); }

	$flash = array();
	if (isset($_POST[$name]) && false !== $info = @getimagesize(PUBLIC_DIR . "/" . $_POST[$name]))
	{
		$flash["src"] = url_for($_POST[$name]);
		$flash["width"] = $info[0];
		$flash["height"] = $info[1];
	}
	?>
	<script type="text/javascript">
	function flash_picker_set_<?= h($pick_num) ?>(url, params)
	{
		write_swf_object(url, params['width'], params['height'], {id: 'flash_picker_flash_content_<?= h($pick_num) ?>'});
		var el = $('flash_picker_field_<?= h($pick_num) ?>');
		if (el) { el.value = url; }
		var el = $('flash_picker_clear_button_<?= h($pick_num) ?>');
		if (el) { el.style.display = ''; }
	}

	function flash_picker_clear_<?= h($pick_num) ?>()
	{
		var div = $('flash_picker_view_<?= h($pick_num) ?>');
		if (div) { div.innerHTML = '<div id="flash_picker_flash_content_<?= j(h($pick_num)) ?>"></div>'; }
		var el = $('flash_picker_field_<?= h($pick_num) ?>');
		if (el) { el.value = ''; }
		var el = $('flash_picker_clear_button_<?= h($pick_num) ?>');
		if (el) { el.style.display = 'none'; }
	}
	</script>
	<table style="width: 100%;">
	<tr>
		<td class="threedface-bg">
			<input type="hidden" name="<?= h($name) ?>" id="flash_picker_field_<?= h($pick_num) ?>">
			<input type="button" value="Обзор..."  onclick="browser_window('<?= url_for("-controller", "browser", "callback", "opener.flash_picker_set_" . $pick_num, "root_path", substr_ltrim($root_folder, CMS_MEDIA_DIR . "/"), "find_path", isset($_POST[$name]) ? $_POST[$name] : null) ?>');">
			<input type="button" value="Убрать" id="flash_picker_clear_button_<?= h($pick_num) ?>" <? if (!$flash): ?>style="display: none;"<? endif ?> onclick="flash_picker_clear_<?= h($pick_num) ?>();">
		</td>
	</tr>
	<tr>
		<td class="appworkspace-bg" style="width: 100%; height: 150px; vertical-align: top;">
			<div id="flash_picker_view_<?= h($pick_num) ?>" style="width: 100%; height: 100%; overflow: auto;">
				<div id="flash_picker_flash_content_<?= h($pick_num) ?>"></div>
				<? if ($flash): ?>
					<script type="text/javascript">
					write_swf_object('<?= j($flash["src"]) ?>', <?= j($flash["width"]) ?>, <?= j($flash["height"]) ?>, {id: 'flash_picker_flash_content_<?= h($pick_num) ?>'});
					</script>
				<? endif ?>
			</div>
		</td>
	</tr>
	</table>
	<?
}

function pick_file($name, $root_folder = null)
{
	static $pick_num = 0;
	$pick_num++;

	if (!$root_folder) { $root_folder = CMS_MEDIA_FILES_DIR; }
	else { $root_folder = normalize_path(CMS_MEDIA_FILES_DIR . "/" . $root_folder); }

	$file_name = "<НЕТ ФАЙЛА>";
	$file_url = null;
	if (isset($_POST[$name]))
	{
		$file_path = normalize_path(PUBLIC_DIR . "/" . $_POST[$name]);
		if (file_exists($file_path))
		{
			$file_name = basename($file_path) . " (" . human_size($file_path) . ")";
			$file_url = $_POST[$name];
		}
	}
	?>
	<script type="text/javascript">
	function file_picker_set_<?= h($pick_num) ?>(url, params)
	{
		var elem = $('file_picker_view_<?= h($pick_num) ?>');
		if (elem) { elem.value = params['name'] + ' (' + params['human_size'] + ')'; }
		var elem = $('file_picker_field_<?= h($pick_num) ?>');
		if (elem) { elem.value = url; }
		var el = $('file_picker_download_anchor_<?= h($pick_num) ?>');
		if (el)
		{
			el.style.display = '';
			el.href = '<?= url_for("-controller", "main", "action", "download", "@jsescape", "true", "@escape", false) ?>?path=' + encodeURIComponent(url);
		}
		var el = $('file_picker_clear_button_<?= h($pick_num) ?>');
		if (el) { el.style.display = ''; }
	}

	function file_picker_clear_<?= h($pick_num) ?>()
	{
		var el = $('file_picker_view_<?= h($pick_num) ?>');
		if (el) { el.value = '<НЕТ ФАЙЛА>'; }
		var el = $('file_picker_field_<?= h($pick_num) ?>');
		if (el) { el.value = ''; }
		var el = $('file_picker_download_anchor_<?= h($pick_num) ?>');
		if (el) { el.style.display = 'none'; }
		var el = $('file_picker_clear_button_<?= h($pick_num) ?>');
		if (el) { el.style.display = 'none'; }
	}
	</script>
	<span style="white-space: nowrap;">
	<input type="hidden" name="<?= h($name) ?>" id="file_picker_field_<?= h($pick_num) ?>">
	<input type="text" readonly="readonly" id="file_picker_view_<?= h($pick_num) ?>" value="<?= h($file_name) ?>" style="width: 300px;">
	<a href="<?= url_for("-controller", "main", "action", "download", "path", $file_url) ?>" id="file_picker_download_anchor_<?= h($pick_num) ?>" target="_blank" title="Скачать" <? if ($file_url === null): ?>style="display: none;"<? endif ?>><img src="<?= h(CMS_PUBLIC_URI . "/pics/download.gif") ?>" style="width: 16px; height: 16px; border: 0px; vertical-align: -3px"></a>
	<input type="button" value="Обзор..." onclick="browser_window('<?= url_for("-controller", "browser", "callback", "opener.file_picker_set_" . $pick_num, "root_path", substr_ltrim($root_folder, CMS_MEDIA_DIR . "/"), "find_path", isset($_POST[$name]) ? $_POST[$name] : null) ?>');">
	<input type="button" value="Убрать" id="file_picker_clear_button_<?= h($pick_num) ?>" <? if ($file_url === null): ?>style="display: none;"<? endif ?> onclick="file_picker_clear_<?= h($pick_num) ?>();">
	</span>
	<?
}

function pick_url($name, $root_node_id = null)
{
	static $pick_num = 0;
	$pick_num++;
	?>
	<script type="text/javascript">
	var url_picker_<?= h($pick_num) ?> = '<?= url_for("@escape", false, "@jsescape", true, "-controller", "catalog", "id", $root_node_id, "callback", "opener.url_picker_set_" . $pick_num, "lang", isset($_POST["lang"]) ? $_POST["lang"] : null) ?>';
	function url_picker_set_<?= h($pick_num) ?>(url, params)
	{
		var elem = $('url_picker_field_<?= h($pick_num) ?>');
		if (elem) { elem.value = '<?= CMS_URL_MARKER_PREFIX ?>' + url + '<?= CMS_URL_MARKER_SUFFIX ?>'; }
	}
	function url_picker_get_url_<?= h($pick_num) ?>()
	{
		var elem = $('url_picker_field_<?= h($pick_num) ?>');
		if (elem)
		{
			var matches = elem.value.match(/^<?= preg_quote(CMS_URL_MARKER_PREFIX, "/") ?>(<?= CMS_ID_SYSNAME_REGEX ?>)<?= preg_quote(CMS_URL_MARKER_SUFFIX, "/") ?>$/);
			if (matches) { return append_url_param(url_picker_<?= h($pick_num) ?>, 'expand_to', matches[1]); } else { return url_picker_<?= h($pick_num) ?>; }
		}
	}
	</script>
	<input type="text" name="<?= h($name) ?>" style="width: 300px;" id="url_picker_field_<?= h($pick_num) ?>">
	<input type="button" title="Выбрать документ сайта" value="Обзор..." onclick="node_picker_window(url_picker_get_url_<?= h($pick_num) ?>());">
	<?
}

function html_editor($name)
{
	$use_ckeditor = false;
	if ($use_ckeditor)
	{
		require_once(PUBLIC_DIR . "/" . CMS_PUBLIC_URI . "/ckeditor/ckeditor.php");

		$ck = new CKEditor();

		$ck->basePath = url_for(CMS_PUBLIC_URI . "/ckeditor/", "@escape", false);

		$ck->config["baseHref"] = BASE_URL;
		$ck->config["width"] = "100%";
		$ck->config["height"] = "@@document.body.offsetHeight";
		$ck->config["customConfig"] = url_for(CMS_PUBLIC_URI . "/ckconfig.js");

		$ck->config["filebrowserImageBrowseUrl"] = url_for("@jsescape", true, "@escape", false, "-controller", "browser", "root_path", substr_ltrim(CMS_MEDIA_IMAGE_DIR, CMS_MEDIA_DIR . "/"), "fck_callback", "image");
		$ck->config["filebrowserFlashBrowseUrl"] = url_for("@jsescape", true, "@escape", false, "-controller", "browser", "root_path", substr_ltrim(CMS_MEDIA_FLASH_DIR, CMS_MEDIA_DIR . "/"), "fck_callback", "flash");
		$ck->config["filebrowserLinkBrowseUrl"] = url_for("@jsescape", true, "@escape", false, "-controller", "browser", "fck_callback", "link");
		$ck->config["filebrowserDocumentsBrowseUrl"] = url_for("@escape", false, "-controller", "catalog", "fck_callback", "node");

		$ck->editor($name, @$_POST[$name]);
	}
	else
	{
		require_once(PUBLIC_DIR . "/" . CMS_PUBLIC_URI . "/fckeditor/fckeditor.php");
		$fck = new FCKeditor($name);

		$fck->BasePath = url_for(CMS_PUBLIC_URI . "/fckeditor/", "@escape", false);
		$fck->Width	= "100%";
		$fck->Height = "100%";

		$fck->Value	= @$_POST[$name];
		$fck->Config["BaseHref"] = BASE_URL;

		$fck->Config["ImageBrowserURL"] = url_for("@escape", false, "-controller", "browser", "root_path", substr_ltrim(CMS_MEDIA_IMAGE_DIR, CMS_MEDIA_DIR . "/"), "fck_callback", "image");
		$fck->Config["FlashBrowserURL"] = url_for("@escape", false, "-controller", "browser", "root_path", substr_ltrim(CMS_MEDIA_FLASH_DIR, CMS_MEDIA_DIR . "/"), "fck_callback", "flash");

		$fck->Config["LinkBrowserURL"] = url_for("@escape", false, "-controller", "browser", "fck_callback", "link");
		$fck->Config["LinkBrowserDocumentsCatalogURL"] = url_for("@escape", false, "-controller", "catalog", "fck_callback", "node");

		$fck->Config["CustomConfigurationsPath"] = url_for(CMS_PUBLIC_URI . "/fckconfig.js");
		$fck->ToolbarSet = "CMS";

		$fck->Create();
	}
}

// Вывод и редактирование дочерних веток, объектов и прочего
// $node_id - родительская ветка (обычно текущая редактирумеая)
// $type - тип детей, если не указан, будет возможность выбора, иначе - только этот тип детей
function child_list($node_id, $type = null, $select_method = "linear")
{
	if ($select_method == "linear")
	{
		?>
		<iframe style="width: 100%; height: 100%;" frameborder="0" border="0" src="<?= url_for("-controller", "list", "id", $node_id, "only_type", $type, "lang", isset($_POST["lang"]) ? $_POST["lang"] : null) ?>"></iframe>
		<?
	}
	else
	{
		?>
		<iframe style="width: 100%; height: 100%;" frameborder="0" border="0" src="<?= url_for("-controller", "catalog", "id", $node_id, "only_type", $type, "lang", isset($_POST["lang"]) ? $_POST["lang"] : null) ?>"></iframe>
		<?
	}
}

function pick_node($name, $root, $type = null, $method = "linear")
{
	static $pick_num = 0;
	$pick_num++;
	$root = $root ? get_id_by_system_name($root) : null;
	if ($method == "linear_dropdown")
	{
		$where = array();
		$where["type"] = $type;
		if (!is_empty($root)) { $where["parent_id"] = $root; }
		$where["@order"] = "sibling_index";
		$nodes = db_select_all("SELECT id, name FROM v_cms_nodes", $where);
		?>
		<select name="<?= h($name) ?>">
		<option value="">---</option>
		<? foreach ($nodes as $item): ?>
			<option value="<?= h($item["id"]) ?>"><?= h($item["name"]) ?></option>
		<? endforeach ?>
		</select>
		<?
	}
	else
	{
		$node_id = null;
		$node_name = "<НЕ ВЫБРАНО>";
		if (isset($_POST[$name]))
		{
			$item = db_select_row("SELECT id, name FROM v_cms_nodes WHERE id = ?i", $_POST[$name]);
			if ($item !== false)
			{
				$node_id = $item["id"];
				$node_name = $item["name"];
			}
		}
		?>
		<script type="text/javascript">
		function node_picker_set_<?= h($pick_num) ?>(url, params)
		{
			var elem = $('node_picker_view_<?= h($pick_num) ?>');
			if (elem) { elem.value = params['name']; }
			var elem = $('node_picker_field_<?= h($pick_num) ?>');
			if (elem) { elem.value = url; }
			var el = $('node_picker_clear_button_<?= h($pick_num) ?>');
			if (el) { el.style.display = ''; }
		}

		function node_picker_clear_<?= h($pick_num) ?>()
		{
			var el = $('node_picker_view_<?= h($pick_num) ?>');
			if (el) { el.value = '<НЕ ВЫБРАНО>'; }
			var el = $('node_picker_field_<?= h($pick_num) ?>');
			if (el) { el.value = ''; }
			var el = $('node_picker_clear_button_<?= h($pick_num) ?>');
			if (el) { el.style.display = 'none'; }
		}
		</script>
		<input type="hidden" name="<?= h($name) ?>" id="node_picker_field_<?= h($pick_num) ?>">
		<input type="text" readonly="readonly" id="node_picker_view_<?= h($pick_num) ?>" value="<?= h($node_name) ?>" style="width: 200px;">
		<?
		if ($method == "linear")
		{ $url = url_for("-controller", "list", "only_type", $type, "callback", "opener.node_picker_set_" . $pick_num, "id", $root ? get_id_by_system_name($root) : null, "lang", isset($_POST["lang"]) ? $_POST["lang"] : null); }
		else
		{ $url = url_for("-controller", "catalog", "only_type", $type, "callback", "opener.node_picker_set_" . $pick_num, "id", $root ? get_id_by_system_name($root) : null, "lang", isset($_POST["lang"]) ? $_POST["lang"] : null); }
		?>
		<input type="button" value="Обзор..." onclick="node_picker_window('<?= $url ?>');">
		<input type="button" value="Убрать" id="node_picker_clear_button_<?= h($pick_num) ?>" <? if (!$node_id): ?>style="display: none;"<? endif ?> onclick="node_picker_clear_<?= h($pick_num) ?>();">
		<?
	}
}

// Отключает набор полей при редактировании - при вызове без
// параметров - возвращает текущий набор скрытых полей
function disable_fields()
{
	static $disabled_fields = array();
	$args = func_get_args();
	if (count($args))
	{
		$fields = array();
		$is_disable = true;
		foreach ($args as $arg)
		{
			if (is_bool($arg)) { $is_disable = $arg; }
			else { $fields = array_merge($fields, (array) $arg); }
		}

		if ($is_disable) { $disabled_fields = array_merge($disabled_fields, $fields); }
		else
		{
			foreach ($disabled_fields as $i => $field_name)
			{
				if (in_array($field_name, $fields))
				{ unset($disabled_fields[$i]); }
			}
		}

		$disabled_fields = array_unique($disabled_fields);
	}
	return $disabled_fields;
}

// Отключает набор общих полей - которые находятся сверху в
// стандартной форме редактирования
function disable_shared_fields($is_disabled = true) { disable_fields("url_id", "is_hidden", "is_menuitem", "node_url", !!$is_disabled); }

// Отключает набор полей находящихся на вкладке дополнительно
function disable_additional_tab($is_disabled = true) { disable_fields("title", "meta_keywords", "meta_description", !!$is_disabled); }

// Отключает вывод указанных дочерних типов при редактировании
function disable_childs()
{
	static $disabled_childs = array();
	$args = func_get_args();
	if (count($args))
	{
		$childs = array();
		$is_disable = true;
		foreach ($args as $arg)
		{
			if (is_bool($arg)) { $is_disable = $arg; }
			else { $childs = array_merge($childs, (array) $arg); }
		}

		if ($is_disable) { $disabled_childs = array_merge($disabled_childs, $childs); }
		else
		{
			foreach ($disabled_childs as $i => $child_name)
			{
				if (in_array($child_name, $childs))
				{ unset($disabled_childs[$i]); }
			}
		}

		$disabled_childs = array_unique($disabled_childs);
	}
	return $disabled_childs;
}