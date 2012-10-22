<?php
require_once("api.php");

if (preg_match("/^" . CMS_PREFIX . "\//u", controller()))
{
	global $_PAGE; $_PAGE = array();
	require_once("helpers.php");
	if (!(controller() == CMS_PREFIX . "/index" && action() == "schema"))
	{
		if (controller() == CMS_PREFIX . "/index" && action() == "index")
		{
			$oid = @db_select_cell("SELECT 'cms_nodes'::regclass::oid");
			if ($oid === false) { redirect_to("action", "schema"); }
			if (false === @db_select_cell("SELECT oid FROM pg_class WHERE oid = ?i AND relkind = 'r'", $oid)) { redirect_to("action", "schema"); }
		}
		Session::start("cms_auth_id");
		if (controller() != CMS_PREFIX . "/index") { require("auth.php"); }
		HTTP::no_cache();
		cms_set_view_mode(0);
		layout(CMS_PREFIX . "/html");
	}
}
else
{
	cms_set_view_mode(1);
	ob_start('cms_ob_url_replace');
	ob_start('cms_ob_translate_strings');
}