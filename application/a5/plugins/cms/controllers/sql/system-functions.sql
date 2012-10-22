CREATE OR REPLACE FUNCTION cms_get_node_parents(INOUT node_id bigint, OUT node_level integer) RETURNS SETOF record
AS $$
DECLARE
	rec RECORD;
	prev_path TEXT := '';
	node_mt_path TEXT := '';
	pos INTEGER;
	parent_pathes TEXT[];
BEGIN
	SELECT INTO node_mt_path mt_path FROM v_cms_nodes WHERE id = node_id;
	IF NOT FOUND THEN RETURN; END IF;
	
	pos := NULL;
	WHILE pos IS NULL OR pos > 0 LOOP
		pos = strpos(node_mt_path, '.');
		IF length(prev_path) > 0 THEN prev_path = prev_path || '.'; END IF;
		IF pos > 0 THEN
			prev_path = prev_path || substr(node_mt_path, 1, pos - 1);
			SELECT INTO parent_pathes array_append(parent_pathes, prev_path);
			node_mt_path = substr(node_mt_path, pos + 1);
		ELSE
			prev_path = prev_path || substr(node_mt_path, 1);
			SELECT INTO parent_pathes array_append(parent_pathes, prev_path);
			EXIT;
		END IF;
	END LOOP;

	FOR rec IN SELECT id, level FROM v_cms_nodes WHERE mt_path = ANY (parent_pathes) LOOP
		node_id := rec.id;
		node_level := rec.level;
		RETURN NEXT;
	END LOOP;
	RETURN;
END;
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION cms_get_nodes_tree(INOUT node_id bigint, IN maxlevel bigint, OUT node_level bigint) RETURNS SETOF record
AS $$
DECLARE
	rec RECORD;
	rec_childs RECORD;
	root_node_id BIGINT;
	root_mt_path TEXT;
	root_node_level INTEGER;
	where_cond TEXT := '';
	node_parents INTEGER[];
BEGIN
	-- Если указали корневую ноду - вытаскиваем её уровень и путь
	IF node_id IS NOT NULL THEN
		SELECT INTO root_mt_path, root_node_level mt_path, level FROM v_cms_nodes WHERE id = node_id;
		-- Если такой ноды нет - возвращаем пустой набор
		IF NOT FOUND THEN RETURN; END IF;
	END IF;

	-- Если указали корневую ноду - добавляем условие для извлечения её и всех её детей
	IF node_id IS NOT NULL THEN
		IF LENGTH(where_cond) > 0 THEN where_cond = where_cond || ' AND '; END IF;
		where_cond = where_cond || '(id = ' || QUOTE_LITERAL(node_id) || ' OR mt_path LIKE ' || QUOTE_LITERAL(root_mt_path || '.%') || ')';
	END IF;

	-- Если указали максимальный уровень	
	IF maxlevel IS NOT NULL THEN
		IF LENGTH(where_cond) > 0 THEN where_cond = where_cond || ' AND '; END IF;
		IF node_id IS NOT NULL THEN
			where_cond = where_cond || '(level >= ' || QUOTE_LITERAL(root_node_level) || ' AND level <= ' || QUOTE_LITERAL(root_node_level + maxlevel) || ')';
		ELSE
			where_cond = where_cond || '(level <= ' || QUOTE_LITERAL(maxlevel) || ')';
		END IF;
	END IF;

	IF LENGTH(where_cond) > 0 THEN where_cond = 'WHERE ' || where_cond; END IF;

	FOR rec IN EXECUTE 'SELECT id, parent_id, level FROM v_cms_nodes ' || where_cond || ' ORDER BY mt_path' LOOP
		IF node_parents[rec.level - 1] IS NOT NULL THEN
			IF node_parents[rec.level - 1] != rec.parent_id THEN CONTINUE; END IF;
		END IF;

		node_parents[rec.level] = rec.id;

		node_id := rec.id;
		node_level := rec.level - CASE WHEN root_node_level IS NOT NULL THEN root_node_level ELSE 0 END;
		RETURN NEXT;
	END LOOP;
	RETURN;
