<?php
namespace BizyTasks;

use BizyCore\Links;
use BizyCore\Registry;
use BizyCore\Services\Notifications;

defined( 'ABSPATH' ) || exit;

/** What BizyTasks adds to BizyCore's shared screens: team figures, department pages, Home, search, links, messages. */
class Integrations {

	public static function init() {
		add_filter( 'bizycore_decorate_team', array( __CLASS__, 'team' ) );
		add_filter( 'bizycore_department_card', array( __CLASS__, 'department_card' ), 10, 4 );
		add_filter( 'bizycore_department_detail', array( __CLASS__, 'department_detail' ), 10, 4 );
		add_filter( 'bizycore_home', array( __CLASS__, 'home' ) );
		add_filter( 'bizycore_search', array( __CLASS__, 'search' ), 10, 2 );
		add_filter( 'bizycore_message_contexts', array( __CLASS__, 'message_contexts' ), 10, 2 );
		add_filter( 'bizycore_message_context_name', array( __CLASS__, 'message_context_name' ), 10, 3 );
		add_filter( 'bizycore_message_context_staff', array( __CLASS__, 'message_context_staff' ), 10, 3 );
		add_action( 'bizycore_deleted_projects', array( __CLASS__, 'forget_project' ) );
		add_action( 'bizycore_deleted_tasks', array( __CLASS__, 'forget_task' ) );
	}

	private static function t( $n ) {
		return Install::table( $n );
	}

	private static function on() {
		return Registry::enabled( 'tasks' );
	}

	/* ---- link types ---- */

	public static function link_types() {
		Links::register_type( 'project', array( 'module' => 'tasks', 'label' => 'Project', 'plural' => 'Projects', 'resolve' => array( __CLASS__, 'resolve_project' ), 'search' => array( __CLASS__, 'search_projects' ) ) );
		Links::register_type( 'task', array( 'module' => 'tasks', 'label' => 'Task', 'plural' => 'Tasks', 'resolve' => array( __CLASS__, 'resolve_task' ), 'search' => array( __CLASS__, 'search_tasks' ) ) );
	}

	public static function resolve_project( $id ) {
		global $wpdb;
		$p = $wpdb->get_row( $wpdb->prepare( 'SELECT id, name, status FROM ' . self::t( 'projects' ) . ' WHERE id=%d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $p ? array( 'title' => $p['name'], 'url' => '#/projects/' . (int) $id, 'sub' => ucfirst( str_replace( '_', ' ', $p['status'] ) ) ) : null;
	}

	public static function search_projects( $q, $n ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, name AS title, status AS sub FROM ' . self::t( 'projects' ) . ' WHERE name LIKE %s ORDER BY id DESC LIMIT %d', '%' . $wpdb->esc_like( $q ) . '%', $n ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return array_map( function ( $r ) {
			return array( 'id' => (int) $r['id'], 'title' => $r['title'], 'sub' => $r['sub'] );
		}, (array) $rows );
	}

	public static function resolve_task( $id ) {
		global $wpdb;
		$t = $wpdb->get_row( $wpdb->prepare( 'SELECT id, title, status, project_id FROM ' . self::t( 'tasks' ) . ' WHERE id=%d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $t ? array( 'title' => $t['title'], 'url' => $t['project_id'] ? '#/projects/' . (int) $t['project_id'] : '#/tasks/all', 'sub' => ucfirst( $t['status'] ) ) : null;
	}

	public static function search_tasks( $q, $n ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, title, status AS sub FROM ' . self::t( 'tasks' ) . ' WHERE title LIKE %s ORDER BY id DESC LIMIT %d', '%' . $wpdb->esc_like( $q ) . '%', $n ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return array_map( function ( $r ) {
			return array( 'id' => (int) $r['id'], 'title' => $r['title'], 'sub' => $r['sub'] );
		}, (array) $rows );
	}

	public static function forget_project( $row ) {
		\BizyCore\Links::forget( 'project', $row['id'] );
	}

	public static function forget_task( $row ) {
		\BizyCore\Links::forget( 'task', $row['id'] );
	}

	/* ---- team ---- */

