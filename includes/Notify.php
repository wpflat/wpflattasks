<?php
namespace WPFlatTasks;

use WPFlatCore\Portal;
use WPFlatCore\Services\Notifications as N;

defined( 'ABSPATH' ) || exit;

/** The notifications WPFlat Tasks raises, and the daily reminder for tasks that are due. */
class Notify {

	public static function init() {
		add_action( 'wpflatcore_saved_tasks', array( __CLASS__, 'task_saved' ), 10, 2 );
		add_action( 'wpflatcore_saved_projects', array( __CLASS__, 'project_saved' ), 10, 2 );
		add_action( 'wpflatcore_saved_project-phases', array( __CLASS__, 'phase_saved' ), 10, 2 );
		add_action( 'wpflatcore_daily', array( __CLASS__, 'daily' ), 20 );
	}

	public static function types() {
		N::register( 'team_task_assigned', array( 'label' => 'Task', 'audience' => 'team', 'group' => 'Tasks', 'description' => 'A task is assigned to you', 'email' => 'instant' ) );
		N::register( 'team_task_done', array( 'label' => 'Task', 'audience' => 'team', 'group' => 'Tasks', 'description' => 'A task in your project is completed', 'email' => 'off' ) );
		N::register( 'team_task_due', array( 'label' => 'Task', 'audience' => 'team', 'group' => 'Tasks', 'description' => 'Your tasks that are due soon or late', 'email' => 'digest' ) );
		N::register( 'team_project_assigned', array( 'label' => 'Project', 'audience' => 'team', 'group' => 'Tasks', 'description' => 'You are made owner of a project', 'email' => 'instant' ) );
		N::register( 'client_project', array( 'label' => 'Project', 'audience' => 'client', 'group' => 'Projects', 'description' => 'A project is started, finished or put on hold', 'email' => 'off' ) );
		N::register( 'client_phase', array( 'label' => 'Project', 'audience' => 'client', 'group' => 'Projects', 'description' => 'A project phase is completed', 'email' => 'off' ) );
	}

	private static function plink( $path ) {
		return Portal::url( $path );
	}

	public static function task_saved( $row, $existing ) {
		global $wpdb;
		$assignee = (int) ( isset( $row['assignee_id'] ) ? $row['assignee_id'] : 0 );
		if ( $assignee && ( ! $existing || (int) $existing['assignee_id'] !== $assignee ) && 'done' !== $row['status'] ) {
			N::emit( 'team_task_assigned', array( N::team_user( $assignee ) ), 'Task assigned to you: ' . $row['title'], ! empty( $row['due_date'] ) ? 'Due ' . wp_date( get_option( 'date_format' ), strtotime( $row['due_date'] ) ) . '.' : '', '/tasks/mine', (int) $row['client_id'], 'task', $row['id'], 'assign:' . $row['id'] . ':' . $assignee );
		}
		if ( $existing && 'done' === $row['status'] && 'done' !== $existing['status'] && ! empty( $row['project_id'] ) ) {
			$owner = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT owner_id FROM ' . Install::table( 'projects' ) . ' WHERE id=%d', $row['project_id'] ) ); // phpcs:ignore WordPress.DB
			N::emit( 'team_task_done', array( $owner ? N::team_user( $owner ) : 0 ), 'Task completed: ' . $row['title'], '', '/projects/' . (int) $row['project_id'], (int) $row['client_id'], 'task', $row['id'], 'done:' . $row['id'] . ':' . ( isset( $row['completed_at'] ) ? $row['completed_at'] : '' ) );
		}
	}

	public static function project_saved( $row, $existing ) {
		if ( ! empty( $row['owner_id'] ) && ( ! $existing || (int) $existing['owner_id'] !== (int) $row['owner_id'] ) ) {
			N::emit( 'team_project_assigned', array( N::team_user( $row['owner_id'] ) ), 'You now own the project “' . $row['name'] . '”', '', '/projects/' . $row['id'], (int) $row['client_id'], 'project', $row['id'], 'powner:' . $row['id'] . ':' . $row['owner_id'] );
		}
		if ( (int) $row['client_id'] <= 0 ) {
			return;
		}
		if ( ! $existing ) {
			N::emit( 'client_project', N::client_users( $row['client_id'] ), 'New project: ' . $row['name'], 'We have set up this project for you. You can follow its progress in the portal.', self::plink( 'projects/' . $row['id'] ), $row['client_id'], 'project', $row['id'], 'pnew:' . $row['id'] );
		} elseif ( $existing['status'] !== $row['status'] && in_array( $row['status'], array( 'done', 'on_hold', 'active' ), true ) ) {
			$msg = array( 'done' => 'is complete', 'on_hold' => 'is on hold', 'active' => 'is under way' );
			N::emit( 'client_project', N::client_users( $row['client_id'] ), 'Project ' . $msg[ $row['status'] ] . ': ' . $row['name'], '', self::plink( 'projects/' . $row['id'] ), $row['client_id'], 'project', $row['id'], 'pstat:' . $row['id'] . ':' . $row['status'] . ':' . gmdate( 'Ymd' ) );
		}
	}

	public static function phase_saved( $row, $existing ) {
		global $wpdb;
		if ( ! $existing || 'done' !== $row['status'] || 'done' === $existing['status'] ) {
			return;
		}
		$p = $wpdb->get_row( $wpdb->prepare( 'SELECT id, name, client_id FROM ' . Install::table( 'projects' ) . ' WHERE id=%d', $row['project_id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( $p && (int) $p['client_id'] > 0 ) {
			N::emit( 'client_phase', N::client_users( $p['client_id'] ), 'Phase complete: ' . $row['name'], 'In project “' . $p['name'] . '”.', self::plink( 'projects/' . $p['id'] ), $p['client_id'], 'project', $p['id'], 'phase:' . $row['id'] . ':' . ( isset( $row['completed_at'] ) ? $row['completed_at'] : '' ) );
		}
	}

	/** Tasks due within two days or late, for the person they are assigned to (once per task and date). */
	public static function daily() {
		global $wpdb;
		if ( ! \WPFlatCore\Registry::enabled( 'tasks' ) ) {
			return;
		}
		$today = current_time( 'Y-m-d' );
		$soon  = gmdate( 'Y-m-d', strtotime( $today . ' +2 days' ) );
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT t.id, t.title, t.due_date, t.client_id, m.wp_user_id FROM ' . Install::table( 'tasks' ) . ' t JOIN ' . $wpdb->prefix . "wpflat_team_members m ON m.id=t.assignee_id WHERE t.status <> 'done' AND t.due_date IS NOT NULL AND t.due_date <= %s AND m.wp_user_id > 0", $soon ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( $rows as $t ) {
			$late = $t['due_date'] < $today;
			N::emit( 'team_task_due', array( (int) $t['wp_user_id'] ), ( $late ? 'Overdue: ' : 'Due soon: ' ) . $t['title'], 'Due ' . wp_date( get_option( 'date_format' ), strtotime( $t['due_date'] ) ) . '.', '/tasks/mine', (int) $t['client_id'], 'task', $t['id'], ( $late ? 'tlate:' : 'tsoon:' ) . $t['id'] . ':' . $t['due_date'] );
		}
	}
}
