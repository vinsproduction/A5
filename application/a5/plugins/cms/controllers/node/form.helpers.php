<?php
// Старт стандартной формы редактирования объекта
// В параметрах можно передать дополнительные параметры для тэга <form>
function start_form($params = array())
{
	global $form;

	$node_url = null;
	// Если редактируем объект - вытащим стандартные поля ноды
	if (isset($_POST["id"]))
	{
		$_POST = array_merge($_POST, db_select_row("
		SELECT
			n.id,
			n.parent_id,
			n.url_id,
			n.is_hidden,
			n.is_menuitem,
			n.title,
			n.meta_keywords,
			n.meta_description
		FROM
			v_cms_nodes n
		WHERE
			n.id = ?i
		", $_POST["id"]));

		// Если режим отладки вытащим доп.поля
		if (DEBUG_MODE)
		{
			$_POST = array_merge($_POST, db_select_row("
			SELECT
				n.type,
				n.system_name,
				n.controller,
				n.action,
				nt.controller as __cms__type_controller,
				nt.action as __cms__type_action
			FROM
				cms_nodes n
				LEFT JOIN cms_node_types nt ON nt.type = n.type
			WHERE
				n.id = ?i
			", $_POST["id"]));

			$_POST["__cms__node_childs"] = db_select_all("
			SELECT
				nc.child_type as type,
				nt.name
			FROM
				cms_node_childs nc
				JOIN cms_node_types nt ON nt.type = nc.child_type
			WHERE
				nc.id = ?i
			", $_POST["id"], array("@key" => "type"));
		}

		$node_url = url($_POST["id"], "@marker", false);
	}

	$form_params = array();
	foreach ($params as $key => $val) { $form_params[] = h($key) . "=\"" . h($val) . "\""; }
	$form_params = implode(" ", $form_params);
	?>

	<? include_block(CMS_PREFIX . "/node/form-loading") ?>

	<form method="get" action="<?= url_for("action", action()) ?>" id="form_lang_selection">
		<input type="hidden" name="parent_id">
		<input type="hidden" name="type">
		<? if ($_POST["__cms__do"] == "mod"): ?>
			<input type="hidden" name="id">
		<? endif ?>
		<input type="hidden" name="lang" value="">
		<input type="hidden" name="__cms__fetch_from_lang" value="">
		<input type="hidden" name="__cms__activate_tab" value="">
	</form>

	<form target="frame_node_processor" id="form_node" method="post"<? if (!is_empty($form_params)): ?> <?= $form_params ?><? endif ?>>
	<input type="hidden" name="__cms__activate_tab" value="">
	<input type="hidden" name="__cms__do" />
	<input type="hidden" name="parent_id">
	<? if (!DEBUG_MODE): ?><input type="hidden" name="type"><? endif ?>

	<? if ($_POST["__cms__do"] == "mod"): ?>
		<input type="hidden" name="id">
	<? else: ?>
		<input type="hidden" name="__cms__one_more" value="">
		<input type="hidden" name="__cms__no_clean" value="">
	<? endif ?>

	<input type="hidden" name="__cms__save_all_langs" value="">
	<input type="hidden" name="__cms__no_close" value="">

	<input id="form_node_submit_button" type="submit" value="Сохранить" style="display: none;">

	<script type="text/javascript">
	<!--
	var current_tab_button = null;
	var current_tab = null;
	var tab_buttons_html = '';

	function show_tab(tab_number, tab_button)
	{
		var node_form = $('form_node');
		try
		{
			if (tab_button.className == 'tab_button_disabled')
			{
				if (confirm('К данной вкладке можно перейти после сохранения данных!\nСохранить и перейти?'))
				{
					node_form.elements['__cms__activate_tab'].value = tab_number;
					node_form.elements['__cms__one_more'].value = '';
					node_form.elements['__cms__no_clean'].value = '';
					node_form.elements['__cms__no_close'].value = 'yes';
					$('form_node_submit_button').click();
				}
			}
			else
			{
				if (current_tab_button != null) { current_tab_button.className = 'tab_button_off'; }
				if (current_tab != null) { current_tab.style.display = 'none'; }
				current_tab_button = tab_button;
				current_tab = $('tab_' + tab_number);
				current_tab_button.className = 'tab_button_on';
				current_tab.style.display = 'block';
				node_form.elements['__cms__activate_tab'].value = tab_number;
				$('form_lang_selection').elements['__cms__activate_tab'].value = tab_number;
			}
		}
		catch(e) {}
	}

	function tab_add(name, number, is_disabled)
	{
		tab_buttons_html += '<div class="' + (is_disabled ? 'tab_button_disabled' : 'tab_button_off') + '" id="tab_button_' + number + '" onclick="show_tab(\'' + number + '\', this);">';
		tab_buttons_html += name;
		tab_buttons_html += '</div>';
	}
	//-->
	</script>

	<?
	// Если в скрытых полях находится меньшее количество полей из указанных, значит одно или более из них видимое
	if (count(array_intersect(array("url_id", "is_hidden", "is_menuitem", "node_url"), disable_fields())) < 4):
	?>
		<div id="shared_fields" style="position: absolute; left: 0px; top: 0px; width: 100%; z-index: 0;">
			<div style="padding: 2px;">
				<table>
				<tr class="nowrap">
					<? if (!in_array("url_id", disable_fields())): ?>
						<td><label for="inp_url_id">URL имя:</label></td>
						<td style="padding-left: 2px;"><input type="text" name="url_id" style="width: 100px;" id="inp_url_id"></td>
					<? endif ?>
					<? if (!in_array("is_hidden", disable_fields())): ?>
						<td style="padding-left: 2px;"><input type="hidden" name="is_hidden" value=""><input type="checkbox" name="is_hidden" id="chk_is_hidden"></td>
						<td><label for="chk_is_hidden">Скрыть</label></td>
					<? endif ?>
					<? if (!in_array("is_menuitem", disable_fields())): ?>
						<td style="padding-left: 2px;"><input type="hidden" name="is_menuitem" value=""><input type="checkbox" name="is_menuitem" id="chk_is_menuitem"></td>
						<td><label for="chk_is_menuitem">Пункт меню</label></td>
					<? endif ?>
					<? if (!in_array("node_url", disable_fields())): ?>
						<td style="padding-left: 10px;">URL:</td>
						<td style="width: 100%; padding-left: 2px;"><div style="margin-right: 6px;"><input type="text" value="<?= h($node_url) ?>" readonly style="width: 100%;"></div></td>
					<? endif ?>
				</tr>
				</table>
			</div>
		</div>
	<? endif ?>

	<div id="tab_buttons" style="position: absolute; left: 0px; top: 27px; width: 100%; z-index: 20;">
		<table>
			<tr>
				<? if (!in_array("lang", disable_fields())): ?>
					<? if (db_select_cell("SELECT is_lang FROM cms_node_types WHERE type = ?", $_POST["type"])): ?>
						<? if (db_select_cell("SELECT COUNT(*) FROM cms_languages") > 1): ?>
							<td style="padding-left: 3px;">Язык:</td>
							<td>
								<script type="text/javascript">
								function handle_language_change(obj, evt)
								{
									var frm = $('form_lang_selection');
									if (frm)
									{
										if (evt && (evt.ctrlKey || evt.metaKey))
										{ frm.elements['__cms__fetch_from_lang'].value = '<?= @!is_empty($_POST["__cms__fetch_from_lang"]) ? j($_POST["__cms__fetch_from_lang"]) : h($_POST["lang"]) ?>'; }
										frm.elements['lang'].value = obj.options[obj.selectedIndex].value;
										frm.submit();
									}
								}
								</script>
								<select title="Удерживайте клавишу Ctrl при выборе языка для копирования в него данных" name="lang" onchange="handle_language_change(this, event);">
								<?
								$langs = db_select_all("
								SELECT
									lang,
									name
								FROM
									cms_languages
								ORDER BY
									is_default DESC,
									name
								");
								?>
								<? foreach ($langs as $item): ?>
									<option value="<?= h($item["lang"]) ?>"><?= h($item["name"]) ?></option>
								<? endforeach ?>
								</select>
							</td>
						<? else: ?>
							<td><input type="hidden" name="lang"></td>
						<? endif ?>
					<? endif ?>
				<? endif ?>
				<td style="padding-left: 2px; padding-right: 0px;"><div id="tab_buttons_collection"></div></td>
			</tr>
		</table>
	</div>
	<?
	// Первый и всегда присутсвующий TAB
	start_tab("Общая информация");
}

function finish_form()
{
	global $form;

	// Если в скрытых полях находится меньшее количество полей из указанных, значит одно или более из них видимое
	if (count(array_intersect(array("title", "meta_keywords", "meta_description"), disable_fields())) < 3)
	{
		start_tab("Дополнительно");
		?>
		<table>
		<? if (!in_array("title", disable_fields())): ?>
			<tr>
				<td style="text-align: right;"><label for="inp_title">Принудительный TITLE:</label></td>
				<td><input type="text" name="title" style="width: 250px;" id="inp_title"></td>
			</tr>
		<? endif ?>
		<? if (!in_array("meta_keywords", disable_fields())): ?>
			<tr>
				<td style="text-align: right;"><label for="inp_meta_keywords">META Keywords:</label></td>
				<td><input type="text" name="meta_keywords" style="width: 400px;" id="inp_meta_keywords"></td>
			</tr>
		<? endif ?>
		<? if (!in_array("meta_description", disable_fields())): ?>
			<tr>
				<td style="text-align: right;"><label for="inp_meta_description">META Description:</label></td>
				<td><input type="text" name="meta_description" style="width: 400px;" id="inp_meta_description"></td>
			</tr>
		<? endif ?>
		</table>
		<?
	}
	?>

	<? if (DEBUG_MODE): ?>
		<? start_tab("DEBUG") ?>
		<table>
		<tr>
			<td style="text-align: right;"><label for="inp_system_name">Системное имя:</label></td>
			<td><input type="text" name="system_name" style="width: 150px;" id="inp_system_name"></td>
			<td style="text-align: right;"><label for="sel_type">Тип:</label></td>
			<td>
				<select name="type" id="sel_type">
				<? $types = db_select_all("SELECT type, name FROM cms_node_types ORDER BY type") ?>
				<? foreach ($types as $item): ?>
					<option value="<?= h($item["type"]) ?>"><?= h($item["type"]) ?> (<?= h($item["name"]) ?>)</option>
				<? endforeach ?>
				</select>
			</td>
		</tr>
		<tr>
			<td style="text-align: right;"><label for="inp_controller">Собственный контроллер:</label></td>
			<td><input type="text" name="controller" style="width: 150px;" id="inp_controller"></td>
			<td style="text-align: right;">Контроллер типа:</td>
			<td><input type="text" readonly="readonly" name="__cms__type_controller"  style="width: 150px;"></td>
		</tr>
		<tr>
			<td style="text-align: right;"><label for="inp_action">Собственное действие:</label></td>
			<td><input type="text" name="action" style="width: 150px;" id="inp_action"></td>
			<td style="text-align: right;">Действие типа:</td>
			<td><input type="text" readonly="readonly" name="__cms__type_action" style="width: 150px;"></td>
		</tr>
		</table>
		<div style="margin-top: 10px; font-weight: bold;">Собственные типы потомков:</div>
		<?
		$types = db_select_all("SELECT type, name FROM cms_node_types ORDER BY type");
		$types = verticalize_list($types, 3);
		?>
		<table>
			<tr>
			<? $i = 0; $count = count($types) ?>
			<? foreach ($types as $item): ?>
				<? if ($i && $i % 3 == 0): ?></tr><tr><? endif ?>
				<td><input type="checkbox" name="__cms__node_childs[<?= h($item["type"]) ?>]" id="chk___cms__node_childs_<?= h($item["type"]) ?>"><label for="chk___cms__node_childs_<?= h($item["type"]) ?>"><?= h($item["type"]) ?> (<?= h($item["name"]) ?>)</label></td>
				<? $i++ ?>
			<? endforeach ?>
			</tr>
		</table>
	<? endif ?>

	<? /* Этот div завершает последний открытый таб */ ?>
	</div>

	</form>

	<script type="text/javascript">
	$('tab_buttons_collection').innerHTML = tab_buttons_html;

	function initialize_form()
	{
		var tab_areas = document.body.getElementsByTagName('DIV');
		for (var i = 0, c = tab_areas.length; i < c; i++)
		{
			if (tab_areas[i].className == 'tab')
			{ tab_areas[i].style.display = 'none'; }
		}

		<? if (isset($_POST["__cms__activate_tab"]) && !is_empty($_POST["__cms__activate_tab"])): ?>
			show_tab('<?= j($_POST["__cms__activate_tab"]) ?>', $('tab_button_' + '<?= j($_POST["__cms__activate_tab"]) ?>'));
		<? else: ?>
			show_tab('0', $('tab_button_0'));
		<? endif ?>

		setTimeout(function()
		{
			reposition_frames();
			$('div_loader').style.display = 'none';
		}, 10);
	}

	function reposition_frames()
	{
		var shared_fields = $('shared_fields');
		var tab_buttons = $('tab_buttons');
		var client_width = document.body.clientWidth;
		var client_height = document.body.clientHeight;

		if (shared_fields) { shared_fields.style.width = client_width + 'px'; }
		tab_buttons.style.top = (shared_fields ? shared_fields.clientHeight : 0) + 'px';

		var tab_areas = document.body.getElementsByTagName('DIV');
		for (var i = 0, c = tab_areas.length; i < c; i++)
		{
			if (tab_areas[i].className == 'tab')
			{
				tab_areas[i].style.top = (shared_fields ? shared_fields.clientHeight : 0) + tab_buttons.clientHeight - 2 + 'px';
				tab_areas[i].style.width = client_width - 10 + 'px';
				tab_areas[i].style.left = 2 + 'px';
				tab_areas[i].style.height = client_height - (shared_fields ? shared_fields.clientHeight : 0) - tab_buttons.clientHeight - 8 + 'px';
			}
		}
	}
	add_event_listener(window, 'load', initialize_form);
	add_event_listener(window, 'resize', function() { setTimeout(function() { reposition_frames(); }, 10); });
	</script>
	<iframe name="frame_node_processor" src="<?= url_for(CMS_PUBLIC_URI . "/empty.php") ?>" frameborder="0" border="0" style="width: 0px; height: 0px; position: absolute; top: 0px; left: 0px; display: none;"></iframe>
	<?
}

function start_tab($name, $is_disabled = false)
{
	static $tab_count = 0;
	if ($tab_count) { echo "</div>"; }
	if ($_POST["__cms__do"] == "mod") { $is_disabled = false; }
	?>
	<script type="text/javascript">
	tab_add('<?= j($name) ?>', '<?= j($tab_count) ?>', <?= $is_disabled ? "true" : "false" ?>);
	</script>
	<div id="tab_<?= h($tab_count) ?>" class="tab">
	<?
	$tab_count++;
}

function cms_node_form_database_error($is_manual)
{
	while (@ob_get_level()) { ob_end_clean(); }
	$error_msg = "Ошибка базы данных:\n\n";
	$error_msg .= ($is_manual ? db_last_manual_error() : db_last_error());
	$error_msg .= (DEBUG_MODE ? "\nЗапрос:\n" . db_last_query() : "");
	?>
	<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
	<html>
	<head>
		<title></title>
	</head>
	<body>
	<script type="text/javascript">
	try { parent.parent.frames['frame_node_footer'].status_clear(); } catch (e) {}
	alert('<?= j($error_msg) ?>');
	</script>
	</body>
	</html>
	<?
	exit;
}

// Функция возвращает набор данных ноды с указанным $id и $type
function cms_select_node_data($id, $type)
{
	$data = array();

	// Выбираем все поля типа
	$node_fields = cms_get_type_fields($type);
	$sql_fields = array();
	foreach ($node_fields as $field)
	{
		switch ($field["field_type"])
		{
			case 'number': $sql_fields[] = db_escape_ident($field["field_name"]) . "::float as " . db_escape_ident($field["field_name"]); break;
			case 'date': $sql_fields[] = "TO_CHAR(" . db_escape_ident($field["field_name"]) . ", 'DD.MM.YYYY') as " . db_escape_ident($field["field_name"]); break;
			case 'time': $sql_fields[] = "TO_CHAR(" . db_escape_ident($field["field_name"]) . ", 'HH24:MI:SS') as " . db_escape_ident($field["field_name"]); break;
			case 'datetime': $sql_fields[] = "TO_CHAR(" . db_escape_ident($field["field_name"]) . ", 'DD.MM.YYYY HH24:MI:SS') as " . db_escape_ident($field["field_name"]); break;
			default: $sql_fields[] = db_escape_ident($field["field_name"]); break;
		}
	}

	if (count($sql_fields))
	{
		$data = db_select_row("SELECT " . implode(",", $sql_fields) . " FROM " . db_escape_ident("v_" . $type) . " WHERE id = ?i", $id);
		if ($data === false) { $data = array(); }
	}

	return $data;
}

// Генерация стандартной формы редактирования
function standard_form()
{
	start_form();
	standard_form_body();
	finish_form();
}

// Стандартная генерация вкладок редактирования для объекта
function standard_form_body()
{
	$type_fields = cms_get_type_fields($_POST["type"]);
	if (disable_fields()) { foreach (disable_fields() as $field_name) unset($type_fields[$field_name]); }

	// Группируем поля по типам
	$simple_fields = array();
	$image_fields = array();
	$flash_fields = array();
	$html_fields = array();

	foreach ($type_fields as $item)
	{
		switch ($item["field_type"])
		{
			case 'html': $html_fields[] = $item; break;
			case 'image': $image_fields[] = $item; break;
			case 'flash': $flash_fields[] = $item; break;
			case 'blob': break;
			case 'fti': break;
			default: $simple_fields[] = $item; break;
		}
	}
	?>
	<table>
	<? foreach ($simple_fields as $item): ?>
		<?
		switch ($item["field_type"])
		{
			case 'gender': ?><tr><td style="text-align: right;"><?= h($item["name"]) ?>:</td><td><?= sex_field($item["field_name"]) ?></td></tr><? break;
			case 'url': ?><tr><td style="text-align: right;"><?= h($item["name"]) ?>:</td><td><? pick_url($item["field_name"]) ?></td></tr><? break;
			case 'boolean': ?><tr><td>&nbsp;</td><td><?= boolean_field($item["field_name"], $item["name"]) ?></td></tr><? break;
			case 'sum':
			case 'cost':
			case 'price': ?><tr><td style="text-align: right;"><?= h($item["name"]) ?>:</td><td><?= money_field($item["field_name"]) ?></td></tr><? break;
			case 'file': ?><tr><td style="text-align: right;"><?= h($item["name"]) ?>:</td><td><? pick_file($item["field_name"], $item["root_folder"]) ?></td></tr><? break;
			case 'object':
				$parent_node_id = $item["select_root_name"];
				/*
				Пользовательская функция определения корневой node_id для вывода списка возможных добавляемых
				объектов для указанного поля - используеться только для функции pick_node или в форме one2many,
				если она определена - то берёться её возвращаемое значение, иначе будет использоваться id ноды
				с системным именем указанным в "select_root_name" метаданных данного добавляемого типа.
				В данной функции доступен массив $_POST с как минимум доступными полями
				 - type (имя типа)
				 - parent_id (id родительской ноды к которой добавляеться данный тип)
				*/
				if (function_exists("cms_get_root_node_for_field_name"))
				{
					$node_id = cms_get_root_node_for_field_name($item["field_name"]);
					if ($node_id) { $parent_node_id = $node_id; }
				}
				?><tr><td style="text-align: right;"><?= h($item["name"]) ?>:</td><td><? pick_node($item["field_name"], $parent_node_id, $item["select_type"], $item["select_method"]) ?></td></tr><?
				break;
			default: ?><tr><td style="text-align: right;"><?= h($item["name"]) ?>:</td><td><?= call_user_func($item["field_type"] . "_field", $item["field_name"]) ?></td></tr><? break;
		}
		?>
	<? endforeach ?>
	</table>
	<?
	// Если есть хоть одна картинка
	if (count($image_fields))
	{
		$count = count($image_fields);
		if ($count > 1)
		{
			start_tab("Картинки");
			?>
			<table style="width: 100%;">
			<? foreach ($image_fields as $i => $item): ?>
				<? if ($count <= 3): ?>
					<tr><td>
				<? else: ?>
					<? if (!$i || $i % 2 == 0): ?><tr><? endif ?>
					<td style="width: 50%;">
				<? endif ?>
				<div style="font-weight: bold;"><?= h($item["name"]) ?></div>
				<? pick_image($item["field_name"], $item["root_folder"]) ?>
				<? if ($count <= 3): ?>
					</td></tr>
				<? else: ?>
					</td>
					<? if (($i + 1) % 2 == 0): ?></tr><? endif ?>
				<? endif ?>
			<? endforeach ?>
			</table>
			<?
		}
		else
		{
			start_tab($image_fields[0]["name"]);
			pick_image($image_fields[0]["field_name"], $image_fields[0]["root_folder"]);
		}
	}

	// Если есть хоть один флэш
	if (count($flash_fields))
	{
		$count = count($flash_fields);
		if ($count > 1)
		{
			start_tab("Флэш");
			?>
			<table style="width: 100%;">
			<? foreach ($flash_fields as $i => $item): ?>
				<? if ($count <= 3): ?>
					<tr><td>
				<? else: ?>
					<? if (!$i || $i % 2 == 0): ?><tr><? endif ?>
					<td style="width: 50%;">
				<? endif ?>
				<div style="font-weight: bold;"><?= h($item["name"]) ?></div>
				<? pick_flash($item["field_name"], $item["root_folder"]) ?>
				<? if ($count <= 3): ?>
					</td></tr>
				<? else: ?>
					</td>
					<? if (($i + 1) % 2 == 0): ?></tr><? endif ?>
				<? endif ?>
			<? endforeach ?>
			</table>
			<?
		}
		else
		{
			start_tab($flash_fields[0]["name"]);
			pick_flash($flash_fields[0]["field_name"], $flash_fields[0]["root_folder"]);
		}
	}

	// HTML-поля тупо выводим отдельно на каждой вкладке
	foreach ($html_fields as $item)
	{
		start_tab($item["name"]);
		html_editor($item["field_name"]);
	}

	$where = array();
	$where["nt.is_hidden_tree = ?b"] = true;
	$where["ntc.type = ?"] = $_POST["type"];
	if (disable_childs()) { $where["nt.type NOT IN (?)"] = disable_childs(); }
	$where["@order"] = "ntc.sibling_index";

	// Проверяем может ли иметь данный тип объекта детей
	// Причём таких детей, которые не отображаются в дереве документов
	// И если может - то для каждого дитя создаём вкладку линейного типа
	$childs = db_select_all("
	SELECT
		nt.type,
		nt.name,
		nt.name_list
	FROM
		cms_node_type_childs ntc
		JOIN cms_node_types nt ON nt.type = ntc.child_type
	", $where);

	foreach ($childs as $item)
	{
		start_tab($item["name_list"], true);
		if ($_POST["__cms__do"] == "mod") { child_list($_POST["id"], $item["type"]); }
	}
}

function prefix_field_name($name, $prefix)
{
	if (!@is_empty($prefix)) { $name = $prefix . preg_replace("/^([^[]+)/", "[$1]", $name); }
	return $name;
}

function text_field($name, $params = array())
{
	if (!isset($params["type"])) { $params["type"] = "text"; }
	$params["name"] = prefix_field_name($name, @$params["prefix"]); unset($params["prefix"]);
	$params_str = array();
	foreach ($params as $key => $val) { $params_str[] = h($key) . '="' . h($val) . '"'; }
	$params_str = implode(" ", $params_str);
	return '<input ' . $params_str . '>';
}

function checkbox_field($name, $params = array())
{
	if (!isset($params["type"])) { $params["type"] = "checkbox"; }
	$params["name"] = prefix_field_name($name, @$params["prefix"]); unset($params["prefix"]);
	$params_str = array();
	foreach ($params as $key => $val) { $params_str[] = h($key) . '="' . h($val) . '"'; }
	$params_str = implode(" ", $params_str);
	return '<input type="hidden" name="' . h($params["name"]) . '" value=""><input ' . $params_str . '>';
}

function textarea_field($name, $params = array())
{
	$params["name"] = prefix_field_name($name, @$params["prefix"]); unset($params["prefix"]);
	$params_str = array();
	foreach ($params as $key => $val) { $params_str[] = h($key) . '="' . h($val) . '"'; }
	$params_str = implode(" ", $params_str);
	return '<textarea ' . $params_str . '></textarea>';
}

function select_field($name, $params = array())
{
	$params["name"] = prefix_field_name($name, @$params["prefix"]); unset($params["prefix"]);
	$params_str = array();
	foreach ($params as $key => $val) { $params_str[] = h($key) . '="' . h($val) . '"'; }
	$params_str = implode(" ", $params_str);
	return '<select ' . $params_str . '>';
}

function hidden_field($name, $params = array())
{
	$params = array_merge(array("type" => "hidden"), $params);
	return call_user_func("text_field", $name, $params);
}

function string_field($name, $params = array())
{
	$params = array_merge(array("style" => "width: 400px"), $params);
	return call_user_func("text_field", $name, $params);
}

function email_field($name, $params = array())
{
	$params = array_merge(array("style" => "width: 200px"), $params);
	return call_user_func("text_field", $name, $params);
}

function password_field($name, $params = array())
{
	$params = array_merge(array("type" => "password", "autocomplete" => "off", "style" => "width: 100px"), $params);
	return call_user_func("text_field", $name, $params);
}

function plain_text_field($name, $params = array())
{
	$params = array_merge(array("style" => "width: 400px; height: 60px"), $params);
	return call_user_func("textarea_field", $name, $params);
}

function date_field($name, $params = array())
{
	$params = array_merge(array("style" => "width: 70px", "onclick" => "NTCal.show(this, event)"), $params);
	return call_user_func("text_field", $name, $params);
}

function time_field($name, $params = array())
{
	$params = array_merge(array("style" => "width: 45px"), $params);
	return call_user_func("text_field", $name, $params);
}

function datetime_field($name, $params = array())
{
	$params = array_merge(array("style" => "width: 105px", "onclick" => "NTCal.show(this, event)"), $params);
	return call_user_func("text_field", $name, $params);
}

function boolean_field($name, $title, $params = array())
{
	$name = prefix_field_name($name, @$params["prefix"]);
	$params = array_merge(array("id" => "chk_" . $name), $params);
	return call_user_func("checkbox_field", $name, $params) . ('<label for="chk_' . h($name) . '">' . h($title) . '</label>');
}

function integer_field($name, $params = array())
{
	$params = array_merge(array("style" => "width: 70px"), $params);
	return call_user_func("text_field", $name, $params);
}

function age_field($name, $params = array())
{
	$params = array_merge(array("style" => "width: 30px"), $params);
	return call_user_func("text_field", $name, $params);
}

function filesize_field($name, $params = array())
{
	$params = array_merge(array("style" => "width: 50px"), $params);
	return call_user_func("text_field", $name, $params);
}

function number_field($name, $params = array())
{
	$params = array_merge(array("style" => "width: 70px"), $params);
	return call_user_func("text_field", $name, $params);
}

function money_field($name, $params = array())
{
	$params = array_merge(array("style" => "width: 70px; text-align: right"), $params);
	return call_user_func("text_field", $name, $params);
}

function sex_field($name, $params = array())
{
	$html = select_field($name, $params);
	$html .= '<option value="">---</option>';
	$genders = cms_get_genders_list();
	foreach ($genders as $k => $v) { $html .= '<option value="'. h($k) . '">' . h($v) . '</option>'; }
	$html .= '</select>';
	return $html;
}

// Кнопка для отправки данных формы
function submit_button($name, $params = array())
{
	$params["type"] = "button";
	$params["value"] = $name;
	$params["onclick"] = (isset($params["onclick"]) ? $params["onclick"] . "; " : null) . "top.submit_form();";
	$params_str = array();
	foreach ($params as $key => $val) { $params_str[] = h($key) . '="' . h($val) . '"'; }
	$params_str = implode(" ", $params_str);
	return '<input ' . $params_str . '>';
}

// Кнопка для отправки данных формы
function cancel_button($name, $params = array())
{
	$params["type"] = "button";
	$params["value"] = $name;
	$params["onclick"] = (isset($params["onclick"]) ? $params["onclick"] . "; " : null) . "top.cancel_form();";
	$params_str = array();
	foreach ($params as $key => $val) { $params_str[] = h($key) . '="' . h($val) . '"'; }
	$params_str = implode(" ", $params_str);
	return '<input ' . $params_str . '>';
}