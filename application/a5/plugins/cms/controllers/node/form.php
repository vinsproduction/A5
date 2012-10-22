<?php
// Обработка формы после постинга данных
if (isset($_CMS["do"]))
{
	if ($_CMS["do"] == "add" && !cms_is_have_auth_for_type($_POST["type"], CMS_AUTH_CREATE))
	{ db_run_error("У вас нет прав на создание данного типа объектов"); }

	if ($_CMS["do"] == "mod" && !cms_is_have_auth_for_type($_POST["type"], CMS_AUTH_UPDATE))
	{ db_run_error("У вас нет прав на изменения данного типа объектов"); }

	require("form-standard-check.php");

	if ($form->validate("url_id", "entered"))
	{
		if (!cms_valid_url_id($_POST["url_id"])) { $form->set_error("url_id", "Не верно (Должно содержать только: \"a-z\", \"0-9\", \"-\")"); }
		// Проверим уникальность url_id у данного типа документов
		else
		{
			if (false !== db_select_cell("SELECT url_id FROM cms_nodes WHERE type = ? AND url_id = ?" . ($_CMS["do"] == "mod" ? "AND id != ?i" : ""), $_POST["type"], $_POST["url_id"], ($_CMS["do"] == "mod" ? $_POST["id"] : null)))
			{ $form->set_error("url_id", "Данное URL имя уже существует у этого типа объектов"); }
		}
	}

	if (DEBUG_MODE)
	{
		if ($form->validate("controller", "entered"))
		{
			if (!cms_valid_controller($_POST["controller"]))
			{ $form->set_error("controller", "Неверно (может содержать только: \"a-z\", \"A-Z\", \"0-9\", \"_\", \"-\" и \"/\")"); }
		}

		if ($form->validate("action", "entered"))
		{
			if (!cms_valid_action($_POST["action"]))
			{ $form->set_error("action", "Неверно (может содержать только: \"a-z\", \"A-Z\", \"0-9\", \"_\", и \"-\")"); }
		}

		if ($form->validate("system_name", "entered"))
		{
			if (!cms_valid_system_name($_POST["system_name"]))
			{ $form->set_error("system_name", "Неверно (может содержать только: \"0-9\", \"a-z\", \"A-Z\", \":\", \".\", \"_\", \"-\" и должно начинаться с \"a-z\" или \"A-Z\")"); }
		}
	}

	if (!$form->is_form_error())
	{
		if (!include("form-standard-save.php")) return 0;

		// В дебаг-режиме мы ещё МОЖЕМ изменять собственные типы потомков
		if (DEBUG_MODE && isset($_POST["id"]))
		{
			$current_child_types = db_select_all("SELECT id, child_type FROM cms_node_childs WHERE id = ?i", $_POST["id"], array("@key" => "child_type"));
			// Вставляем те, которых ещё нет, которые уже есть - убираем из списка
			if (isset($_CMS["node_childs"]) && is_array($_CMS["node_childs"]))
			{
				$child_types = array_keys($_CMS["node_childs"]);
				foreach ($child_types as $type)
				{
					if (!isset($current_child_types[$type]))
					{ db_insert("cms_node_childs", array("id" => $_POST["id"], "child_type" => $type)); }
					else { unset($current_child_types[$type]); }
				}
			}
			// Удаляем те, которые остались в списке - т.к. они не были выбраны
			$current_child_types = array_keys($current_child_types);
			if (count($current_child_types)) { db_delete("cms_node_childs", array("id" => $_POST["id"], "child_type IN (?)" => $current_child_types)); }
		}
		layout(CMS_PREFIX . "/html-empty");
		return render_view("form_saved");
	}
	else { if (!include("form-standard-error.php")) return 0; }
}
// Начальная инициализация формы
else
{
	// Устанавливаем текущий тип действия с формой
	if (isset($_POST["id"])) { $_CMS["do"] = "mod"; } else { $_CMS["do"] = "add"; }

	if ($_CMS["do"] == "mod")
	{
		if (!cms_is_have_auth_for_type($_POST["type"], CMS_AUTH_READ)) { db_run_error("У вас нет прав на чтение данных!"); }

		// Если попросили выбрать данные от другого языка и он отличается от текущего
		// Запоминаем текущий - переключаем на язык из которого будем выбирать
		if (isset($_CMS["fetch_from_lang"]) && !is_empty($_CMS["fetch_from_lang"]) && $_CMS["fetch_from_lang"] != $_POST["lang"])
		{
			$old_node_lang = cms_get_language();
			cms_set_language($_CMS["fetch_from_lang"]);
		}

		// Если определена пользовательская функция выборки данных - используем её - иначе - выбираем данные сами
		// Пользовательская функция выборки данных должна вернуть массив с данными полей ноды, id которой
		// находиться в $_POST["id"], в общем случае в данной функции должен быть релизован механизм, подобный
		// этому: return db_select_row("SELECT * FROM v_node WHERE id = ?i", $_POST["id"])
		if (function_exists("cms_get_node_data"))
		{
			$data = cms_get_node_data($_POST["id"]);
			if (is_array($data)) { $_POST = array_merge($data, $_POST); }
		}
		else { $_POST = array_merge($_POST, cms_select_node_data($_POST["id"], $_POST["type"])); }

		// Если определена пользовательская функция выборки дополнительных данных - используем её.
		// Функция должна вернуть какие-то дополнительные данные, которе будут совмещены с массивом $_POST.
		if (function_exists("cms_get_node_custom_data"))
		{
			$data = cms_get_node_custom_data($_POST["id"]);
			if (is_array($data)) { $_POST = array_merge($data, $_POST); }
		}

		// Если мы выбирали данные из другого языка - пора вернуть его обратно
		if (isset($old_node_lang)) { cms_set_language($old_node_lang); }
	}
}

// Перед выводом формы возвращаем все поля из $_CMS обратно в $_POST
foreach ($_CMS as $key => $val) { $_POST["__cms__" . $key] = $val; } unset($_CMS);