<?php
require_once("include/setup.php");

if (!$_AUTH["is_admin"]) { render_text("You are not authorized to view this page"); }

if (action() == "users-list")
{
	$where = array();

	if (isset($_GET["sort_cond"])) { $where["@order"] = $_GET["sort_cond"]; } else { $where["@order"] = "id ASC"; }

	$roles = db_select_all("
	SELECT
		id,
		name
	FROM
		cms_auth_roles
	ORDER BY
		name
	");

	$users = db_select_all("
	SELECT
		au.id,
		au.login,
		au.is_admin
	FROM
		cms_auth_users au
	", $where);

	foreach ($users as $i => $item)
	{
		$users[$i]["roles"] = implode(", ", db_select_col("
		SELECT
			ar.name
		FROM
			cms_auth_user_roles aur
			JOIN cms_auth_roles ar ON ar.id = aur.role_id
		WHERE
			aur.user_id = ?i",
		$item["id"]));
	}
}

if (action() == "users-delete")
{
	layout(false);
	if (isset($_POST["nodes"]) && is_array($_POST["nodes"]) && count($_POST["nodes"]))
	{ db_delete("cms_auth_users", array("id IN (?i)" => $_POST["nodes"])); }
}

if (action() == "users-edit" || action() == "users-add")
{
	$_PAGE["main_bg"] = "threedface-bg";

	$form = new FormProcessor();

	if (@$_POST["do"] == "save")
	{
		if ($form->check("login", "entered"))
		{
			if
			(
				(action() == "users-add" && false !== db_select_cell("SELECT id FROM cms_auth_users WHERE login = ?", $_POST["login"]))
				||
				(action() == "users-edit" && false !== db_select_cell("SELECT id FROM cms_auth_users WHERE login = ? AND id != ?i", $_POST["login"], $_GET["id"]))
			)
			{ $form->set_error("login", "Такой логин уже есть"); }
		}

		if (action() == "users-add")
		{
			if ($form->check("pass", "entered") && $form->check("pass2", "entered") && trim($_POST["pass"]) != trim($_POST["pass2"]))
			{ $form->set_error("pass", "Пароли не совпадают"); }
		}

		if (action() == "users-edit")
		{
			if ($form->validate("pass", "entered"))
			{
				if ($form->check("pass2", "entered") && trim($_POST["pass"]) != trim($_POST["pass2"]))
				{ $form->set_error("pass", "Пароли не совпадают"); }
			}
		}

		if (!$form->is_form_error())
		{
			$fields = array
			(
				"login" => $_POST["login"],
				"is_admin" => @$_POST["is_admin"],
			);

			if (action() == "users-add" || $form->validate("pass", "entered"))
			{ $fields["pass"] = sha256(trim($_POST["pass"])); }

			if (action() == "users-add") { $_GET["id"] = db_insert("cms_auth_users", $fields); }
			elseif (action() == "users-edit") { db_update("cms_auth_users", $fields, array("id" => $_GET["id"])); }

			$user_roles = db_select_all("SELECT role_id FROM cms_auth_user_roles WHERE user_id = ?i", $_GET["id"], array("@key" => "role_id"));
			if (isset($_POST["roles"]) && is_array($_POST["roles"]))
			{
				foreach ($_POST["roles"] as $role_id)
				{
					if (!isset($user_roles[$role_id])) { db_insert("cms_auth_user_roles", array("user_id" => $_GET["id"], "role_id" => $role_id)); }
					else { unset($user_roles[$role_id]); }
				}
			}
			if ($user_roles) { db_delete("cms_auth_user_roles", array("user_id" => $_GET["id"], "role_id IN (?i)" => array_keys($user_roles))); }

			redirect_to("action", "users-list");
		}
	}
	elseif (action() == "users-edit")
	{
		$_POST = db_select_row("
		SELECT
			id,
			login,
			is_admin
		FROM
			cms_auth_users
		WHERE
			id = ?i
		", $_GET["id"]);

		$_POST["roles"] = db_select_col("SELECT role_id FROM cms_auth_user_roles WHERE user_id = ?i", $_GET["id"]);
	}

	// Все имеющиеся роли
	$roles = db_select_all("
	SELECT
		id,
		name
	FROM
		cms_auth_roles
	ORDER BY
		name
	");

	render_view("users-form");
}

if (action() == "roles-list")
{
	$where = array();

	if (isset($_GET["sort_cond"])) { $where["@order"] = $_GET["sort_cond"]; } else { $where["@order"] = "id ASC"; }

	$roles = db_select_all("
	SELECT
		id,
		name
	FROM
		cms_auth_roles
	", $where);
}

if (action() == "roles-delete")
{
	layout(false);
	if (isset($_POST["nodes"]) && is_array($_POST["nodes"]) && count($_POST["nodes"]))
	{ db_delete("cms_auth_roles", array("id IN (?i)" => $_POST["nodes"])); }
}

if (action() == "roles-edit" || action() == "roles-add")
{
	$_PAGE["main_bg"] = "threedface-bg";
	$_PAGE["body_overflow"] = "auto";

	// Функция возвращает массив целых чисел бит которых содержится в указанном целом числе
	function int2array($int)
	{
		$int = intval($int); $arr = array();
		for ($i = 1; $i <= pow(2, 30); $i *= 2)
		{ if ($int & $i) $arr[] = $i; }
		return $arr;
	}

	// Функция обратного преобразования
	// Получает массив целых чисел и возвращает их сумму (только тех которые являются степенью двойки)
	function array2int($arr)
	{
		if (!is_array($arr)) { return 0; }
		if (!count($arr)) { return 0; }
		$int = 0;
		foreach ($arr as $i) { if ($i == 1 || $i % 2 == 0) $int += $i; }
		return $int;
	}

	$form = new FormProcessor();

	if (@$_POST["do"] == "save")
	{
		if ($form->check("name", "entered"))
		{
			if
			(
				(action() == "roles-add" && false !== db_select_cell("SELECT id FROM cms_auth_roles WHERE name = ?", $_POST["name"]))
				||
				(action() == "roles-edit" && false !== db_select_cell("SELECT id FROM cms_auth_roles WHERE name = ? AND id != ?i", $_POST["name"], $_GET["id"]))
			)
			{ $form->set_error("name", "Такая роль уже есть"); }
		}

		if (!$form->is_form_error())
		{
			$fields = array
			(
				"name" => $_POST["name"],
				"default_auth" => @array2int($_POST["default_auth"]),
			);

			if (action() == "roles-add") { $_GET["id"] = db_insert("cms_auth_roles", $fields); }
			elseif (action() == "roles-edit") { db_update("cms_auth_roles", $fields, array("id" => $_GET["id"])); }

			// Обновляем специальные права на типы объектов
			if (@!is_array($_POST["auth_types"])) { db_delete("cms_auth_types", array("role_id" => $_GET["id"])); }
			else
			{
				foreach ($_POST["auth_types"] as $type => $item)
				{
					if (@$item["is_special_auth"])
					{
						if (false === db_select_cell("SELECT id FROM cms_auth_types WHERE role_id = ?i AND type = ?", $_GET["id"], $type))
						{ db_insert("cms_auth_types", array("type" => $type, "role_id" => $_GET["id"], "auth" => @array2int($item["auth"]))); }
						else { db_update("cms_auth_types", array("auth" => @array2int($item["auth"])), array("type" => $type, "role_id" => $_GET["id"])); }
					}
					else
					{ db_delete("cms_auth_types", array("role_id" => $_GET["id"], "type" => $type)); }
				}
			}

			redirect_to("action", "roles-list");
		}
	}
	elseif (action() == "roles-edit")
	{
		$_POST = db_select_row("
		SELECT
			id,
			name,
			default_auth
		FROM
			cms_auth_roles
		WHERE
			id = ?i
		", $_GET["id"]);

		$_POST["default_auth"] = int2array($_POST["default_auth"]);

		$_POST["auth_types"] = db_select_all("
		SELECT
			id,
			type,
			auth,
			1 as is_special_auth
		FROM
			cms_auth_types
		WHERE
			role_id = ?i
		", $_POST["id"], array("@key" => "type"));

		foreach ($_POST["auth_types"] as $type => $item) { $_POST["auth_types"][$type]["auth"] = int2array($item["auth"]); }
	}

	// Все имеющиеся типы
	$types = verticalize_list(db_select_all("
	SELECT
		type,
		name,
		0 as auth,
		0 as is_special_auth
	FROM
		cms_node_types
	ORDER BY
		name
	", array("@key" => "type")), 2);

	foreach ($types as $type => $item)
	{
		if (@$_POST["auth_types"][$type]["is_special_auth"])
		{
			$types[$type]["auth"] = @is_array($_POST["auth_types"][$type]["auth"]) ? $_POST["auth_types"][$type]["auth"] : array();
			$types[$type]["is_special_auth"] = 1;
		}
	}

	render_view("roles-form");
}