END;
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION cms_get_nodes_tree(INOUT node_id bigint, OUT node_level bigint) RETURNS SETOF record
AS $$
SELECT * FROM cms_get_nodes_tree($1, null);
$$
LANGUAGE SQL;

CREATE OR REPLACE FUNCTION num2str(IN num bigint) RETURNS text
AS $$
BEGIN
	RETURN num2str(num, 0);
END
$$ LANGUAGE plpgsql IMMUTABLE RETURNS NULL ON NULL INPUT;

CREATE OR REPLACE FUNCTION num2str(IN num bigint, IN pad integer) RETURNS text
AS $$
DECLARE
	result TEXT := '';
	divided BIGINT := 0;
	sign TEXT := '';
BEGIN
	IF num < 0 THEN divided = num * -1; sign = '-'; ELSE divided := num; END IF;
	LOOP
		result := CASE divided % 36
		WHEN 0 THEN '0'
		WHEN 1 THEN '1'
		WHEN 2 THEN '2'
		WHEN 3 THEN '3'
		WHEN 4 THEN '4'
		WHEN 5 THEN '5'
		WHEN 6 THEN '6'
		WHEN 7 THEN '7'
		WHEN 8 THEN '8'
		WHEN 9 THEN '9'
		WHEN 10 THEN 'a'
		WHEN 11 THEN 'b'
		WHEN 12 THEN 'c'
		WHEN 13 THEN 'd'
		WHEN 14 THEN 'e'
		WHEN 15 THEN 'f'
		WHEN 16 THEN 'g'
		WHEN 17 THEN 'h'
		WHEN 18 THEN 'i'
		WHEN 19 THEN 'j'
		WHEN 20 THEN 'k'
		WHEN 21 THEN 'l'
		WHEN 22 THEN 'm'
		WHEN 23 THEN 'n'
		WHEN 24 THEN 'o'
		WHEN 25 THEN 'p'
		WHEN 26 THEN 'q'
		WHEN 27 THEN 'r'
		WHEN 28 THEN 's'
		WHEN 29 THEN 't'
		WHEN 30 THEN 'u'
		WHEN 31 THEN 'v'
		WHEN 32 THEN 'w'
		WHEN 33 THEN 'x'
		WHEN 34 THEN 'y'
		WHEN 35 THEN 'z'
		END || result;
		divided := divided / 36;
		IF divided < 1 THEN EXIT; END IF;
	END LOOP;
	IF pad IS NOT NULL AND pad > 0 THEN
		IF length(result) < pad THEN result := repeat('0', pad - length(result)) || result; END IF;
	END IF;
	RETURN sign || result;
END
$$ LANGUAGE plpgsql IMMUTABLE RETURNS NULL ON NULL INPUT;

CREATE OR REPLACE FUNCTION cms_node_extras_aiud_tr_func() RETURNS "trigger"
AS $$
DECLARE
	node_type varchar(255);
