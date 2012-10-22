CREATE OR REPLACE FUNCTION cms_get_current_language() RETURNS character
AS $$
DECLARE
	tmp_lang CHAR(2);
BEGIN
	-- Пробуем получить текущий язык, если не удалось - устанавливаем дефолтный
	BEGIN
		SELECT INTO tmp_lang current_lang FROM cms_tmp_current_language;
	EXCEPTION WHEN undefined_table THEN
		SELECT INTO tmp_lang current_lang FROM cms_set_current_language(null) cl(current_lang);
	END;
	RETURN tmp_lang;
END;
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION cms_get_view_mode() RETURNS smallint
AS $$
DECLARE
	tmp_view_mode SMALLINT;
BEGIN
	-- Пробуем получить текущий режим, если не удалось - устанавливаем дефолтный
	BEGIN
		SELECT INTO tmp_view_mode current_view_mode FROM cms_tmp_current_view_mode;
	EXCEPTION WHEN undefined_table THEN
		SELECT INTO tmp_view_mode current_view_mode FROM cms_set_view_mode(null) vm(current_view_mode);
	END;
	RETURN tmp_view_mode;
END;
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION cms_set_current_language(lang_name character) RETURNS character
AS $$
DECLARE
	tmp_lang char(2);
BEGIN
	-- Создаём временную таблицу если её ещё нет
	BEGIN
		CREATE TEMPORARY TABLE cms_tmp_current_language (current_lang CHAR(2));
	EXCEPTION WHEN duplicate_table THEN
	END;

	-- Если такого языка не существует - вытаскиваем дефолтный
	IF lang_name IS NULL THEN
		SELECT INTO tmp_lang lang FROM cms_languages WHERE is_default = 1 LIMIT 1;
	ELSE
		tmp_lang = lang_name;
	END IF;

	-- Если записи во временной таблице ещё нет - то записываем
	IF NOT EXISTS(SELECT * FROM cms_tmp_current_language) THEN
		INSERT INTO cms_tmp_current_language (current_lang) VALUES (tmp_lang);
	ELSE
		UPDATE cms_tmp_current_language SET current_lang = tmp_lang;
	END IF;

	RETURN tmp_lang;
END;
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION cms_set_view_mode(view_mode integer) RETURNS integer
AS $$
DECLARE
	tmp_view_mode integer;
BEGIN
	-- Создаём временную таблицу если её ещё нет
	BEGIN
		CREATE TEMPORARY TABLE cms_tmp_current_view_mode (current_view_mode smallint);
	EXCEPTION WHEN duplicate_table THEN
	END;

	IF view_mode IS NULL THEN
		tmp_view_mode = 0;
	ELSE
		tmp_view_mode = view_mode;
	END IF;

	-- Если записи во временной таблице ещё нет - то записываем
	IF NOT EXISTS(SELECT * FROM cms_tmp_current_view_mode) THEN
		INSERT INTO cms_tmp_current_view_mode (current_view_mode) VALUES (tmp_view_mode);
	ELSE
		UPDATE cms_tmp_current_view_mode SET current_view_mode = tmp_view_mode;
	END IF;

	RETURN tmp_view_mode;
END;
$$
LANGUAGE plpgsql;