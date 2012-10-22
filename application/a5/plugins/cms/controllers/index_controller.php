<?php
require_once("include/setup.php");

if (action() == "index")
{
	layout(false);
	$error_string = "";
	if (isset($_POST["login"]) && isset($_POST["password"]))
	{
		if (cms_is_blocked_ip($_SERVER["REMOTE_ADDR"]))
		{ $error_string = "<span style=\"color: #ff0000;\">Ваш IP временно заблокирован</span>"; }
		else
		{
			setcookie("cms_latest_login", $_POST["login"], time() + 86400 * 365 * 10, "/");

			$result = db_query("
			SELECT
				id,
				login,
				pass
			FROM
				cms_auth_users
			WHERE
				login = ?
				AND pass = ?
			", $_POST["login"], sha256(trim($_POST["password"])));

			if (false !== $row = db_fetch($result))
			{
				db_update("cms_auth_users", array("modified" => db_raw("DATE_TRUNC('second', NOW())")), array("id" => $row["id"]));
				// При правильном наборе пароля - счётчик сбрасываем
				db_delete("cms_auth_blocks", array("ip" => $_SERVER["REMOTE_ADDR"]));
				$_SESSION["cms_auth_id"] = $row["id"];
				redirect_to("-controller", "main");
			}
			else
			{
				if (false === $auth_block = db_select_cell("SELECT ip, failed_count FROM cms_auth_blocks WHERE ip = ?", $_SERVER["REMOTE_ADDR"]))
				{
					db_insert("cms_auth_blocks", array(
					"ip" => $_SERVER["REMOTE_ADDR"],
					"failed_count" => 1,
					"failed_time" => db_raw("DATE_TRUNC('second', NOW())")
					));
				}
				else
				{
					db_update("cms_auth_blocks", array(
					"failed_count" => db_raw("failed_count + 1"),
					"failed_time" => db_raw("DATE_TRUNC('second', NOW())")
					),
					array("ip" => $_SERVER["REMOTE_ADDR"]
					));
				}
				$error_string = "<span style=\"color: #ff0000;\">Не верный логин или пароль (попыток осталось: " . h(cms_is_blocked_ip($_SERVER["REMOTE_ADDR"], 1)) . ")</span>";
			}
		}
	}
	elseif (isset($_COOKIE["cms_latest_login"]))
	{ $_POST["login"] = $_COOKIE["cms_latest_login"]; }
}

if (action() == "schema") { layout(CMS_PREFIX . "/html"); }