BEGIN
	IF tg_op = 'INSERT' THEN
		SELECT INTO node_type type FROM cms_nodes WHERE id = new.id;
		IF NOT FOUND THEN
			RAISE EXCEPTION 'Неизвестный id объекта: %. Сначала добавьте его в cms_nodes', new.id;
		END IF;

		EXECUTE '
			UPDATE
				'||QUOTE_IDENT(node_type)||'
			SET
				is_hidden = '||QUOTE_LITERAL(new.is_hidden)||'
			WHERE
				id = '||QUOTE_LITERAL(new.id)||'
				AND lang '||CASE WHEN new.lang IS NULL THEN 'IS NULL' ELSE '= '||QUOTE_LITERAL(new.lang) END||'
		';
	END IF;

	-- Если удаляем
	IF tg_op = 'DELETE' THEN
		SELECT INTO node_type type FROM cms_nodes WHERE id = old.id;
		-- И при этом в cms_nodes запись осталась
		IF FOUND THEN
			-- Устанавливаем флаг что объект виден (по-умолчанию)
			EXECUTE '
			UPDATE
				'||QUOTE_IDENT(node_type)||'
			SET
				is_hidden = 0
			WHERE
				id = '||QUOTE_LITERAL(old.id)||'
				AND lang '||CASE WHEN old.lang IS NULL THEN 'IS NULL' ELSE '= '||QUOTE_LITERAL(old.lang) END||'
			';
		END IF;
	END IF;

	-- При изменении, если меняется флаг видимости
	-- То обновляем поле в типовой таблице
	IF tg_op = 'UPDATE' THEN
		IF new.is_hidden != old.is_hidden THEN
			SELECT INTO node_type type FROM cms_nodes WHERE id = new.id;
			IF NOT FOUND THEN
				RAISE EXCEPTION 'Неизвестный id объекта: %. Сначала добавьте его в cms_nodes', new.id;
			END IF;

			EXECUTE '
			UPDATE
				'||QUOTE_IDENT(node_type)||'
			SET
				is_hidden = '||QUOTE_LITERAL(new.is_hidden)||'
			WHERE
				id = '||QUOTE_LITERAL(new.id)||'
				AND lang '||CASE WHEN new.lang IS NULL THEN 'IS NULL' ELSE '= '||QUOTE_LITERAL(new.lang) END||'
			';
			END IF;
	END IF;

	RETURN NULL;
END;
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION cms_nodes_aiud_tr_func() RETURNS "trigger"
AS $$
BEGIN
	IF tg_op = 'UPDATE' THEN
		-- Если у объекта поменялся level или mt_path то нужно запустить
		-- каскадное обновление всех его детей
		IF
		(new.level != old.level)
		OR (new.level IS NULL AND old.level IS NOT NULL)
		OR (new.level IS NOT NULL AND old.level IS NULL)
		OR (new.mt_path != old.mt_path)
		THEN
			-- Устанавливаем у всех детей level = new.level + 1
			-- Запустится тригер BEFORE где при изменении level будет просчитываться
			-- по-новой на основе родительской ветки
			UPDATE
				cms_nodes
			SET
				level = new.level + 1,
				mt_path = new.mt_path||'.'||num2str(id, 13)
			WHERE
				parent_id = new.id;
		END IF;

		-- Если изменился тип ноды, то нужно из старой типовой таблицы
		-- Удалить все записи с этим id (это мусор)
		IF new.type != old.type THEN
			EXECUTE 'DELETE FROM '||QUOTE_IDENT(old.type)||' WHERE id = '||QUOTE_LITERAL(new.id);
		END IF;

		-- Если изменился parent_id, url_id, sibling_index или system_name
		-- То нужно обновить их в типовой таблице тоже
		IF
		(new.parent_id != old.parent_id)
		OR (new.parent_id IS NULL AND old.parent_id IS NOT NULL)
		OR (new.parent_id IS NOT NULL AND old.parent_id IS NULL)
		OR (new.url_id != old.url_id)
		OR (new.url_id IS NULL AND old.url_id IS NOT NULL)
		OR (new.url_id IS NOT NULL AND old.url_id IS NULL)
		OR (new.system_name != old.system_name)
		OR (new.system_name IS NULL AND old.system_name IS NOT NULL)
		OR (new.system_name IS NOT NULL AND old.system_name IS NULL)
		OR (new.sibling_index != old.sibling_index)
		OR (new.sibling_index IS NULL AND old.sibling_index IS NOT NULL)
		OR (new.sibling_index IS NOT NULL AND old.sibling_index IS NULL)
		THEN
			EXECUTE '
			UPDATE
				'||QUOTE_IDENT(new.type)||'
			SET
				parent_id = '||COALESCE(QUOTE_LITERAL(new.parent_id), 'NULL')||',
				url_id = '||COALESCE(QUOTE_LITERAL(new.url_id), 'NULL')||',
				system_name = '||COALESCE(QUOTE_LITERAL(new.system_name), 'NULL')||',
				sibling_index = '||COALESCE(QUOTE_LITERAL(new.sibling_index), 'NULL')||'
			WHERE
				id = '||QUOTE_LITERAL(new.id)||'
			';
		END IF;
	END IF;

	RETURN NULL;
