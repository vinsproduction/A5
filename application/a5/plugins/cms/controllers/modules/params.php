<?
function get_all_param_types()
{
	return array
	(
		"1" => "E-mail",
		"17" => "E-mail список",
		"4" => "Строка",
		"5" => "Строка (не пустая)",
		"22" => "Пароль",
		"6" => "Текст",
		"7" => "Текст (не пустой)",
		"8" => "Целое число",
		"9" => "Целое число (не пустое)",
		"18" => "Вещественное число",
		"19" => "Вещественное число (не пустое)",
		"20" => "Дата (не пустое)",
		"21" => "Дата + время (не пустое)",
		"10" => "HTML-Текст",
		"11" => "Чекбокс",
		"16" => "Ссылка",
		"2" => "Картинка",
		"3" => "Картинка (не пустая)",
		"12" => "Флэш",
		"13" => "Флэш (не пустой)",
		"14" => "Файл",
		"15" => "Файл (не пустой)",
	);
}

function cms_get_type_nodes($type, $parent_id, $offset = null, $limit = null)
{
	$where = array();
	$where["parent_id = ?i"] = $parent_id;

	if (isset($_GET["sort_cond"])) { $where["@order"] = $_GET["sort_cond"]; } else { $where["@order"] = "sibling_index ASC"; }

	if ($offset) { $where["@offset"] = $offset; }
	if ($limit) { $where["@limit"] = $limit; }

	$params = db_select_all("SELECT * FROM v_params", $where);
	foreach ($params as $i => $param) { if ($param["ptype"] == 22) $params[$i]["value"] = null; }
	return $params;
}

function cms_check_node_data($action, $form)
{
	$param_types = get_all_param_types();

	if (DEBUG_MODE)
	{
		$form->check("name", "entered");
		if ($action == "add") { $form->check("ptype", "selected"); }
	}

	if ($action == "mod")
	{
		// Менять название, описание, тип параметра можно только в DEBUG-режиме
		if (!DEBUG_MODE) { $_POST = array_merge($_POST, db_select_row("SELECT name, description, ptype FROM v_params WHERE id = ?i", $_POST["id"])); }

		if ($form->check("ptype", "selected"))
		{
			switch ($_POST["ptype"])
			{
				case '1': $form->check("value", "entered,valid_email"); break;
				case '17': $form->check("value", "entered"); break;
				case '3':
				case '13':
				case '15': $form->check("value", "selected"); break;
				case '5':
				case '7':
				case '16': $form->check("value", "entered"); break;
				case '8': $form->check("value", "valid_integer"); break;
				case '9': $form->check("value", "entered,valid_integer"); break;
				case '18': $form->check("value", "valid_float"); break;
				case '19': $form->check("value", "entered,valid_float"); break;
				case '11': $_POST["value"] = @$_POST["value"] ? 1 : 0; break;
				case '20':
				case '21': $form->check("value", "entered,valid_date"); break;
			}
		}
	}
}

function cms_get_node_data()
{
	$data = db_select_row("SELECT * FROM v_params WHERE id = ?i", $_POST["id"]);
	if ($data !== false && $data["ptype"] == 22) { $data["value"] = null; }
	return $data;
}

function cms_set_node_data($action)
{
	if ($_POST["ptype"] == 22 && !is_empty($_POST["value"])) { $_POST["value"] = sha256($_POST["value"]); }

	if ($action == "add" && DEBUG_MODE) { $id = set_node_data($_POST); }
	if ($action == "mod") { $id = set_node_data($_POST); }

	if (DEBUG_MODE)
	{
		// Название, описание и тип параметра обновляем всегда для всех языков
		$langs = cms_get_all_languages();
		foreach ($langs as $lang => $lang_info)
		{
			set_node_data(array(
			"id" => $id,
			"lang" => $lang,
			"name" => $_POST["name"],
			"description" => $_POST["description"],
			"ptype" => $_POST["ptype"],
			));
		}
	}

	return $id;
}

