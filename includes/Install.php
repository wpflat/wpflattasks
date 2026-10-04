<?php
namespace BizyTasks;

defined( 'ABSPATH' ) || exit;

class Install {

	const DB_VERSION = '1';
	const OPTION     = 'bizytasks_db_version';

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'bizytasks_' . $name;
	}

	/** Cheap check on every request so an updated plugin upgrades itself. */
	public static function maybe_upgrade() {
		if ( get_option( self::OPTION ) !== self::DB_VERSION ) {
			self::install_tables();
		}
	}

	public static function install_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		dbDelta( 'CREATE TABLE ' . self::table( 'projects' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  client_id bigint(20) unsigned NOT NULL DEFAULT 0,
  name varchar(190) NOT NULL DEFAULT '',
  description text,
  status varchar(20) NOT NULL DEFAULT 'active',
  start_date date DEFAULT NULL,
  due_date date DEFAULT NULL,
  owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
  department_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime DEFAULT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY client_id (client_id),
  KEY status (status)
) $c;" );
		dbDelta( 'CREATE TABLE ' . self::table( 'phases' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  project_id bigint(20) unsigned NOT NULL DEFAULT 0,
  name varchar(190) NOT NULL DEFAULT '',
  sort_order int(11) NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'todo',
  start_date date DEFAULT NULL,
  due_date date DEFAULT NULL,
  department_id bigint(20) unsigned NOT NULL DEFAULT 0,
  completed_at datetime DEFAULT NULL,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime DEFAULT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY project_id (project_id)
) $c;" );
		dbDelta( 'CREATE TABLE ' . self::table( 'tasks' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  project_id bigint(20) unsigned NOT NULL DEFAULT 0,
  phase_id bigint(20) unsigned NOT NULL DEFAULT 0,
  client_id bigint(20) unsigned NOT NULL DEFAULT 0,
  title varchar(255) NOT NULL DEFAULT '',
  notes text,
  status varchar(20) NOT NULL DEFAULT 'todo',
  priority varchar(10) NOT NULL DEFAULT 'normal',
  due_date date DEFAULT NULL,
  assignee_id bigint(20) unsigned NOT NULL DEFAULT 0,
  department_id bigint(20) unsigned NOT NULL DEFAULT 0,
  completed_at datetime DEFAULT NULL,
  sort_order int(11) NOT NULL DEFAULT 0,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime DEFAULT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY project_id (project_id),
  KEY client_id (client_id),
  KEY assignee_id (assignee_id),
  KEY status_due (status,due_date)
) $c;" );
		update_option( self::OPTION, self::DB_VERSION, false );
	}
}
