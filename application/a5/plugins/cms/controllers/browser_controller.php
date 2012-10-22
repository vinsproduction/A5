<?php
require_once("include/setup.php");

if (!defined('CMS_MEDIA_DIR')) { die("CMS_MEDIA_DIR undefined"); }
elseif (!is_dir(CMS_MEDIA_DIR)) { @mkdirs(CMS_MEDIA_DIR) or die("Cannot create: " . CMS_MEDIA_DIR . ": " . $GLOBALS["php_errormsg"]); }

// Создаём недостающие медиа-папки
foreach (array(CMS_MEDIA_IMAGE_DIR, CMS_MEDIA_FLASH_DIR, CMS_MEDIA_FILES_DIR) as $media_folder)
{
	if (!is_dir($media_folder))
	{
		if (strpos($media_folder, CMS_MEDIA_DIR) !== 0) { die("Invalid root path: " . h($media_folder)); }
		else { @mkdirs($media_folder) or die("Cannot create: " . $media_folder . ": " . $GLOBALS["php_errormsg"]); }
	}
}

$root_path = null;
if (isset($_GET["root_path"]))
{
	$root_path = $_GET["root_path"];
	$full_root_path = rtrim(normalize_path(CMS_MEDIA_DIR . "/" . $_GET["root_path"]), "/");
	if (strpos($full_root_path, CMS_MEDIA_DIR) !== 0) { die("Invalid root path: " . h($_GET["root_path"])); }
	else { define('ROOT_FOLDER', $full_root_path); }
}
else { define('ROOT_FOLDER', CMS_MEDIA_DIR); }

if (!is_dir(ROOT_FOLDER)) { die("Cannot find root folder <b>" . h(ROOT_FOLDER) . "</b>"); }

if (isset($_GET["list_style"])) { setcookie("__cms__browser_list_style", $_GET["list_style"]); $explorer_list_style = $_GET["list_style"]; }
elseif (isset($_COOKIE["__cms__browser_list_style"])) { $explorer_list_style = $_COOKIE["__cms__browser_list_style"]; }
if (!isset($explorer_list_style) || $explorer_list_style < 0 || $explorer_list_style > 1) { $explorer_list_style = 0; }

$images_extensions = array("gif", "png", "jpg", "jpeg", "jpc", "jp2", "jpx", "xbm", "psd", "bmp", "tiff", "swf", "swc");

// Функция проверяет переданный путь (который должен быть указан относительно корня)
// И если в итоге он находиться внутри корня - то возвращает этот путь, иначе возвращаеться false.
// Функция возвращает путь только для существующих файлов.
function get_real_path($path)
{
	$current_folder = realpath(ROOT_FOLDER . "/" . $path);
	if ($current_folder !== false)
	{
		$current_folder = normalize_path($current_folder);
		if (strpos($current_folder, ROOT_FOLDER) !== 0) { $current_folder = false; }
		else { $current_folder = (string) substr($current_folder, strlen(ROOT_FOLDER) + 1); }
	}
	return $current_folder;
}

function valid_file_name($name) { return preg_match("/^[a-zA-Z0-9_\.-]+$/u", trim($name)); }

if (action() == "index") { layout(CMS_PREFIX . "/frames"); }