function cms_show_node_form($action)
{
	$param_types = get_all_param_types();

	if ($action == "add" && !DEBUG_MODE)
	{
		?>
		<table class="wide"><tr><td style="text-align: center;">
		<p>К сожалению, добавлять настройки могут только разработчики (программисты) сайта.</p>
		<p>Не существует таких ситуаций, когда нужно добавить настройку вам самим.</p>
		</td></tr></table>
		<?
	}
	else
	{
		start_form();
		?>
		<table style="width: 100%;">
			<tr>
				<? if (DEBUG_MODE): ?>
					<td style="text-align: right; white-space: nowrap;">Название:</td>
					<td style="width: 100%;"><input autocomplete="off" type="text" name="name" style="width: 500px;"></td>
				<? else: ?>
					<td style="text-align: right; vertical-align: top; white-space: nowrap; padding-bottom: 15px;"><b>Название:</b></td>
					<td style="vertical-align: top; width: 100%; padding-bottom: 15px;"><?= h($_POST["name"]) ?></td>
				<? endif ?>
			</tr>
			<? if (DEBUG_MODE): ?>
				<tr>
					<td style="text-align: right; white-space: nowrap;">Описание:</td>
					<td style="width: 100%"><textarea name="description" style="width: 500px; height: 150px;"></textarea></td>
				</tr>
			<? elseif (!is_empty($_POST["description"])): ?>
				<tr>
					<td style="text-align: right; vertical-align: top; white-space: nowrap; padding-bottom: 15px;"><b>Описание:</b></td>
					<td style="vertical-align: top; width: 100%; padding-bottom: 15px;"><?= nl2br($_POST["description"]) ?></td>
				</tr>
			<? endif ?>
			<? if (DEBUG_MODE): ?>
				<tr>
					<td style="text-align: right; white-space: nowrap;">Тип:</td>
					<td style="width: 100%">
						<select name="ptype">
						<option value="">---</option>
						<? foreach ($param_types as $k => $v): ?>
							<option value="<?= h($k) ?>"><?= h($v) ?></option>
						<? endforeach ?>
						</select>
					</td>
				</tr>
			<? endif ?>
			<? if ($action == "mod"): ?>
				<tr>
					<td style="text-align: right; white-space: nowrap"><?= $param_types[$_POST["ptype"]] ?>:</TD>
					<td style="width: 100%">
						<? if ($_POST["ptype"] == "10"): ?>
							<div style="width: 100%; height: 400px;"><? html_editor("value") ?></div>
						<? elseif ($_POST["ptype"] == "11"): ?>
							<input type="checkbox" name="value">
						<? elseif ($_POST["ptype"] == "20"): ?>
							<?= date_field("value") ?>
						<? elseif ($_POST["ptype"] == "22"): ?>
							<?= password_field("value", array("autocomplete" => "off")) ?>
						<? elseif ($_POST["ptype"] == "21"): ?>
							<?= datetime_field("value") ?>
						<? elseif ($_POST["ptype"] == "2" || $_POST["ptype"] == "3"): ?>
							<div style="width: 100%;"><? pick_image("value") ?></div>
						<? elseif ($_POST["ptype"] == "12" || $_POST["ptype"] == "13"): ?>
							<div style="width: 100%;"><? pick_flash("value") ?></div>
						<? elseif ($_POST["ptype"] == "14" || $_POST["ptype"] == "15"): ?>
							<? pick_file("value") ?>
						<? elseif ($_POST["ptype"] == "6" || $_POST["ptype"] == "7"): ?>
							<textarea style="width: 500px; height: 200px;" name="value"></textarea>
						<? elseif ($_POST["ptype"] == "16"): ?>
							<? pick_url("value") ?>
						<? else: ?>
							<input type="text" name="value" style="width: 500px;">
						<? endif ?>
					</TD>
				</tr>
			<? endif ?>
		</table>
		<?
		finish_form();
	}
}

function cms_get_type_field_list() { return array("name", "description", "value"); }