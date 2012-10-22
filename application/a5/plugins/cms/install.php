<?php
$prefix = "plugins/cms/";

$CMS_MODULES_DIR = !defined("CMS_MODULES_DIR") ? normalize_path(APP_DIR . "/cms-modules") : CMS_MODULES_DIR;
$CMS_PUBLIC_URI = !defined("CMS_PUBLIC_URI") ? "cms-data" : CMS_PUBLIC_URI;
$CMS_PREFIX = !defined("CMS_PREFIX") ? "cms" : CMS_PREFIX;

A5_APP_Plugin::install_application($prefix . "modules", substr_ltrim($CMS_MODULES_DIR, APP_DIR . "/"));
A5_APP_Plugin::install_controllers($prefix . "controllers", $CMS_PREFIX);
A5_APP_Plugin::clean_controllers($prefix . "controllers", $CMS_PREFIX);
A5_APP_Plugin::install_layouts($prefix . "layouts", $CMS_PREFIX);
A5_APP_Plugin::clean_layouts($prefix . "layouts", $CMS_PREFIX);
A5_APP_Plugin::install_views($prefix . "views", $CMS_PREFIX);
A5_APP_Plugin::clean_views($prefix . "views", $CMS_PREFIX);
A5_APP_Plugin::install_public($prefix . "public", $CMS_PUBLIC_URI);
A5_APP_Plugin::clean_public($prefix . "public", $CMS_PUBLIC_URI);