if (action() == "explorer" || action() == "load-files")
{
	if (action() == "load-files") { layout(false); }

	$highlight_node = false;

	if (!isset($_GET["path"]) && isset($_COOKIE["__cms__browser_latest_path"]))
	{
		$path = normalize_path(PUBLIC_DIR . "/" . $_COOKIE["__cms__browser_latest_path"]);
		$path = substr_ltrim($path, ROOT_FOLDER . "/");
		$path = get_real_path($path);
		if ($path !== false) { $_GET["path"] = $path; }
	}

	if (isset($_GET["find_path"]))
	{
		$find_path = normalize_path(PUBLIC_DIR . "/" . $_GET["find_path"]);
		$find_path = substr_ltrim($find_path, ROOT_FOLDER . "/");
		$find_path = get_real_path($find_path);
		if ($find_path !== false)
		{
			$_GET["path"] = dirname($find_path);
			$highlight_node = basename($find_path);
		}
	}

	$current_folder = get_real_path(@trim($_GET["path"]));
	if ($current_folder === false) { $current_folder = ""; }

	$full_current_path = ROOT_FOLDER . "/" . $current_folder;
	if (!is_dir($full_current_path)) { $full_current_path = ROOT_FOLDER; }

	setcookie("__cms__browser_latest_path", substr_ltrim($full_current_path, PUBLIC_DIR . "/"));

	// Переходим в текущий каталог
	chdir($full_current_path);

	$folders = array(); $files = array();
	$list = glob("*");
	foreach ($list as $item)
	{
		$full_path = $full_current_path . "/" . $item;

		if (is_dir($full_path))
		{
			if ($item == "__thumbs_cache") { continue; }
			$nodes_list = glob($full_path . "/*");
			$files_count = 0; $folders_count = 0;
			foreach ($nodes_list as $node)
			{
				if (is_dir($node)) { if (!preg_match("/\/__thumbs_cache$/sux", $node)) $folders_count++; }
				elseif (is_file($node)) { $files_count++; }
			}

			$info = array
			(
				"name" => $item,
				"path" => $full_path,
				"size" => null,
				"ext" => null,
				"icon" => CMS_PUBLIC_URI . "/pics/icons/folder.icon.gif",
				"icon32" => CMS_PUBLIC_URI . "/pics/icons/32/folder.icon.gif",
				"mtime" => filemtime($full_path),
				"type" => "folder",
				"url" => substr(normalize_path($full_current_path . "/" . $item), strlen(PUBLIC_DIR) + 1),
				"folders_count" => $folders_count,
				"files_count" => $files_count,
			);

			$folders[] = $info;
		}
		elseif (is_file($full_path))
		{
			$info = array
			(
				"name" => $item,
				"path" => $full_path,
				"size" => filesize($full_current_path . "/" . $item),
				"ext" => get_file_extension($item),
				"mtime" => filemtime($full_path),
				"type" => "file",
				"url" => substr(normalize_path($full_current_path . "/" . $item), strlen(PUBLIC_DIR) + 1)
			);

			if (file_exists(PUBLIC_DIR . "/" . CMS_PUBLIC_URI . "/pics/icons/" . ltrim($info["ext"], ".") . ".gif"))
			{ $info["icon"] = CMS_PUBLIC_URI . "/pics/icons/" . ltrim($info["ext"], ".") . ".gif"; }
			else { $info["icon"] = CMS_PUBLIC_URI . "/pics/icons/default.icon.gif"; }

			if (file_exists(PUBLIC_DIR . "/" . CMS_PUBLIC_URI . "/pics/icons/32/" . ltrim($info["ext"], ".") . ".gif"))
			{ $info["icon32"] = CMS_PUBLIC_URI . "/pics/icons/32/" . ltrim($info["ext"], ".") . ".gif"; }
			else { $info["icon32"] = CMS_PUBLIC_URI . "/pics/icons/32/default.icon.gif"; }

			$files[] = $info;
		}
	}

	$items_list = array_merge($folders, $files);
}

if (action() == "create-folder")
{
	$_PAGE["main_bg"] = "threedface-bg";

	$current_folder = get_real_path(@trim($_GET["parent_folder"]));
	if ($current_folder === false) { render_text("Invalid path: " . h(@trim($_GET["parent_folder"]))); }

	$form = new FormProcessor();
	if (@$_POST["do"] == "create")
	{
		$new_folder = ROOT_FOLDER . "/" . $current_folder . "/" . trim($_POST["name"]);

		if ($form->check("name", "entered"))
		{
			if (!valid_file_name($_POST["name"])) { $form->set_error("name", "Неверное имя: должно содержать только: \"a-z, A-Z, 0-9, _, -, .\""); }
			elseif (file_exists($new_folder)) { $form->set_error("name", "Файл или папка с таким именем уже существует"); }
		}

		if (!$form->is_form_error())
		{
			if (false === @mkdir($new_folder))
			{ $form->set_error("name", "Ошибка: " . @$php_errormsg); }
		}
	}
}

if (action() == "delete")
{
	layout(false);

	$error_message = "";
	$current_folder = get_real_path(@trim($_GET["parent_folder"]));
	if ($current_folder === false) { render_text("Invalid path: " . h(@trim($_GET["parent_folder"]))); }

	if (isset($_POST["nodes"]) && is_array($_POST["nodes"]))
	{
		foreach ($_POST["nodes"] as $name)
		{
			$name = get_real_path($current_folder . "/" . trim($name));
			if ($name !== false)
			{
				if (false === @rmdirs(ROOT_FOLDER . "/" . $name))
				{ $error_message = "Ошибка: " . $GLOBALS["php_errormsg"]; }
			}
		}
	}
	else { render_nothing(); }
}

