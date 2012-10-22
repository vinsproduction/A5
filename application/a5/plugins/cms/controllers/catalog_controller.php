<?php
require_once("include/setup.php");

// Если корневая нода пустая - считаем что её вообще не указали
if (isset($_GET["id"]) && !$_GET["id"]) { unset($_GET["id"]); }

if (action() == "index") { layout(CMS_PREFIX . "/frames"); }

if (action() == "tree")
{
	if (isset($_GET["expand_to"]))
	{
		$_GET["expand_to"] = db_select_cell("SELECT id FROM v_cms_nodes WHERE id = ?i OR system_name = ?", $_GET["expand_to"], $_GET["expand_to"]);
		if ($_GET["expand_to"] === false) { unset($_GET["expand_to"]); }
	}
}

if (action() == "expand-tree")
{
	layout(false);

	// В этом массиве содержиться id нод дочерние которых нужно загрузить в любом случае
	$load_node_childs = array();
	if (@is_array($_POST["load_node_childs"]))
	{
		$load_node_childs = array_flip($_POST["load_node_childs"]);
		array_walk($load_node_childs, function(&$item) { $item = true; });
	}

	$queue_nodes = array();
	$load_node_childs_distinct = array();

	if (isset($_POST["to_nodes"]))
	{
		foreach ($_POST["to_nodes"] as $node_id)
		{
			$parent_nodes = db_select_all("
			SELECT
				n.id as id
			FROM
				cms_get_node_parents(?i) p
				JOIN v_cms_nodes n on n.id = p.node_id
			ORDER BY
				p.node_level
			", $node_id);

			// Проверка необходимости загрузки ноды "root"
			$is_load_node_childs = false;

			// Проверим нет ли среди нод, которые попросили загрузить ноды
			// с пустым id - если есть - значит нужно грузить "root"
			if (@$load_node_childs[""] && !isset($load_node_childs_distinct["root"]))
			{
				$is_load_node_childs = true;
				$load_node_childs_distinct["root"] = true;
			}

			// Проверим нет ли среди родительских ветвей, таких которые попросили загрузить
			// и которые при этом находяться за пределами текущего корня каталога
			// если такие есть - значит нужно установить флаг для загрузки дочерних ветвей
			// корневой ноды
			if (!$is_load_node_childs)
			{
				foreach ($parent_nodes as $node)
				{
					if (isset($_GET["id"]) && $node["id"] == $_GET["id"]) { break; }
					if (@$load_node_childs[$node["id"]] && !isset($load_node_childs_distinct["root"]))
					{
						$is_load_node_childs = true;
						$load_node_childs_distinct["root"] = true;
						break;
					}
				}
			}

			$queue_nodes[] = array
			(
				"id" => "root",
				"load" => $is_load_node_childs,
				"level" => 0,
			);

			$current_level = 0;
			foreach ($parent_nodes as $node)
			{
				if ($node["id"] == $node_id && @!$_POST["to_node_inclusive"]) { break; }
				if ((isset($_GET["id"]) && !count($queue_nodes) && $node["id"] == $_GET["id"]) || count($queue_nodes))
				{
					$is_load_node_childs = false;
					if (@$load_node_childs[$node["id"]] && !isset($load_node_childs_distinct[$node["id"]]))
					{
						$is_load_node_childs = true;
						$load_node_childs_distinct[$node["id"]] = true;
					}

					$queue_nodes[] = array
					(
						"id" => $node["id"],
						"load" => $is_load_node_childs,
						"level" => $current_level,
					);
					$current_level++;
				}
			}
		}
	}
}

if (action() == "load-childs")
{
	layout(false);

	if (isset($_GET["parent_node_id"]))
	{
		$root_node_level = isset($_GET["id"]) ? db_select_cell("SELECT level FROM v_cms_nodes WHERE id = ?i", $_GET["id"]) : 0;

		// Список типов, на которые у нас есть права на чтение
		$auth_types = cms_get_types_list_for_auth(CMS_AUTH_READ);

		if (isset($_GET["lang"])) { cms_set_language($_GET["lang"]); }

		$where = array();

		if ($_GET["parent_node_id"] == "root")
		{
			if (isset($_GET["id"])) { $where["n.id = ?i"] = $_GET["id"]; }
			else { $where[] = "n.parent_id IS NULL"; }
		}
		else { $where["n.parent_id = ?i"] = $_GET["parent_node_id"]; }

		if (!@$_GET["show_full_tree"] && $_GET["parent_node_id"] != "root") { $where[] = "nt.is_hidden_tree = 0"; }

		$where["n.type IN (?)"] = $auth_types;
		$where["@order"] = "n.type, n.sibling_index";

		$subwhere = array();

		$subwhere[] = "cn.parent_id = n.id";
		if (!@$_GET["show_full_tree"]) { $subwhere[] = "cnt.is_hidden_tree = 0"; }
		$subwhere["cn.type IN (?)"] = $auth_types;
		$subwhere["@limit"] = 1;

		$nodes = db_select_all("
		SELECT
			n.id as id,
			n.level - " . db_int($root_node_level) . " as level,
			n.name as name,
			n.parent_id as parent_id,
			n.system_name as system_name,
			n.type as type,
			nt.name as type_name,
			nt.name_list as type_name_list,
			nt.is_hidden_tree,
			nt.icon as icon,
			n.sibling_index,
			CASE WHEN EXISTS
			(
				SELECT
					cn.id
				FROM
					v_cms_nodes cn
					JOIN cms_node_types cnt ON cnt.type = cn.type
				?filter
			) THEN 1 ELSE 0 END as child_count
		FROM
			v_cms_nodes n
			JOIN cms_node_types nt ON nt.type = n.type
		", $subwhere, $where);

		$is_many_types = false;
		$distinct_types = array();
		foreach ($nodes as $item)
		{
			if (!isset($distinct_types[$item["type"]]))
			{ $distinct_types[$item["type"]] = true; }
		}
		if (count(array_keys($distinct_types)) > 1) { $is_many_types = true; }
	}
}

if (action() == "move")
{
	layout(false);

	if (@$_POST["do"] == "one_move")
	{ $status = cms_one_move_nodes($_POST["nodes"], $_POST["direction"], @$_GET["show_full_tree"] ? 0 : 1); }

	if (@$_POST["do"] == "full_move")
	{ $status = cms_full_move_nodes($_POST["nodes"], $_POST["direction"]); }
}

if (action() == "delete")
{
	layout(false);

	if (isset($_POST["nodes"]))
	{
		$error_message = "";
		$affected_nodes = array();

		$root_node_level = isset($_GET["id"]) ? db_select_cell("SELECT level FROM v_cms_nodes WHERE id = ?i", $_GET["id"]) : 0;

		// Перед удалением - получим список parent_id удаляемых нод,
		// т.к. после удаления нам нужно будет раскрыть дерево каталога
		// до каждого из этого parent_id включая его дочерние ноды
		$parents = db_select_col("SELECT DISTINCT parent_id FROM cms_nodes WHERE id IN (?i) AND level >= ?i", $_POST["nodes"], $root_node_level);
		foreach ($parents as $parent_id) { if ($parent_id) $affected_nodes[] = $parent_id; }

		// Теперь получим список родителей этих родительских нод,
		// Т.к. возможно после удаления у родительских веток не останеться детей
		// И нужно перегрузить их родительские ветки чтобы избавиться от значка "+/-"
		$parents = db_select_col("SELECT DISTINCT parent_id FROM cms_nodes WHERE id IN (?i)", $parents);
		foreach ($parents as $parent_id) { if ($parent_id) $affected_nodes[] = $parent_id; }

		$result = cms_check_auth_for_delete($_POST["nodes"]);
		if ($result) { $result = cms_delete_nodes($_POST["nodes"]); }
		if ($result === false) { $error_message = $GLOBALS["php_errormsg"]; }

		// Оставим только те parent_id которые остались после удаления
		$affected_nodes = db_select_col("SELECT id FROM cms_nodes WHERE id IN (?i) AND level >= ?i", array_unique($affected_nodes), $root_node_level);
		// Если в итоге не осталось ни одного родителя которого нужно подгрузить - грузим "root"
		if (!count($affected_nodes)) { $affected_nodes[] = ""; }
		// Иначе если среди родителей есть корневая ветка - нужно также подгрузить и "root"
		elseif (isset($_GET["id"]) && in_array($_GET["id"], $affected_nodes)) { $affected_nodes[] = ""; }
	}
	else { render_nothing(); }
}