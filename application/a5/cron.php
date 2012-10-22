<?php
/*
Типовой шаблон крон-скрипта
<?
require("application/config/environment.php");
// LOCKS_DIR предварительно лучше определить в конфиг-файле приложения, если вторым
// параметром ничего не передать - то по-дефолту это будет текущая папка
start_process("Sample cron job", LOCKS_DIR);
// Далее производятся нужные действия, все отладочные сообщения выводим через logmsg($str)
*/
set_time_limit(0);
ini_set("output_buffering", "off");

// Функция для вывода лог-сообщений с буферизацией
// Сообщения записываются в буффер до первого вызова logmsg
// При первом вызове - выводятся сначала все буфферизированные сообщения,
// после этого вызов данной функции будет аналогичен вызову logmsg
// С помощью этой функции полезно выводить сообщения, которые не имеют
// никакой важности в случае если скрипт не выводит никакой другой важной
// информации, таким образом не будут засоряться лог-файлы крон-скриптов
function logmsg_delayed()
{
	static $messages = array();
	static $is_buffer_flushed = false;
	$args = func_get_args();
	if (count($args))
	{
		if (!$is_buffer_flushed) { $messages[] = call_user_func_array("logmsg_format", $args); }
		else { call_user_func_array("logmsg", $args); }
	}
	elseif (!$is_buffer_flushed)
	{
		$is_buffer_flushed = true;
		foreach ($messages as $message) { echo $message; }
	}
}

// Функция для вывода лог-сообщений
// Используется в cron-скриптах
function logmsg()
{
	static $is_buffer_flushed = false;
	@flush(); while (@ob_end_flush());
	if (!$is_buffer_flushed) { $is_buffer_flushed = true; logmsg_delayed(); }
	$args = func_get_args(); echo call_user_func_array("logmsg_format", $args);
	@flush(); while (@ob_end_flush());
}

function logmsg_format()
{
	$args = func_get_args();
	return sprintf("[%s %d] ", date('Y-m-d H:i:s T'), getmypid()) . @call_user_func_array('sprintf', $args) . "\n";
}

// Функция предотвращения ситуаций когда запускается сразу несколько скриптов
function start_process($name, $locks_dir = ".")
{
	if (@!is_empty($_SERVER["PHP_SELF"])) { $process_file = $_SERVER["PHP_SELF"]; }
	elseif (@!is_empty($_SERVER["argv"][0])) { $process_file = $_SERVER["argv"][0]; }

	logmsg_delayed("%s: process started (%s) ...", $name, $process_file);

	$lock_file = normalize_path($locks_dir . "/" . basename($process_file) . ".lock");
	$lock_dir = dirname($lock_file);
	if (!is_dir($lock_dir))
	{
		$r = @mkdirs($lock_dir);
		if (!$r) { logmsg("Cannot create %s: %s, finish", $lock_dir, $GLOBALS["php_errormsg"]); exit; }
	}

	$lock = @fopen($lock_file, "a+");
	if (!$lock) { $lock = @fopen($lock_file, "r+"); }
	if (!$lock) { logmsg("Cannot create/open %s: %s, finish.", $lock_file, $php_errormsg); exit; }
	$is_locked = !flock($lock, LOCK_EX | LOCK_NB);
	if ($is_locked) { logmsg_delayed("Another process is already running, finish."); exit; }
	ftruncate($lock, 0); fseek($lock, 0); fwrite($lock, getmypid());
	logmsg_delayed("Lock created ($lock_file) ...");

	A5::$cron_jobs[getmypid()] = array($lock, $lock_file);
	register_shutdown_function("finish_process");
}

function finish_process()
{
	if (@is_array(A5::$cron_jobs[getmypid()]))
	{
		$lock = A5::$cron_jobs[getmypid()][0];
		$lock_file = A5::$cron_jobs[getmypid()][1];
		if (file_exists($lock_file) && $lock)
		{
			fclose($lock); @unlink($lock_file); unset(A5::$cron_jobs[getmypid()]);
			logmsg_delayed("Process finished. Lock unlinked (%s).", $lock_file);
		}
		else { logmsg("Lock file not exists, may be process not started? finish."); }
	}
	exit;
}