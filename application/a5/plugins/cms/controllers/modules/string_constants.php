<?php
function cms_get_node_data()
{
	$langs = cms_get_all_languages();
	$_POST["name"] = db_select_cell("SELECT name FROM v_string_constants WHERE id = ?i", $_POST["id"]);
	foreach ($langs as $lang => $info)
	{
		cms_set_language($lang);
		$_POST["value"][$lang] = db_select_cell("SELECT value FROM v_string_constants WHERE id = ?i", $_POST["id"]);
	}
}

function cms_check_node_data($action, $form)
{
	// Добавлять можно только в DEBUG-режиме
	if ($action == "add" && DEBUG_MODE) { $form->check("name", "entered"); }
}

function cms_set_node_data($action)
{
	$langs = cms_get_all_languages();

	// Добавлять можно только в DEBUG-режиме
	if ($action == "add" && DEBUG_MODE)
	{
		// Идентификатор должен быть одинаков для любого языка
		$fields = array("name" => $_POST["name"], "parent_id" => $_POST["parent_id"], "type" => $_POST["type"]);
		foreach ($langs as $lang => $info)
		{
			cms_set_language($lang);
			$fields["value"] = $_POST["value"][$lang];
			$fields["id"] = set_node_data($fields);
		}
		return $fields["id"];
	}

	if ($action == "mod")
	{
		// Модифицируем только языковые значения
		foreach ($langs as $lang => $info)
		{
			cms_set_language($lang);
			set_node_data(array(
			"id" => $_POST["id"],
			"value" => $_POST["value"][$lang],
			));
		}
	}
}

function cms_show_node_form($action)
{
	$langs = cms_get_all_languages();

	if ($action == "add" && !DEBUG_MODE)
	{
		?>
		<table class="wide"><tr><td style="text-align: center;">
		<p>К сожалению, добавлять строковые константы могут только разработчики (программисты).</p>
		<p>Не существует таких ситуаций, когда нужно добавить константу вам самим.</p>
		</td></tr></table>
		<?
	}
	else
	{
		$fields = cms_get_type_fields("string_constants");
		start_form();
		?>
		<table>
			<tr>
				<td><?= h($fields["name"]["name"]) ?>:</td>
				<td><textarea <? if (!DEBUG_MODE || $action == "mod"): ?>readonly<? endif ?> name="name" wrap="off" style="width: 500px; height: 120px;"></textarea></td>
			</tr>
			<? foreach ($langs as $lang => $info): ?>
			<tr>
				<td><?= h($fields["value"]["name"]) ?> (<?= h($info["name"]) ?>):</td>
				<td><textarea name="value[<?= h($lang) ?>]" wrap="off" style="width: 500px; height: 120px;"></textarea></td>
			</tr>
			<? endforeach ?>
		</table>
		<?
		finish_form();
	}
}