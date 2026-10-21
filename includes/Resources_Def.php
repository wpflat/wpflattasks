<?php
namespace WPFlatTasks;

use WPFlatCore\Resources;

defined( 'ABSPATH' ) || exit;

/** WPFlat Tasks' REST resources: projects, tasks and project phases (served by WPFlat Core's resource controller). */
class Resources_Def {

	public static function today() {
		return current_time( 'Y-m-d' );
	}

	public static function register() {
		$P  = Install::table( 'projects' );
		$PH = Install::table( 'phases' );
		$TK = Install::table( 'tasks' );
		global $wpdb;
		$cl = $wpdb->prefix . 'wpflat_clients';
		$tm = $wpdb->prefix . 'wpflat_team_members';
		$dp = $wpdb->prefix . 'wpflat_departments';

		Resources::register( 'projects', array(
			'route'     => 'projects',
			'table'     => 'wpflattasks_projects',
			'label'     => 'Project',
			'select'    => ", c.name AS client_name, o.name AS owner_name, dp.name AS department_name, (SELECT COUNT(*) FROM $TK tk WHERE tk.project_id=t.id) AS task_total, (SELECT COUNT(*) FROM $TK tk2 WHERE tk2.project_id=t.id AND tk2.status='done') AS task_done",
			'join'      => "LEFT JOIN $cl c ON c.id=t.client_id LEFT JOIN $tm o ON o.id=t.owner_id LEFT JOIN $dp dp ON dp.id=t.department_id",
			'cols'      => array( 'client_id' => 'i', 'name' => 's', 'description' => 't', 'status' => 's', 'start_date' => 'date', 'due_date' => 'date', 'owner_id' => 'i', 'department_id' => 'i' ),
			'filters'   => array( 'client_id', 'status', 'owner_id', 'department_id' ),
			'search'    => array( 't.name', 'c.name', 't.description' ),
			'order'     => "FIELD(t.status,'active','planned','on_hold','done'), t.due_date IS NULL, t.due_date ASC, t.id DESC",
			'order_map' => array( 'client_name' => 'c.name', 'owner_name' => 'o.name' ),
			'required'  => array( 'name' ),
			'title'     => function ( $r ) {
				return $r['name'];
			},
			'before_save' => function ( $d, $existing ) {
				if ( isset( $d['status'] ) && ! in_array( $d['status'], array( 'planned', 'active', 'on_hold', 'done' ), true ) ) {
					return new \WP_Error( 'wpflattasks_invalid', 'Unknown project status.', array( 'status' => 400 ) );
				}
				return $existing ? $d : $d + array( 'status' => 'active' );
			},
			'decorate'  => function ( $rows ) {
				foreach ( $rows as &$r ) {
					$tot             = (int) $r['task_total'];
					$r['task_total'] = $tot;
					$r['task_done']  = (int) $r['task_done'];
					$r['progress']   = $tot ? (int) round( 100 * $r['task_done'] / $tot ) : 0;
					$r['is_overdue'] = ! empty( $r['due_date'] ) && 'done' !== $r['status'] && $r['due_date'] < self::today();
				}
				return $rows;
			},
			'after_delete' => function ( $row ) {
				global $wpdb;
				$wpdb->update( Install::table( 'tasks' ), array( 'project_id' => 0, 'phase_id' => 0 ), array( 'project_id' => (int) $row['id'] ) ); // keep the tasks, just unlink
				$wpdb->delete( Install::table( 'phases' ), array( 'project_id' => (int) $row['id'] ) );
			},
		), 'tasks' );

		Resources::register( 'tasks', array(
			'route'     => 'tasks',
			'table'     => 'wpflattasks_tasks',
			'label'     => 'Task',
			'select'    => ', c.name AS client_name, p.name AS project_name, ph.name AS phase_name, a.name AS assignee_name',
			'join'      => "LEFT JOIN $cl c ON c.id=t.client_id LEFT JOIN $P p ON p.id=t.project_id LEFT JOIN $PH ph ON ph.id=t.phase_id LEFT JOIN $tm a ON a.id=t.assignee_id",
			'cols'      => array( 'project_id' => 'i', 'client_id' => 'i', 'title' => 's', 'notes' => 't', 'status' => 's', 'priority' => 's', 'due_date' => 'date', 'assignee_id' => 'i', 'phase_id' => 'i', 'sort_order' => 'i', 'department_id' => 'i' ),
			'filters'   => array( 'client_id', 'project_id', 'phase_id', 'status', 'priority', 'assignee_id' ),
			'extra'     => function ( $a ) {
				$sql   = array();
				$args  = array();
				$today = self::today();
				if ( ! empty( $a['open'] ) ) {
					$sql[] = "t.status <> 'done'";
				}
				if ( isset( $a['department_id'] ) && '' !== $a['department_id'] ) {
					$sql[]  = 'COALESCE(NULLIF(t.department_id,0), NULLIF(ph.department_id,0), NULLIF(p.department_id,0), 0) = %d'; // its own, else the phase's, else the project's
					$args[] = (int) $a['department_id'];
				}
				if ( ! empty( $a['mine'] ) ) {
					$sql[]  = 't.assignee_id IN (SELECT id FROM ' . $GLOBALS['wpdb']->prefix . 'wpflat_team_members WHERE wp_user_id=%d)';
					$args[] = get_current_user_id();
				}
				if ( ! empty( $a['due'] ) ) {
					if ( 'overdue' === $a['due'] ) {
						$sql[]  = "t.status <> 'done' AND t.due_date < %s";
						$args[] = $today;
					} elseif ( 'today' === $a['due'] ) {
						$sql[]  = "t.status <> 'done' AND t.due_date <= %s";
						$args[] = $today;
					} elseif ( 'week' === $a['due'] ) {
						$sql[]  = "t.status <> 'done' AND t.due_date <= %s";
						$args[] = gmdate( 'Y-m-d', strtotime( $today . ' +7 days' ) );
					}
				}
				return array( implode( ' AND ', $sql ), $args );
			},
			'search'    => array( 't.title', 't.notes', 'c.name', 'p.name' ),
			'order'     => "FIELD(t.status,'doing','todo','done'), t.due_date IS NULL, t.due_date ASC, t.sort_order ASC, t.id DESC",
			'order_map' => array( 'client_name' => 'c.name', 'project_name' => 'p.name', 'assignee_name' => 'a.name' ),
			'required'  => array( 'title' ),
			'title'     => function ( $r ) {
				return $r['title'];
			},
			'before_save' => function ( $d, $existing ) {
				global $wpdb;
				if ( isset( $d['status'] ) && ! in_array( $d['status'], array( 'todo', 'doing', 'done' ), true ) ) {
					return new \WP_Error( 'wpflattasks_invalid', 'Unknown task status.', array( 'status' => 400 ) );
				}
				if ( isset( $d['priority'] ) && ! in_array( $d['priority'], array( 'normal', 'high' ), true ) ) {
					$d['priority'] = 'normal';
				}
				if ( ! $existing ) {
					$d += array( 'status' => 'todo', 'priority' => 'normal' );
				}
				$pid = isset( $d['project_id'] ) ? (int) $d['project_id'] : ( $existing ? (int) $existing['project_id'] : 0 );
				$cid = isset( $d['client_id'] ) ? (int) $d['client_id'] : ( $existing ? (int) $existing['client_id'] : 0 );
				if ( $pid && ! $cid ) {
					$d['client_id'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT client_id FROM ' . Install::table( 'projects' ) . ' WHERE id=%d', $pid ) ); // phpcs:ignore WordPress.DB
				}
				if ( isset( $d['status'] ) && 'done' === $d['status'] && ( ! $existing || 'done' !== $existing['status'] ) ) {
					$d['completed_at'] = current_time( 'mysql' );
				}
				return $d;
			},
			'after_save' => function ( $row ) {
				global $wpdb;
				$tbl = Install::table( 'tasks' );
				if ( 'done' !== $row['status'] && ! empty( $row['completed_at'] ) ) {
					$wpdb->query( $wpdb->prepare( "UPDATE $tbl SET completed_at = NULL WHERE id=%d", $row['id'] ) ); // phpcs:ignore WordPress.DB
				} elseif ( 'done' === $row['status'] && empty( $row['completed_at'] ) ) {
					$wpdb->query( $wpdb->prepare( "UPDATE $tbl SET completed_at = %s WHERE id=%d", current_time( 'mysql' ), $row['id'] ) ); // phpcs:ignore WordPress.DB
				}
			},
			'decorate'  => function ( $rows ) {
				$today = self::today();
				foreach ( $rows as &$r ) {
					$r['is_overdue'] = ! empty( $r['due_date'] ) && 'done' !== $r['status'] && $r['due_date'] < $today;
					$r['is_today']   = ! empty( $r['due_date'] ) && 'done' !== $r['status'] && $r['due_date'] === $today;
				}
				return $rows;
			},
		), 'tasks' );

		Resources::register( 'project-phases', array(
			'route'     => 'project-phases',
			'table'     => 'wpflattasks_phases',
			'label'     => 'Phase',
			'select'    => ", (SELECT COUNT(*) FROM $TK tk WHERE tk.phase_id=t.id) AS task_total, (SELECT COUNT(*) FROM $TK tk2 WHERE tk2.phase_id=t.id AND tk2.status='done') AS task_done",
			'cols'      => array( 'project_id' => 'i', 'name' => 's', 'sort_order' => 'i', 'status' => 's', 'start_date' => 'date', 'due_date' => 'date', 'department_id' => 'i' ),
			'filters'   => array( 'project_id', 'status', 'department_id' ),
			'search'    => array( 't.name' ),
			'order'     => 't.sort_order ASC, t.id ASC',
			'required'  => array( 'project_id', 'name' ),
			'title'     => function ( $r ) {
				return $r['name'];
			},
			'before_save' => function ( $d, $existing ) {
				global $wpdb;
				if ( isset( $d['status'] ) && ! in_array( $d['status'], array( 'todo', 'doing', 'done' ), true ) ) {
					return new \WP_Error( 'wpflattasks_invalid', 'Unknown phase status.', array( 'status' => 400 ) );
				}
				if ( ! $existing ) {
					$d += array( 'status' => 'todo' );
					if ( ! isset( $d['sort_order'] ) ) {
						$d['sort_order'] = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(MAX(sort_order),0) FROM ' . Install::table( 'phases' ) . ' WHERE project_id=%d', isset( $d['project_id'] ) ? $d['project_id'] : 0 ) ); // phpcs:ignore WordPress.DB
					}
				}
				return $d;
			},
			'after_save' => function ( $row ) {
				global $wpdb;
				$tbl = Install::table( 'phases' );
				if ( 'done' === $row['status'] && empty( $row['completed_at'] ) ) {
					$wpdb->query( $wpdb->prepare( "UPDATE $tbl SET completed_at=%s WHERE id=%d", current_time( 'mysql' ), $row['id'] ) ); // phpcs:ignore WordPress.DB
				} elseif ( 'done' !== $row['status'] && ! empty( $row['completed_at'] ) ) {
					$wpdb->query( $wpdb->prepare( "UPDATE $tbl SET completed_at=NULL WHERE id=%d", $row['id'] ) ); // phpcs:ignore WordPress.DB
				}
			},
			'after_delete' => function ( $row ) {
				global $wpdb;
				$wpdb->update( Install::table( 'tasks' ), array( 'phase_id' => 0 ), array( 'phase_id' => (int) $row['id'] ) );
			},
			'decorate'  => function ( $rows ) {
				foreach ( $rows as &$r ) {
					$r['task_total'] = (int) $r['task_total'];
					$r['task_done']  = (int) $r['task_done'];
				}
				return $rows;
			},
		), 'tasks' );
	}
}
