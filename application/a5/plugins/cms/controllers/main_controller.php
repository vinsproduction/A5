<?php
require_once("include/setup.php");

if (action() == "index")
{
	// Список типов, на которые у нас есть права на чтение
	$auth_types = cms_get_types_list_for_auth(CMS_AUTH_READ);

	// Список дочерних типов, на которые у нас есть право на чтение, плюс хоть одно право из: создание, изменение, удаление
	$child_auth_types = cms_get_types_list_for_auth(CMS_AUTH_CREATE | CMS_AUTH_UPDATE | CMS_AUTH_DELETE);

	// Выбираем все ноды из дерева документов, которые имеют собственных потомков
	// Но только тех у которых есть такие потомки к которым у нас есть хоть какой-нибудь доступ
	// кроме как на чтение
	$section_nodes = db_select_all("
	SELECT
		n.id,
		n.name,
		CASE WHEN EXISTS(SELECT nc.id FROM cms_node_childs nc JOIN cms_node_types t ON nc.child_type = t.type WHERE nc.id = n.id AND t.is_hidden_tree = 0 AND nc.child_type IN (?)) THEN 1 ELSE 0 END as is_catalog
	FROM
		v_cms_nodes n
	WHERE
		n.id IN (SELECT id FROM cms_node_childs WHERE child_type IN (?))
	ORDER BY
		n.name
	", $auth_types, $child_auth_types);
}

if (action() == "download")
{
	// Путь обязан быть указан относительно PUBLIC_DIR
	if (!@is_empty($_GET["path"]))
	{
		$path = normalize_path(PUBLIC_DIR . "/" . normalize_path($_GET["path"]));
		if (@is_file($path) && @is_readable($path)) { Download::attachment($path, basename($path)); }
	}
	HTTP::response_code(404);
}

if (action() == "logout")
{
	unset($_SESSION["cms_auth_id"]);
	redirect_to("-controller", "index");
}