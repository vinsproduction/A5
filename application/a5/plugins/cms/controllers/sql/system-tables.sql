SET default_with_oids = false;

CREATE TABLE cms_auth_roles (
	id bigserial PRIMARY KEY,
	name varchar(64) NOT NULL,
	default_auth bigint DEFAULT 0 NOT NULL
);

INSERT INTO cms_auth_roles (name, default_auth) VALUES ('Полный доступ', 15);

CREATE TABLE cms_auth_users (
	id bigserial PRIMARY KEY,
	"login" varchar(255) NOT NULL,
	pass varchar(64) NOT NULL,
	is_admin smallint DEFAULT 0 NOT NULL,
	modified timestamp with time zone DEFAULT now() NOT NULL
);

CREATE INDEX cms_auth_users_is_admin_idx ON cms_auth_users(is_admin);
CREATE INDEX cms_auth_users_login_idx ON cms_auth_users("login");
CREATE INDEX cms_auth_users_pass_idx ON cms_auth_users(pass);

INSERT INTO cms_auth_users ("login", pass, is_admin) VALUES ('dev', 'ef260e9aa3c673af240d17a2660480361a8e081d1ffeca2a5ed0e3219fc18567', 1);

CREATE TABLE cms_auth_user_roles (
	user_id bigint NOT NULL REFERENCES cms_auth_users(id) ON UPDATE CASCADE ON DELETE CASCADE,
	role_id bigint NOT NULL REFERENCES cms_auth_roles(id) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE INDEX cms_auth_user_roles_user_id_idx ON cms_auth_user_roles(user_id);
CREATE INDEX cms_auth_user_roles_role_id_idx ON cms_auth_user_roles(role_id);

INSERT INTO cms_auth_user_roles (user_id, role_id) VALUES (1, 1);

CREATE TABLE cms_auth_blocks (
	ip varchar(15) PRIMARY KEY,
	failed_count integer DEFAULT 0 NOT NULL,
	failed_time timestamp with time zone NOT NULL
);

CREATE TABLE cms_node_types (
	"type" varchar(255) PRIMARY KEY,
	name varchar(64) NOT NULL,
	name_list varchar(64) NOT NULL,
	tree_name varchar(255),
	icon varchar(255) DEFAULT 'folder.gif' NOT NULL,
	controller varchar(64) DEFAULT 'index' NOT NULL,
	"action" varchar(64) DEFAULT 'index' NOT NULL,
	one2many_field varchar(64),
	is_hidden_tree smallint DEFAULT 0 NOT NULL,
	is_lang smallint DEFAULT 0 NOT NULL,
	is_shared_fields_disabled smallint DEFAULT 0 NOT NULL,
	is_additional_tab_disabled smallint DEFAULT 0 NOT NULL,
	disable_fields varchar(512),
	disable_childs varchar(512)
);

CREATE INDEX cms_node_types_is_additional_tab_disabled_idx ON cms_node_types (is_additional_tab_disabled);
CREATE INDEX cms_node_types_is_shared_fields_disabled_idx ON cms_node_types (is_shared_fields_disabled);

CREATE TABLE cms_auth_types (
	id bigserial PRIMARY KEY,
	role_id bigint NOT NULL REFERENCES cms_auth_roles(id) ON UPDATE CASCADE ON DELETE CASCADE,
	auth bigint DEFAULT 0 NOT NULL,
	"type" varchar(255) NOT NULL REFERENCES cms_node_types("type") ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE INDEX cms_auth_types_role_id_idx ON cms_auth_types(role_id);

CREATE TABLE cms_languages (
	lang character(2) PRIMARY KEY,
	name varchar(64) NOT NULL,
	is_default smallint DEFAULT 0 NOT NULL
);

CREATE TABLE cms_metadata (
	id bigserial PRIMARY KEY,
	"type" varchar(255) NOT NULL REFERENCES cms_node_types("type") ON UPDATE CASCADE ON DELETE CASCADE,
	name varchar(255) NOT NULL,
	field_name varchar(255) NOT NULL,
	field_type varchar(64) NOT NULL,
	on_update varchar(32),
	on_delete varchar(32),
	is_req smallint DEFAULT 0 NOT NULL,
	is_uniq smallint DEFAULT 0 NOT NULL,
	is_idx smallint DEFAULT 0 NOT NULL,
	is_ncs smallint DEFAULT 0 NOT NULL,
	select_root_name varchar(255),
	select_type varchar(255) REFERENCES cms_node_types("type") ON UPDATE CASCADE ON DELETE SET NULL,
	select_method varchar(32),
	root_folder varchar(255),
	default_value varchar(512)
);

CREATE INDEX cms_metadata_field_type_idx ON cms_metadata(field_type);
CREATE INDEX cms_metadata_type_idx ON cms_metadata("type");

CREATE TABLE cms_node_type_childs (
	"type" varchar(255) NOT NULL REFERENCES cms_node_types("type") ON UPDATE CASCADE ON DELETE CASCADE,
	child_type varchar(255) NOT NULL REFERENCES cms_node_types("type") ON UPDATE CASCADE ON DELETE CASCADE,
	sibling_index bigint DEFAULT 0 NOT NULL
);

CREATE INDEX cms_node_type_childs_type_idx ON cms_node_type_childs("type");
CREATE INDEX cms_node_type_childs_child_type_idx ON cms_node_type_childs(child_type);

CREATE TABLE cms_nodes (
	id bigserial PRIMARY KEY,
	system_name varchar(32) UNIQUE,
	url_id varchar(128),
	parent_id bigint,
	"level" integer,
	"type" varchar(255) NOT NULL REFERENCES cms_node_types("type") ON UPDATE CASCADE ON DELETE CASCADE,
	controller varchar(64),
	"action" varchar(64),
	mt_path varchar(255) NOT NULL,
	is_menuitem smallint DEFAULT 0 NOT NULL,
	sibling_index bigint NOT NULL
);

CREATE INDEX cms_nodes_action_idx ON cms_nodes("action");
CREATE INDEX cms_nodes_controller_idx ON cms_nodes(controller);
CREATE INDEX cms_nodes_is_menuitem_idx ON cms_nodes(is_menuitem);
CREATE INDEX cms_nodes_level_idx ON cms_nodes("level");
CREATE INDEX cms_nodes_parent_id_idx ON cms_nodes(parent_id);
CREATE INDEX cms_nodes_type_idx ON cms_nodes("type");
CREATE INDEX cms_nodes_urlname_idx ON cms_nodes(url_id);
CREATE INDEX cms_nodes_mt_path_idx ON cms_nodes(mt_path);
CREATE INDEX cms_nodes_sibling_index_idx ON cms_nodes(sibling_index);

ALTER TABLE ONLY cms_nodes ADD CONSTRAINT cms_nodes_parent_id_fkey FOREIGN KEY (parent_id) REFERENCES cms_nodes(id) ON UPDATE CASCADE ON DELETE CASCADE;

CREATE TRIGGER cms_nodes_aiud_tr AFTER INSERT OR DELETE OR UPDATE ON cms_nodes FOR EACH ROW EXECUTE PROCEDURE cms_nodes_aiud_tr_func();
CREATE TRIGGER cms_nodes_biud_tr BEFORE INSERT OR DELETE OR UPDATE ON cms_nodes FOR EACH ROW EXECUTE PROCEDURE cms_nodes_biud_tr_func();

CREATE TABLE cms_node_childs (
	id bigint NOT NULL REFERENCES cms_nodes(id) ON UPDATE CASCADE ON DELETE CASCADE,
	child_type varchar(255) NOT NULL REFERENCES cms_node_types("type") ON UPDATE CASCADE ON DELETE CASCADE,
	sibling_index bigint DEFAULT 0 NOT NULL
);

CREATE INDEX cms_node_childs_child_type_idx ON cms_node_childs(child_type);
CREATE INDEX cms_node_childs_id_idx ON cms_node_childs(id);

CREATE TABLE cms_node_extras (
	lang character(2) REFERENCES cms_languages(lang) ON UPDATE CASCADE ON DELETE CASCADE,
	id bigint NOT NULL REFERENCES cms_nodes(id) ON UPDATE CASCADE ON DELETE CASCADE,
	name varchar(255),
	is_hidden smallint DEFAULT 0 NOT NULL,
	title varchar(255),
	meta_keywords varchar(255),
	meta_description varchar(255)
);

CREATE UNIQUE INDEX cms_node_extras_id_and_lang_uniq_key ON cms_node_extras(id, lang);
CREATE UNIQUE INDEX cms_node_extras_id_uniq_key ON cms_node_extras(id) WHERE lang IS NULL;
CREATE INDEX cms_node_extras_lang_is_null_idx ON cms_node_extras(lang) WHERE lang IS NULL;
CREATE INDEX cms_node_extras_is_hidden_idx ON cms_node_extras(is_hidden);

CREATE TRIGGER cms_node_extras_aiud_tr AFTER INSERT OR DELETE OR UPDATE ON cms_node_extras FOR EACH ROW EXECUTE PROCEDURE cms_node_extras_aiud_tr_func();

CREATE VIEW v_cms_nodes
AS
SELECT
	n.id,
	n.parent_id,
	ne.name,
	n.system_name,
	n."level",
	n."type",
	n.url_id,
	COALESCE(n.controller, nt.controller) AS controller,
	COALESCE(n."action", nt."action") AS "action",
	COALESCE(ne.is_hidden, 0) AS is_hidden,
	n.is_menuitem,
	n.mt_path,
	n.sibling_index,
	ne.title,
	ne.meta_keywords,
	ne.meta_description
FROM
	cms_get_view_mode() vm(vm)
	JOIN cms_get_current_language() cl(cl) ON 0 = 0
	JOIN cms_nodes n ON 0 = 0
	JOIN cms_node_types nt ON nt."type" = n."type"
	LEFT JOIN cms_node_extras ne ON ne.id = n.id AND ((nt.is_lang = 1 AND ne.lang = cl.cl) OR (nt.is_lang = 0 AND ne.lang IS NULL))
WHERE
	(
		vm.vm = 0
		OR
		(
			vm.vm = 1
			AND (ne.is_hidden = 0 OR ne.id IS NULL)
		)
	);