	/** Open tasks, overdue tasks and active projects on every team row. */
	public static function team( $rows ) {
		global $wpdb;
		if ( ! self::on() || ! $rows ) {
			return $rows;
		}
		$in    = implode( ',', array_map( 'intval', wp_list_pluck( $rows, 'id' ) ) );
		$today = current_time( 'Y-m-d' );
		$open  = array();
		foreach ( (array) $wpdb->get_results( 'SELECT assignee_id, COUNT(*) n, SUM(due_date IS NOT NULL AND due_date < \'' . $today . '\') late FROM ' . self::t( 'tasks' ) . " WHERE status <> 'done' AND assignee_id IN ($in) GROUP BY assignee_id", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
			$open[ (int) $r['assignee_id'] ] = array( (int) $r['n'], (int) $r['late'] );
		}
		$proj = array();
		foreach ( (array) $wpdb->get_results( 'SELECT t.assignee_id, COUNT(DISTINCT t.project_id) n FROM ' . self::t( 'tasks' ) . ' t JOIN ' . self::t( 'projects' ) . " p ON p.id=t.project_id WHERE t.status <> 'done' AND p.status IN ('active','planned') AND t.assignee_id IN ($in) GROUP BY t.assignee_id", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
			$proj[ (int) $r['assignee_id'] ] = (int) $r['n'];
		}
		foreach ( $rows as &$r ) {
			$id                   = (int) $r['id'];
			$r['open_tasks']      = isset( $open[ $id ] ) ? $open[ $id ][0] : 0;
			$r['overdue_tasks']   = isset( $open[ $id ] ) ? $open[ $id ][1] : 0;
			$r['active_projects'] = isset( $proj[ $id ] ) ? $proj[ $id ] : 0;
		}
		return $rows;
	}

	/* ---- departments ---- */

	private static function dept_sql() {
		// a task counts for its own department, else its phase's, else its project's
		return 'COALESCE(NULLIF(t.department_id,0), NULLIF(ph.department_id,0), NULLIF(p.department_id,0), 0)';
	}

	public static function department_card( $card, $id, $from, $to ) {
		global $wpdb;
		if ( ! self::on() ) {
			return $card;
		}
		$d    = self::dept_sql();
		$open = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t( 'tasks' ) . ' t LEFT JOIN ' . self::t( 'phases' ) . ' ph ON ph.id=t.phase_id LEFT JOIN ' . self::t( 'projects' ) . " p ON p.id=t.project_id WHERE t.status <> 'done' AND $d = %d", $id ) ); // phpcs:ignore WordPress.DB
		$pj   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t( 'projects' ) . " WHERE status IN ('active','planned') AND department_id=%d", $id ) ); // phpcs:ignore WordPress.DB
		$card['modules']['tasks'] = array( 'open_tasks' => $open, 'active_projects' => $pj );
		return $card;
	}

	public static function department_detail( $data, $id, $from, $to ) {
		global $wpdb;
		if ( ! self::on() ) {
			return $data;
		}
		$d     = self::dept_sql();
		$today = current_time( 'Y-m-d' );
		$tk    = self::t( 'tasks' );
		$join  = ' LEFT JOIN ' . self::t( 'phases' ) . ' ph ON ph.id=t.phase_id LEFT JOIN ' . self::t( 'projects' ) . ' p ON p.id=t.project_id';
		$open  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tk t $join WHERE t.status <> 'done' AND $d = %d", $id ) ); // phpcs:ignore WordPress.DB
		$late  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tk t $join WHERE t.status <> 'done' AND t.due_date < %s AND $d = %d", $today, $id ) ); // phpcs:ignore WordPress.DB
		$done  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tk t $join WHERE t.status = 'done' AND $d = %d AND t.completed_at >= %s AND t.completed_at <= %s", $id, ( $from ? $from : '1970-01-01' ) . ' 00:00:00', ( $to ? $to : '2999-12-31' ) . ' 23:59:59' ) ); // phpcs:ignore WordPress.DB
		$projs = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT p.id, p.name, p.due_date, c.name AS client_name, (SELECT COUNT(*) FROM ' . $tk . ' x WHERE x.project_id=p.id) AS total, (SELECT COUNT(*) FROM ' . $tk . " y WHERE y.project_id=p.id AND y.status='done') AS done FROM " . self::t( 'projects' ) . ' p LEFT JOIN ' . $wpdb->prefix . "bizy_clients c ON c.id=p.client_id WHERE p.status IN ('active','planned') AND p.department_id=%d ORDER BY p.due_date IS NULL, p.due_date LIMIT 8", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( $projs as &$p ) {
			$p['total']    = (int) $p['total'];
			$p['done']     = (int) $p['done'];
			$p['progress'] = $p['total'] ? (int) round( 100 * $p['done'] / $p['total'] ) : 0;
		}
		$data['modules']['tasks'] = array( 'open' => $open, 'overdue' => $late, 'done' => $done, 'projects' => $projs );
		return $data;
	}

	/* ---- Home ---- */

	public static function home( $home ) {
		global $wpdb;
		if ( ! self::on() ) {
			return $home;
		}
		$today = current_time( 'Y-m-d' );
		$me    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $wpdb->prefix . 'bizy_team_members WHERE wp_user_id=%d LIMIT 1', get_current_user_id() ) ); // phpcs:ignore WordPress.DB
		if ( $me ) {
			$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT t.id, t.title, t.due_date, t.priority, p.name AS project_name FROM ' . self::t( 'tasks' ) . ' t LEFT JOIN ' . self::t( 'projects' ) . " p ON p.id=t.project_id WHERE t.status <> 'done' AND t.assignee_id=%d AND t.due_date IS NOT NULL AND t.due_date <= %s ORDER BY t.due_date LIMIT 20", $me, $today ), ARRAY_A ); // phpcs:ignore WordPress.DB
			foreach ( $rows as $r ) {
				$home['todo'][] = array( 'kind' => 'task', 'title' => $r['title'], 'sub' => $r['project_name'] ? $r['project_name'] : 'Task', 'link' => '/tasks/mine', 'days' => (int) floor( ( strtotime( $r['due_date'] ) - strtotime( $today ) ) / DAY_IN_SECONDS ) );
			}
			$soon = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT t.title, t.due_date, p.name AS project_name FROM ' . self::t( 'tasks' ) . ' t LEFT JOIN ' . self::t( 'projects' ) . " p ON p.id=t.project_id WHERE t.status <> 'done' AND t.assignee_id=%d AND t.due_date > %s ORDER BY t.due_date LIMIT 10", $me, $today ), ARRAY_A ); // phpcs:ignore WordPress.DB
			foreach ( $soon as $r ) {
				$home['coming_up'][] = array( 'kind' => 'task', 'title' => $r['title'], 'sub' => $r['project_name'] ? $r['project_name'] : 'Task', 'link' => '/tasks/mine', 'days' => (int) floor( ( strtotime( $r['due_date'] ) - strtotime( $today ) ) / DAY_IN_SECONDS ) );
			}
		}
		$home['stats']['open_tasks'] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::t( 'tasks' ) . " WHERE status <> 'done'" ); // phpcs:ignore WordPress.DB
		$home['stats']['tasks_overdue'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t( 'tasks' ) . " WHERE status <> 'done' AND due_date < %s", $today ) ); // phpcs:ignore WordPress.DB
		return $home;
	}

	/* ---- search ---- */

	public static function search( $out, $q ) {
		global $wpdb;
		if ( ! self::on() || ! current_user_can( 'bizytasks_work' ) && ! current_user_can( 'manage_options' ) ) {
			return $out;
		}
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT p.id, p.name, c.name AS client_name FROM ' . self::t( 'projects' ) . ' p LEFT JOIN ' . $wpdb->prefix . 'bizy_clients c ON c.id=p.client_id WHERE p.name LIKE %s ORDER BY p.id DESC LIMIT 6', $like ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
			$out[] = array( 'type' => 'project', 'kind' => \BizyCore\Terminology::label( 'project' ), 'id' => (int) $r['id'], 'label' => $r['name'], 'sub' => (string) $r['client_name'], 'route' => '/projects/' . (int) $r['id'] );
		}
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT t.id, t.title, t.project_id, p.name AS project_name FROM ' . self::t( 'tasks' ) . ' t LEFT JOIN ' . self::t( 'projects' ) . ' p ON p.id=t.project_id WHERE t.title LIKE %s ORDER BY t.id DESC LIMIT 6', $like ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
			$out[] = array( 'type' => 'task', 'kind' => \BizyCore\Terminology::label( 'task' ), 'id' => (int) $r['id'], 'label' => $r['title'], 'sub' => (string) $r['project_name'], 'route' => $r['project_id'] ? '/projects/' . (int) $r['project_id'] : '/tasks/all' );
		}
		return $out;
	}

	/* ---- messages: a conversation can be about a project ---- */

	public static function message_contexts( $list, $client_id ) {
		global $wpdb;
		if ( ! self::on() ) {
			return $list;
		}
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, name FROM ' . self::t( 'projects' ) . " WHERE client_id=%d ORDER BY FIELD(status,'active','planned','on_hold','done'), id DESC", (int) $client_id ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
			$list[] = array( 'type' => 'project', 'id' => (int) $r['id'], 'name' => $r['name'], 'group' => \BizyCore\Terminology::label( 'project', true ) );
		}
		return $list;
	}

	public static function message_context_name( $name, $type, $id ) {
		global $wpdb;
		if ( 'project' !== $type || ! self::on() ) {
			return $name;
		}
		return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ' . self::t( 'projects' ) . ' WHERE id=%d', $id ) ); // phpcs:ignore WordPress.DB
	}

	/** The project owner hears about a client's message on their project. */
	public static function message_context_staff( $ids, $type, $id ) {
		global $wpdb;
		if ( 'project' !== $type || ! self::on() ) {
			return $ids;
		}
		$oid = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT m.wp_user_id FROM ' . self::t( 'projects' ) . ' p JOIN ' . $wpdb->prefix . 'bizy_team_members m ON m.id=p.owner_id WHERE p.id=%d', $id ) ); // phpcs:ignore WordPress.DB
		if ( $oid ) {
			$ids[] = $oid;
		}
		return $ids;
	}
}