END;
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION cms_nodes_biud_tr_func() RETURNS "trigger"
AS $$
DECLARE
	is_do_update_sibling_index SMALLINT = 0;
	is_do_parent_type_check SMALLINT = 0;
	is_do_parent_check SMALLINT = 0;
	is_do_update_level SMALLINT = 0;
	is_can_have_that_child SMALLINT = 0;
	is_do_construct_mt_path SMALLINT = 0;
	node_name varchar(255);
	tmp_text TEXT;
	rec RECORD;
BEGIN
	IF tg_op = 'INSERT' OR tg_op = 'UPDATE' THEN
		IF new.parent_id IS NULL THEN
			new.level = 0;
		END IF;

		IF new.type IS NULL THEN
			RAISE EXCEPTION 'Тип документа должен быть указан';
		END IF;

		IF new.url_id IS NOT NULL THEN
			IF EXISTS(SELECT id FROM cms_nodes WHERE url_id = new.url_id AND id != new.id AND type = new.type) THEN
				RAISE EXCEPTION 'УРЛ имя "%" уже существует для данного типа документов', new.url_id;
			END IF;
		END IF;

		-- Перед INSERT или UPDATE мы должны сформировать новый url_id
		IF tg_op = 'INSERT' OR tg_op = 'UPDATE' THEN
			new.url_id = COALESCE(new.url_id, CAST(new.id AS TEXT));
		END IF;

		IF tg_op = 'INSERT' THEN
			is_do_update_sibling_index = 1;
			is_do_construct_mt_path = 1;
			IF new.parent_id IS NOT NULL THEN is_do_parent_type_check = 1; END IF;
			IF new.parent_id IS NOT NULL THEN is_do_update_level = 1; END IF;
		END IF;

		IF tg_op = 'UPDATE' THEN
			IF
			(new.parent_id != old.parent_id)
			OR (new.parent_id IS NULL AND old.parent_id IS NOT NULL)
			OR (new.parent_id IS NOT NULL AND old.parent_id IS NULL)
			THEN
				is_do_update_sibling_index = 1;
				is_do_construct_mt_path = 1;
				IF new.parent_id IS NOT NULL THEN is_do_parent_type_check = 1; END IF;
				IF new.parent_id IS NOT NULL THEN is_do_parent_check = 1; END IF;
				IF new.parent_id IS NOT NULL THEN is_do_update_level = 1; END IF;
			END IF;
			
			IF new.id != old.id THEN
				is_do_construct_mt_path = 1;
			END IF;

			IF new.type != old.type THEN
				IF new.parent_id IS NOT NULL THEN is_do_parent_type_check = 1; END IF;
			END IF;

			IF
			(new.level != old.level)
			OR (new.level IS NULL AND old.level IS NOT NULL)
			OR (new.level IS NOT NULL AND old.level IS NULL)
			THEN
				IF new.parent_id IS NOT NULL THEN is_do_update_level = 1; END IF;
			END IF;
		END IF;

		-- Проверяем что новый родитель не является потомком документа
		IF is_do_parent_check = 1 THEN
			IF new.id IN (SELECT node_id FROM cms_get_node_parents(new.parent_id)) THEN
				SELECT INTO tmp_text name FROM v_cms_nodes WHERE id = new.parent_id;
				RAISE EXCEPTION 'Документ "%" является потомком', tmp_text;
			END IF;
		END IF;

		-- Проверяем что указанный родитель может иметь детей такого типа
		IF is_do_parent_type_check = 1 THEN
			-- Если для родителя указаны свои - специфичные потомки - проверяем среди них
			-- Иначе проверяем среди потомков для данного типа
			IF EXISTS(SELECT id FROM cms_node_childs WHERE id = new.parent_id) THEN
				IF EXISTS
				(
				SELECT
					*
				FROM
					cms_node_childs
				WHERE
					id = new.parent_id
					AND child_type = new.type
				)
				THEN
					is_can_have_that_child = 1;
				END IF;
			ELSE
				IF EXISTS
				(
				SELECT
					*
				FROM
					cms_nodes n
					JOIN cms_node_type_childs ntc ON n.type = ntc.type
				WHERE
					n.id = new.parent_id
					AND ntc.child_type = new.type
				)
				THEN
					is_can_have_that_child = 1;
				END IF;
			END IF;

			-- Если не можем иметь таких детей
			IF is_can_have_that_child = 0 THEN
				SELECT INTO tmp_text name FROM v_cms_nodes WHERE id = new.parent_id;
				RAISE EXCEPTION 'Документ "%" не может иметь потомков такого типа', tmp_text;
			END IF;
		END IF;

		IF is_do_update_sibling_index = 1 THEN
			IF new.parent_id IS NOT NULL THEN
				SELECT INTO new.sibling_index COALESCE(MAX(sibling_index) + 1, 0) FROM cms_nodes WHERE parent_id = new.parent_id;
			ELSE
				SELECT INTO new.sibling_index COALESCE(MAX(sibling_index) + 1, 0) FROM cms_nodes WHERE parent_id IS NULL;
			END IF;
		END IF;

		IF is_do_construct_mt_path = 1 THEN
			IF new.parent_id IS NOT NULL THEN
				SELECT INTO new.mt_path mt_path||'.'||num2str(new.id, 13) FROM cms_nodes WHERE id = new.parent_id;
			ELSE
				new.mt_path = num2str(new.id, 13);
			END IF;
		END IF;

		IF is_do_update_level = 1 THEN
			SELECT INTO new.level COALESCE(level + 1, 0) FROM cms_nodes WHERE id = new.parent_id;
		END IF;
	END IF;

	-- Нельзя удалить документ имеющий системное имя, назначенный особый controller,
	-- action или собственные типы потомков
	IF tg_op = 'DELETE' THEN
		IF
		old.system_name IS NOT NULL
		OR old.controller IS NOT NULL
		OR old.action IS NOT NULL
		OR EXISTS(SELECT * FROM cms_node_childs WHERE id = old.id LIMIT 1)
		THEN
			SELECT INTO node_name name FROM v_cms_nodes WHERE id = old.id;
			RAISE EXCEPTION 'Нельзя удалить системный документ "%"', node_name;
		END IF;
	END IF;

	IF tg_op = 'DELETE' THEN
		RETURN old;
	ELSE
		RETURN new;
	END IF;
