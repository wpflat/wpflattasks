<?php
namespace BizyTasks;

use BizyCore\Migration\Legacy as Core_Legacy;
use BizyCore\Migration\Reader;

defined( 'ABSPATH' ) || exit;

/** Takes the projects, phases and tasks out of an old BizyDash backup. Money fields (prices, pay, invoice links) stay for BizyFinance. */
class Legacy {

	public static function init() {
		add_filter( 'bizycore_legacy_import_tasks', array( __CLASS__, 'import' ), 10, 2 );
	}

	/** @return array|\WP_Error */
	public static function import( $report, Reader $reader ) {
		global $wpdb;
		$tables = array( 'projects' => 'projects', 'project_phases' => 'phases', 'tasks' => 'tasks' );
		$had    = 0;
		foreach ( $tables as $new ) {
			$had += (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Install::table( $new ) ); // phpcs:ignore WordPress.DB
		}
		if ( $had && empty( Core_Legacy::module_state( 'tasks' )['done'] ) ) {
			return new \WP_Error( 'bizytasks_not_empty', 'BizyTasks already has records. Importing would mix them with the old ones. Remove them first, or skip this import.', array( 'status' => 409 ) );
		}
		foreach ( $tables as $new ) {
			$wpdb->query( 'TRUNCATE TABLE ' . Install::table( $new ) ); // phpcs:ignore WordPress.DB
		}
		$cols = array(
			'projects'       => array( 'id', 'client_id', 'name', 'description', 'status', 'start_date', 'due_date', 'owner_id', 'department_id', 'created_by', 'created_at', 'updated_at' ),
			'project_phases' => array( 'id', 'project_id', 'name', 'sort_order', 'status', 'start_date', 'due_date', 'department_id', 'completed_at', 'created_by', 'created_at', 'updated_at' ),
			'tasks'          => array( 'id', 'project_id', 'phase_id', 'client_id', 'title', 'notes', 'status', 'priority', 'due_date', 'assignee_id', 'department_id', 'completed_at', 'sort_order', 'created_by', 'created_at', 'updated_at' ),
		);
		$out = array();
		foreach ( $tables as $old => $new ) {
			$n = 0;
			$f = 0;
			foreach ( $reader->rows( $old ) as $row ) {
				$data = array();
				foreach ( $cols[ $old ] as $c ) {
					if ( array_key_exists( $c, $row ) ) {
						$data[ $c ] = 'created_by' === $c ? Core_Legacy::map_user( $row[ $c ] ) : $row[ $c ];
					}
				}
				false === $wpdb->insert( Install::table( $new ), $data ) ? $f++ : $n++;
			}
			$out[ $old ] = $n;
			if ( $f ) {
				$out[ $old . '_failed' ] = $f;
			}
		}
		return $out;
	}
}
