<?php
$_AUTH = array(); global $_AUTH;

$redirect_to_index = false;
if (!isset($_SESSION["cms_auth_id"]) || cms_is_blocked_ip($_SERVER["REMOTE_ADDR"])) { $redirect_to_index = true; }
else
{
	$account = db_select_row("
	SELECT
		au.id,
		au.login,
		au.pass,
		au.is_admin,
		EXTRACT(EPOCH FROM au.modified) as modified
	FROM
		cms_auth_users au
	WHERE
		au.id = ?i
	", $_SESSION["cms_auth_id"]);

	if (false !== $account)
	{
		if ($account["modified"] > time() - 7200)
		{
			db_update("cms_auth_users", array("modified" => db_raw("DATE_TRUNC('second', NOW())")), array("id" => $account["id"]));
			$account["roles"] = db_select_col("SELECT role_id FROM cms_auth_user_roles WHERE user_id = ?i", $account["id"]);
			$_AUTH = $account;
		}
		else { $redirect_to_index = true; }
	}
	else { $redirect_to_index = true; }
}

if ($redirect_to_index)
{
	echo "<HTML><BODY><script type=\"text/javascript\">top.location.href = '" . url_for("-controller", "index") . "';</SCRIPT></BODY></HTML>";
	exit;
}