END;
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION cms_update_sf_biud_tr_func() RETURNS "trigger"
AS $$
DECLARE
	rec RECORD;
BEGIN
	-- Это тригерная общая функция для каждой типовой таблицы
	-- Устанавливаем общие поля только когда сюда заносится запись
	IF tg_op = 'INSERT' THEN
		SELECT INTO rec
			n.parent_id,
			n.url_id,
			n.system_name,
			n.sibling_index,
			COALESCE(ne.is_hidden, 0) as is_hidden
		FROM
			cms_nodes n
			JOIN cms_node_types nt ON nt.type = n.type
			LEFT JOIN cms_node_extras ne ON n.id = ne.id AND ((nt.is_lang = 1 AND ne.lang = new.lang) OR (nt.is_lang = 0 AND ne.lang IS NULL))
		WHERE
			n.id = new.id;

		IF NOT FOUND THEN
			RAISE EXCEPTION 'Неизвестный id объекта: %. Сначала добавьте его в cms_nodes', new.id;
		END IF;

		new.parent_id = rec.parent_id;
		new.url_id = rec.url_id;
		new.system_name = rec.system_name;
		new.sibling_index = rec.sibling_index;
		new.is_hidden = rec.is_hidden;
	END IF;

	IF tg_op = 'DELETE' THEN
		RETURN old;
	ELSE
		RETURN new;
	END IF;
END;
$$
LANGUAGE plpgsql;