if (action() == "rename")
{
	layout(false);
	$error_message = "";

	$current_folder = get_real_path(@trim($_GET["parent_folder"]));
	if ($current_folder !== false && isset($_POST["old_name"]) && isset($_POST["new_name"]) && isset($_POST["node_id"]) && valid_file_name(trim($_POST["old_name"])))
	{
		$old_path = get_real_path($current_folder . "/" . trim($_POST["old_name"]));
		if (false !== $old_path)
		{
			$old_path = normalize_path(ROOT_FOLDER . "/" . $old_path);
			if (valid_file_name(trim($_POST["new_name"])))
			{
				$new_path = normalize_path(ROOT_FOLDER . "/" . $current_folder . "/" . trim($_POST["new_name"]));
				if ($new_path != $old_path)
				{
					if (!file_exists($new_path))
					{
						if (!is_writable($old_path)) { $error_message = "ОШИБКА! Недостаточно прав для переименования \"" . basename($old_path) . "\"."; }
						elseif (!is_writable(dirname($new_path))) { $error_message = "ОШИБКА! Недостаточно прав для переименования в \"" . basename($new_path) . "\"."; }
						elseif (false === @rename($old_path, $new_path)) { $error_message = "ОШИБКА! " . $php_errormsg; }
						else
						{
							cms_catch_db_error();
							db_begin();

							// Переименуем значения полей во всех таблицах
							$rel_old_path = substr_ltrim($old_path, PUBLIC_DIR . "/");
							$rel_new_path = substr_ltrim($new_path, PUBLIC_DIR . "/");
							$file_fields = db_select_all("SELECT type, field_name FROM cms_metadata WHERE field_type IN ('file', 'image', 'flash')");

							foreach ($file_fields as $item)
							{ db_update($item["type"], array($item["field_name"] => $rel_new_path), array($item["field_name"] => $rel_old_path)); }

							if (is_dir($new_path))
							{
								foreach ($file_fields as $item)
								{ db_update($item["type"], array($item["field_name"] => db_raw(db_str($rel_new_path) . "||SUBSTR(" . db_escape_ident($item["field_name"]) . ", LENGTH(" . db_str($rel_old_path) . ") + 1)")), array(db_escape_ident($item["field_name"]) . " LIKE " . db_like($rel_old_path . "/_%"))); }
							}

							$db_error = cms_get_db_error();
							if ($db_error) { $error_message = "Ошибка базы данных: " . $db_error; db_rollback(); } else { db_commit(); }
						}
					}
					else { $error_message = "ОШИБКА! Файл или папка с таким именем уже существует"; }
				}
			}
			else { $error_message = "ОШИБКА! Неверное имя: должно содержать только: \"a-z, A-Z, 0-9, _, -, .\""; }
		}
	}
}

if (action() == "uploader") { $_PAGE["main_bg"] = "threedface-bg"; }

if (action() == "upload-file")
{
	$error_message = null;

	$current_folder = get_real_path(@trim($_POST["parent_folder"]));
	if ($current_folder === false) { $error_message = "Invalid path: " . h(@trim($_POST["parent_folder"])); return; }

	$_FILES = Upload::normalize($_FILES);
	if (isset($_FILES["file"]) && count($_FILES["file"]))
	{
		foreach ($_FILES["file"] as $id => $file)
		{
			$file = Upload::fetch($file, array("no_data" => true));
			if ($file["errcode"]) { $error_message = "ОШИБКА! " . $file["errmsg"]; }
			elseif ($file["uploaded"])
			{
				$file_name = strtolower($file["name"]);

				// Если имя файла не верное - пытаемся преобразовать к верному
				if (!valid_file_name($file_name)) { $file_name = cms_normalize_file_name($file_name); }

				$file_path = normalize_path(ROOT_FOLDER . "/" . $current_folder . "/" . $file_name);

				if (file_exists($file_path) && @!$_POST["is_overwrite"]) { $error_message = "ОШИБКА! Файл с именем \"" . basename($file_path) . "\" уже существует."; }
				elseif (!is_writable(dirname($file_path))) { $error_message = "ОШИБКА! Недостаточно прав для записи в папку \"" . dirname($file_path) . "\"."; }
				elseif (file_exists($file_path) && !is_writable($file_path)) { $error_message = "ОШИБКА! Недостаточно прав для перезаписи файла \"" . basename($file_path) . "\"."; }
				else
				{
					if (false === @move_uploaded_file($file["path"], $file_path))
					{ $error_message = "ОШИБКА! Не удалось записать файл: " . $php_errormsg; }
				}
			}
			else
			{
				if ($_SERVER["REQUEST_METHOD"] == "POST")
				{
					if (@$_SERVER["CONTENT_LENGTH"] > bytes_from_size(ini_get("post_max_size")))
					{ $error_message = "Превышено ограничение объёма передаваемых данных: " . human_size(ini_get("post_max_size")); return; }
				}
				$error_message = "Прикрепите файл!" . $file["errmsg"];
			}
			if (!is_empty($error_message)) { break; }
		}
	}
	else { $error_message = "Прикрепите файл!"; }
}

if (action() == "thumb")
{
	$image_path = get_real_path(trim($_GET["parent_folder"]) . "/" . trim($_GET["img_name"]));
	if ($image_path !== false)
	{
		$image_path = ROOT_FOLDER . "/" . $image_path;
		$cache_path = dirname($image_path) . "/__thumbs_cache/" . basename($image_path);

		if (!(file_exists($cache_path) && is_readable($cache_path)) || @filemtime($image_path) > @filemtime($cache_path))
		{
			$img = Image::transform($image_path, array("resize_width" => 120, "resize_height" => 64));
			@mkdirs(dirname($cache_path));
			file_put_contents($cache_path, $img["data"]);
		}
		else { $img = Image::transform($cache_path); }

		header("Content-Type: " . $img["type"]);
		header("Content-Length: " . strlen($img["data"]));
		echo $img["data"];

		exit;
	}
	else { die("Invalid image path provided"); }
}