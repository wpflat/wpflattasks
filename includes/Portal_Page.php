<?php
namespace BizyTasks;

use BizyCore\Portal;

defined( 'ABSPATH' ) || exit;

/** The client portal's "Projects" page: what we are doing for the client, never costs or internal notes. */
class Portal_Page {

	public static function register() {
		Portal::register_page( 'projects', array(
			'module'   => 'tasks',
			'label'    => 'Projects',
			'order'    => 10,
			'render'   => array( __CLASS__, 'render' ),
			'overview' => array( __CLASS__, 'overview' ),
			'options'  => array(
				array( 'key' => 'portal_show_tasks', 'label' => 'Tasks inside projects' ),
				array( 'key' => 'portal_show_assignee', 'label' => 'Who is doing each task' ),
			),
		) );
	}

	private static function opt( $k ) {
		return false !== \BizyCore\Settings::get( $k );
	}

	public static function projects( $client_id ) {
		global $wpdb;
		$tk   = Install::table( 'tasks' );
		$rows = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT p.id, p.name, p.description, p.status, p.start_date, p.due_date,
				(SELECT COUNT(*) FROM $tk t WHERE t.project_id=p.id) AS tasks_total,
				(SELECT COUNT(*) FROM $tk t WHERE t.project_id=p.id AND t.status='done') AS tasks_done
			FROM " . Install::table( 'projects' ) . " p WHERE p.client_id=%d ORDER BY FIELD(p.status,'active','planned','on_hold','done'), p.due_date IS NULL, p.due_date ASC, p.id DESC", // phpcs:ignore WordPress.DB
			$client_id
		), ARRAY_A );
		foreach ( $rows as &$r ) {
			$r['id']          = (int) $r['id'];
			$r['tasks_total'] = (int) $r['tasks_total'];
			$r['tasks_done']  = (int) $r['tasks_done'];
			$r['percent']     = $r['tasks_total'] ? (int) round( 100 * $r['tasks_done'] / $r['tasks_total'] ) : ( 'done' === $r['status'] ? 100 : 0 );
		}
		return $rows;
	}

	/** One project of this client with its phases and tasks, or null. */
	public static function project( $client_id, $id, $with_assignee ) {
		global $wpdb;
		$p = $wpdb->get_row( $wpdb->prepare( 'SELECT id, name, description, status, start_date, due_date FROM ' . Install::table( 'projects' ) . ' WHERE id=%d AND client_id=%d', $id, $client_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( ! $p ) {
			return null;
		}
		$phases = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, name, status, start_date, due_date, completed_at FROM ' . Install::table( 'phases' ) . ' WHERE project_id=%d ORDER BY sort_order ASC, id ASC', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$tasks  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT t.id, t.title, t.status, t.priority, t.due_date, t.phase_id, t.completed_at' . ( $with_assignee ? ', a.name AS assignee' : '' ) . ' FROM ' . Install::table( 'tasks' ) . ' t' . ( $with_assignee ? ' LEFT JOIN ' . $wpdb->prefix . 'bizy_team_members a ON a.id=t.assignee_id' : '' ) . " WHERE t.project_id=%d ORDER BY FIELD(t.status,'doing','todo','done'), t.due_date IS NULL, t.due_date ASC, t.sort_order ASC, t.id ASC", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$by = array();
		foreach ( $tasks as $t ) {
			$by[ (int) $t['phase_id'] ][] = $t;
		}
		foreach ( $phases as &$ph ) {
			$ph['tasks'] = isset( $by[ (int) $ph['id'] ] ) ? $by[ (int) $ph['id'] ] : array();
			unset( $by[ (int) $ph['id'] ] );
		}
		unset( $ph );
		$p['phases'] = $phases;
		$p['other']  = $by ? array_merge( ...array_values( $by ) ) : array();
		$total       = count( $tasks );
		$done        = count( array_filter( $tasks, function ( $t ) {
			return 'done' === $t['status'];
		} ) );
		$p['tasks_total'] = $total;
		$p['tasks_done']  = $done;
		$p['percent']     = $total ? (int) round( 100 * $done / $total ) : ( 'done' === $p['status'] ? 100 : 0 );
		return $p;
	}

	private static function badge( $s, $map ) {
		$x = isset( $map[ $s ] ) ? $map[ $s ] : array( ucfirst( $s ), 'neutral' );
		return Portal::badge( $x[0], $x[1] );
	}

	private static function project_badge( $s ) {
		return self::badge( $s, array( 'planned' => array( 'Planned', 'neutral' ), 'active' => array( 'In progress', 'info' ), 'on_hold' => array( 'On hold', 'warning' ), 'done' => array( 'Completed', 'success' ) ) );
	}

	private static function task_badge( $s ) {
		return self::badge( $s, array( 'todo' => array( 'To do', 'neutral' ), 'doing' => array( 'In progress', 'info' ), 'done' => array( 'Done', 'success' ) ) );
	}

	/** The overview card and the "projects in progress" panel. */
	public static function overview( $ctx ) {
		$c      = $ctx['client'];
		$pr     = self::projects( $c['id'] );
		$active = array_values( array_filter( $pr, function ( $p ) {
			return 'active' === $p['status'];
		} ) );
		$cards  = '<a class="stat" href="' . esc_url( Portal::link( 'projects' ) ) . '"><span class="stat__l">Projects</span><span class="stat__v">' . count( $active ) . ' in progress</span><span class="stat__s">' . count( $pr ) . ' in total</span></a>';
		$panels = '';
		if ( $active ) {
			$panels = '<section class="panel"><h2>Projects in progress</h2><ul class="list">';
			foreach ( array_slice( $active, 0, 5 ) as $p ) {
				$panels .= '<li class="list__proj"><a href="' . esc_url( Portal::link( 'projects/' . $p['id'] ) ) . '"><strong>' . Portal::e( $p['name'] ) . '</strong></a>' . Portal::progress( $p['percent'] ) . '<span>' . (int) $p['percent'] . '% · ' . ( $p['due_date'] ? 'due ' . Portal::e( Portal::date( $p['due_date'] ) ) : 'no due date' ) . '</span></li>';
			}
			$panels .= '</ul></section>';
		}
		return array( 'cards' => $cards, 'panels' => $panels );
	}

	private static function task_list( array $tasks, $assignee ) {
		if ( ! $tasks ) {
			return '<p class="muted">No tasks yet.</p>';
		}
		$h = '<ul class="tasks">';
		foreach ( $tasks as $t ) {
			$h .= '<li class="task' . ( 'done' === $t['status'] ? ' is-done' : '' ) . '"><span class="task__t">' . Portal::e( $t['title'] ) . '</span><span class="task__m">' . ( $assignee && ! empty( $t['assignee'] ) ? Portal::e( $t['assignee'] ) . ' · ' : '' ) . ( $t['due_date'] ? 'due ' . Portal::e( Portal::date( $t['due_date'] ) ) : '' ) . '</span>' . self::task_badge( $t['status'] ) . '</li>';
		}
		return $h . '</ul>';
	}

	public static function render( $ctx, $id ) {
		return $id ? self::detail( $ctx, $id ) : self::index( $ctx );
	}

	private static function index( $ctx ) {
		$pr  = self::projects( $ctx['client']['id'] );
		$tasks_on = self::opt( 'portal_show_tasks' );
		$h   = '<h1>Projects</h1><p class="lead">What we are working on for you.</p>';
		if ( ! $pr ) {
			return $h . Portal::empty_state( 'No projects yet', 'When we start a project for you it will appear here with its progress.' );
		}
		$h .= '<div class="cards">';
		foreach ( $pr as $p ) {
			$h .= '<a class="card" href="' . esc_url( Portal::link( 'projects/' . $p['id'] ) ) . '"><div class="card__top"><strong>' . Portal::e( $p['name'] ) . '</strong>' . self::project_badge( $p['status'] ) . '</div>' . ( $p['description'] ? '<p class="muted clamp">' . Portal::e( wp_strip_all_tags( $p['description'] ) ) . '</p>' : '' ) . Portal::progress( $p['percent'] ) . '<div class="card__meta"><span>' . (int) $p['percent'] . '% complete' . ( $tasks_on ? ' · ' . (int) $p['tasks_done'] . ' of ' . (int) $p['tasks_total'] . ' tasks' : '' ) . '</span><span>' . ( $p['due_date'] ? 'Due ' . Portal::e( Portal::date( $p['due_date'] ) ) : '' ) . '</span></div></a>';
		}
		return $h . '</div>';
	}

	private static function detail( $ctx, $id ) {
		$tasks_on = self::opt( 'portal_show_tasks' );
		$assignee = self::opt( 'portal_show_assignee' );
		$p        = self::project( $ctx['client']['id'], $id, $tasks_on && $assignee );
		if ( ! $p ) {
			return Portal::empty_state( 'Not found', 'We could not find that project.' );
		}
		$h  = '<p class="crumb"><a href="' . esc_url( Portal::link( 'projects' ) ) . '">← All projects</a></p><div class="head"><h1>' . Portal::e( $p['name'] ) . '</h1>' . self::project_badge( $p['status'] ) . '</div>';
		$h .= '<div class="meta">' . ( $p['start_date'] ? '<span>Started ' . Portal::e( Portal::date( $p['start_date'] ) ) . '</span>' : '' ) . ( $p['due_date'] ? '<span>Due ' . Portal::e( Portal::date( $p['due_date'] ) ) . '</span>' : '' ) . '</div>';
		$h .= ( $p['description'] ? '<p class="prose">' . nl2br( Portal::e( wp_strip_all_tags( $p['description'] ) ) ) . '</p>' : '' );
		$h .= Portal::progress( $p['percent'] ) . '<p class="muted">' . (int) $p['percent'] . '% complete' . ( $tasks_on ? ' · ' . (int) $p['tasks_done'] . ' of ' . (int) $p['tasks_total'] . ' tasks done' : '' ) . '</p>';
		foreach ( $p['phases'] as $ph ) {
			$h .= '<section class="panel"><div class="head"><h2>' . Portal::e( $ph['name'] ) . '</h2>' . self::badge( $ph['status'], array( 'todo' => array( 'Not started', 'neutral' ), 'doing' => array( 'In progress', 'info' ), 'done' => array( 'Complete', 'success' ) ) ) . '</div>' . ( $ph['due_date'] ? '<p class="muted">Due ' . Portal::e( Portal::date( $ph['due_date'] ) ) . '</p>' : '' ) . ( $tasks_on ? self::task_list( $ph['tasks'], $assignee ) : '' ) . '</section>';
		}
		if ( $tasks_on && $p['other'] ) {
			$h .= '<section class="panel"><h2>' . ( $p['phases'] ? 'Other tasks' : 'Tasks' ) . '</h2>' . self::task_list( $p['other'], $assignee ) . '</section>';
		}
		if ( ! $p['phases'] && ! $p['other'] ) {
			$h .= Portal::empty_state( 'Nothing planned yet', 'Phases and tasks will show here as the work is planned.' );
		}
		return $h;
	}
}
