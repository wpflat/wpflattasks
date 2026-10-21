<?php
namespace WPFlatTasks;

use WPFlatCore\Rest\Routes as Core_Routes;
use WPFlatCore\Rest\Resource_Controller;

defined( 'ABSPATH' ) || exit;

class Routes {

	public static function register() {
		Core_Routes::add( 'tasks', '/tasks/overview', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'overview' ), 'permission_callback' => array( Resource_Controller::class, 'can' ) ) );
		Core_Routes::add(
			'tasks',
			'/tasks/analytics',
			array(
				'methods'             => 'GET',
				'callback'            => function ( \WP_REST_Request $req ) {
					return rest_ensure_response(
						Services\TaskAnalytics::query(
							array(
								'from'          => (string) $req->get_param( 'from' ),
								'to'            => (string) $req->get_param( 'to' ),
								'department_id' => (string) $req->get_param( 'department_id' ),
								'assignee_id'   => absint( $req->get_param( 'assignee_id' ) ),
								'client_id'     => absint( $req->get_param( 'client_id' ) ),
							)
						)
					);
				},
				'permission_callback' => array( Resource_Controller::class, 'can' ),
			)
		);
	}

	/** Tasks → Overview: what is due, who is busy, which projects are moving. */
	public static function overview() {
		global $wpdb;
		$tk    = Install::table( 'tasks' );
		$pj    = Install::table( 'projects' );
		$tm    = $wpdb->prefix . 'wpflat_team_members';
		$cl    = $wpdb->prefix . 'wpflat_clients';
		$today = current_time( 'Y-m-d' );
		$week  = gmdate( 'Y-m-d', strtotime( $today . ' +7 days' ) );
		$me    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $tm WHERE wp_user_id=%d LIMIT 1", get_current_user_id() ) ); // phpcs:ignore WordPress.DB
		$cnt   = function ( $sql, $args = array() ) use ( $wpdb ) {
			return (int) $wpdb->get_var( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB
		};
		$stats = array(
			'open'          => $cnt( "SELECT COUNT(*) FROM $tk WHERE status <> 'done'" ),
			'overdue'       => $cnt( "SELECT COUNT(*) FROM $tk WHERE status <> 'done' AND due_date IS NOT NULL AND due_date < %s", array( $today ) ),
			'due_week'      => $cnt( "SELECT COUNT(*) FROM $tk WHERE status <> 'done' AND due_date BETWEEN %s AND %s", array( $today, $week ) ),
			'done_week'     => $cnt( "SELECT COUNT(*) FROM $tk WHERE status = 'done' AND updated_at >= %s", array( gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days' ) ) ) ),
			'mine'          => $me ? $cnt( "SELECT COUNT(*) FROM $tk WHERE status <> 'done' AND assignee_id=%d", array( $me ) ) : 0,
			'projects'      => $cnt( "SELECT COUNT(*) FROM $pj WHERE status IN ('active','planned')" ),
			'projects_late' => $cnt( "SELECT COUNT(*) FROM $pj WHERE status IN ('active','planned') AND due_date IS NOT NULL AND due_date < %s", array( $today ) ),
		);
		$sel     = 't.id, t.title, t.due_date, t.priority, t.status, t.project_id, c.name AS client_name, a.name AS assignee_name';
		$jn      = "FROM $tk t LEFT JOIN $cl c ON c.id=t.client_id LEFT JOIN $tm a ON a.id=t.assignee_id";
		$overdue = $wpdb->get_results( $wpdb->prepare( "SELECT $sel $jn WHERE t.status <> 'done' AND t.due_date IS NOT NULL AND t.due_date < %s ORDER BY t.due_date ASC LIMIT 8", $today ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$soon    = $wpdb->get_results( $wpdb->prepare( "SELECT $sel $jn WHERE t.status <> 'done' AND t.due_date BETWEEN %s AND %s ORDER BY t.due_date ASC LIMIT 8", $today, $week ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$mine    = $me ? $wpdb->get_results( $wpdb->prepare( "SELECT $sel $jn WHERE t.status <> 'done' AND t.assignee_id=%d ORDER BY t.due_date IS NULL, t.due_date ASC LIMIT 8", $me ), ARRAY_A ) : array(); // phpcs:ignore WordPress.DB
		$projects = $wpdb->get_results( "SELECT p.id, p.name, p.due_date, p.status, c.name AS client_name, (SELECT COUNT(*) FROM $tk x WHERE x.project_id=p.id) AS total, (SELECT COUNT(*) FROM $tk y WHERE y.project_id=p.id AND y.status='done') AS done FROM $pj p LEFT JOIN $cl c ON c.id=p.client_id WHERE p.status IN ('active','planned') ORDER BY p.due_date IS NULL, p.due_date ASC LIMIT 6", ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( $projects as &$p ) {
			$p['total']      = (int) $p['total'];
			$p['done']       = (int) $p['done'];
			$p['progress']   = $p['total'] ? (int) round( 100 * $p['done'] / $p['total'] ) : 0;
			$p['is_overdue'] = ! empty( $p['due_date'] ) && $p['due_date'] < $today;
		}
		$load = $wpdb->get_results( $wpdb->prepare( "SELECT a.id, a.name, COUNT(t.id) AS open_tasks, SUM(t.due_date IS NOT NULL AND t.due_date < %s) AS late FROM $tm a JOIN $tk t ON t.assignee_id=a.id AND t.status <> 'done' GROUP BY a.id, a.name ORDER BY open_tasks DESC LIMIT 8", $today ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return rest_ensure_response( array( 'stats' => $stats, 'overdue' => $overdue, 'soon' => $soon, 'mine' => $mine, 'projects' => $projects, 'load' => $load, 'has_me' => (bool) $me ) );
	}
}
