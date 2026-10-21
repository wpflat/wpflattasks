<?php
namespace WPFlatTasks;

use WPFlatCore\Services\Demo as Core_Demo;

defined( 'ABSPATH' ) || exit;

/** WPFlat Tasks' part of the sample data: a project with phases and tasks, and one planned project. */
class Demo {

	public static function init() {
		add_action( 'wpflatcore_demo_load', array( __CLASS__, 'load' ) );
	}

	public static function load( $ctx ) {
		if ( ! \WPFlatCore\Registry::enabled( 'tasks' ) ) {
			return;
		}
		$web = $ctx['web'];
		$c   = $ctx['clients'];
		$t   = $ctx['team'];
		$p1  = Core_Demo::add( 'wpflattasks_projects', array( 'client_id' => $c[0], 'name' => 'Website redesign', 'status' => 'active', 'start_date' => Core_Demo::day( -20 ), 'due_date' => Core_Demo::day( 25 ), 'department_id' => $web, 'owner_id' => $t[0] ) );
		$h1  = Core_Demo::add( 'wpflattasks_phases', array( 'project_id' => $p1, 'name' => 'Design', 'sort_order' => 1, 'status' => 'done', 'due_date' => Core_Demo::day( -5 ), 'department_id' => $web ) );
		$h2  = Core_Demo::add( 'wpflattasks_phases', array( 'project_id' => $p1, 'name' => 'Build', 'sort_order' => 2, 'status' => 'doing', 'due_date' => Core_Demo::day( 20 ), 'department_id' => $web ) );
		Core_Demo::add( 'wpflattasks_tasks', array( 'project_id' => $p1, 'client_id' => $c[0], 'phase_id' => $h1, 'title' => 'Homepage mock-up', 'status' => 'done', 'priority' => 'normal', 'assignee_id' => $t[0], 'department_id' => $web ) );
		Core_Demo::add( 'wpflattasks_tasks', array( 'project_id' => $p1, 'client_id' => $c[0], 'phase_id' => $h2, 'title' => 'Build the home page', 'status' => 'doing', 'priority' => 'high', 'due_date' => Core_Demo::day( 7 ), 'assignee_id' => $t[1], 'department_id' => $web ) );
		Core_Demo::add( 'wpflattasks_tasks', array( 'project_id' => $p1, 'client_id' => $c[0], 'phase_id' => $h2, 'title' => 'Contact form', 'status' => 'todo', 'priority' => 'normal', 'due_date' => Core_Demo::day( 14 ), 'assignee_id' => $t[1], 'department_id' => $web ) );
		Core_Demo::add( 'wpflattasks_projects', array( 'client_id' => $c[1], 'name' => 'Q4 campaign', 'status' => 'planned', 'start_date' => Core_Demo::day( 5 ), 'due_date' => Core_Demo::day( 60 ), 'department_id' => $ctx['marketing'] ) );
	}
}
