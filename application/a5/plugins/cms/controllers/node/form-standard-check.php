<?php
// Если определена пользовательская функция проверки данных - запускаем.
// Пользовательская функция проверки данных проверяет введённые данные на основе
// первого параметра переданного в функцию (т.е. имя действия "add" или "mod")
// Вторым параметром функция должна принимать ссылку на объект FormProcessor
// С помощью которого все эти проверки должны проводиться
// Ошибки соотвественно должны устанавливаться через метод $form->set_error
if (function_exists("cms_check_node_data")) { cms_check_node_data($_CMS["do"], $form); }

$form_errors = $form->get_all_errors();

$field_names = array
(
	"url_id" => "URL имя",
	"title" => "Принудительный TITLE",
	"meta_keywords" => "META Keywords",
	"meta_description" => "META Description",
	"system_name" => "Системное имя",
	"controller" => "Собственный контроллер",
	"action" => "Собственное действие",
);

$node_fields = cms_get_type_fields($_POST["type"]);

// После пользовательской проверки - проводим стандартную проверку
// При этом не проверяем те поля, по которым ошибки уже есть
foreach ($node_fields as $field)
{
	// Заполняем массив имён полей для человека
	$field_names[$field["field_name"]] = $field["name"];

	// Если пользовательская функция проверки ошибок установила
	// ошибку в поле - то стандартную проверку не производим
	if (array_key_exists($field["field_name"], $form_errors)) { continue; }

	// Если данное поле отключено - проверку не производим
	if (in_array($field["field_name"], disable_fields())) { continue; }

	// Заполняем массив нужными проверками
	$field_checks = array();
	// Если обязательно для заполнения и не имеет значения по-умолчанию
	if ($field["is_req"] && is_empty($field["default_value"]))
	{
		switch ($field["field_type"])
		{
			case 'boolean':
			case 'gender':
			case 'object':
			case 'image':
			case 'file':
			case 'flash': $field_checks[] = "selected"; break;
			default: $field_checks[] = "entered"; break;
		}
	}

	// Типовые проверки
	switch ($field["field_type"])
	{
		case 'email': $field_checks[] = "valid_email"; break;
		case 'date':
		case 'datetime': $field_checks[] = "valid_date"; break;
		case 'time': $field_checks[] = "valid_time"; break;
		case 'integer':
		case 'filesize':
		case 'gender':
		case 'object': $field_checks[] = "valid_integer"; break;
		case 'age': $field_checks[] = "valid_age"; break;
		case 'number': $field_checks[] = "valid_float"; break;
		case 'price': $field_checks[] = "valid_price"; break;
		case 'sum': $field_checks[] = "valid_sum"; break;
		case 'cost': $field_checks[] = "valid_cost"; break;
	}

	// Если есть проверки - проверям
	if (count($field_checks)) { $form->check($field["field_name"], implode(",", $field_checks)); }

	// Список всех возможных в cms типов полей
	$column_types = cms_get_column_types();

	// Если ошибок по полю не было и оно должно быть уникальным - проверяем уникальность
	if ($field["is_uniq"] && !$form->is_error($field["field_name"]))
	{
		$check_field_name = db_escape_ident($field["field_name"]);
		$check_field_value = $column_types[$field["field_type"]]["dbmarker"];

		if ($field["is_ncs"])
		{
			$check_field_name = "lower(" . $check_field_name . ")";
			$check_field_value = "lower(" . $check_field_value . ")";
		}

		if
		(
			false !== db_select_cell("
			SELECT
				id
			FROM
				 " . db_escape_ident("v_" . $_POST["type"]) . "
			WHERE
				" . $check_field_name . " = " . $check_field_value . "
				" . ($_CMS["do"] == "mod" ? "AND id != " . db_int($_POST["id"]) : "") . "
			", $_POST[$field["field_name"]])
		)
		{ $form->set_error($field["field_name"], "Должно быть уникальным"); }
	}

	// Если ошибок по полю не было и это поле типа "object" и указан тип объекта и объект был выбран
	// пользователем, проверим, что выбранный объект действительно этого типа и что он вообще существует
	if (!$form->is_error($field["field_name"]) && $field["field_type"] == "object" && $field["select_type"] !== null && !@is_empty($_POST[$field["field_name"]]))
	{
		$object = db_select_row("SELECT id, type FROM cms_nodes WHERE id = ?i", $_POST[$field["field_name"]]);
		if ($object === false) { $form->set_error($field["field_name"], "selected", true); }
		elseif ($object["type"] != $field["select_type"]) { $form->set_error($field["field_name"], "Выбран документ неверного типа"); }
	}
}

// Дополнительная функция проверки запускаемая ПОСЛЕ пользовательской и стандартной проверки полей
// В ней можно дополнительно установить какие-то ошибки или наоборот исключить ненужные ошибки
if (function_exists("cms_after_check_node_data")) { cms_after_check_node_data($_CMS["do"], $form); }

// Если существует пользовательская функция имён полей - дополняем список ими.
// Если у вас есть какие-то особенные поля, то нужно в модуле создать спец.функцию с именем
// cms_get_node_field_names() которая должна возвращать массив ключами которого являются
// имена полей текущего редактируемого типа ноды, а значениями - имена этих полей более удобно
// читаемые для человека. К примеру если в форме редактирования у вас есть поле "email",
// которое не относиться к стандартному полю объекта, тогда вызов
// cms_get_node_field_names() должен вернуть array("email" => "E-mail")
// Таким образом в сообщениях об ошибке будет использоваться данное имя (E-mail)
// иначе будет использоваться имя поля так как оно есть.
if (function_exists("cms_get_node_field_names"))
{
	$user_field_names = cms_get_node_field_names();
	if (is_array($user_field_names)) { $field_names = array_merge($field_names, $user_field_names); }
	unset($user_field_names);
}