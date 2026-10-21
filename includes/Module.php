<?php
namespace WPFlatTasks;

defined( 'ABSPATH' ) || exit;

/** WPFlat Tasks' connection to WPFlat Core: it describes itself, then hooks into the shared places. Nothing else is global. */
class Module {

	public static function init() {
		Install::maybe_upgrade();
		add_action( 'wpflatcore_register', array( __CLASS__, 'register' ) );
		add_action( 'wpflatcore_register_terms', array( __CLASS__, 'terms' ) );
		add_action( 'wpflatcore_register_resources', array( Resources_Def::class, 'register' ) );
		add_action( 'wpflatcore_register_link_types', array( Integrations::class, 'link_types' ) );
		add_action( 'wpflatcore_register_notifications', array( Notify::class, 'types' ) );
		add_action( 'wpflatcore_register_portal', array( Portal_Page::class, 'register' ) );
		add_action( 'wpflatcore_register_routes', array( Routes::class, 'register' ) );
		Integrations::init();
		Notify::init();
		Legacy::init();
		Demo::init();
	}

	public static function register() {
		wpflatcore_register_module(
			array(
				'id'            => 'tasks',
				'label'         => 'Tasks',
				'version'       => WPFLATTASKS_VERSION,
				'requires_core' => '0.6.0',
				'desc'          => 'Projects, phases and tasks. Who is doing what, and when it is due.',
				'icon'          => 'tasks',
				'route'         => '/tasks',
				'routes'        => array( 'tasks', 'projects' ),
				'cap'           => 'wpflattasks_work',
				'caps'          => array( 'wpflattasks_work' ),
				'resources'     => array( 'projects', 'tasks', 'project-phases' ),
				'tables'        => array( 'wpflattasks_projects', 'wpflattasks_phases', 'wpflattasks_tasks' ),
				'refs'          => array(
					'wpflattasks_projects' => array( 'client_id' => 'wpflat_clients' ),
					'wpflattasks_phases'   => array( 'project_id' => 'wpflattasks_projects' ),
					'wpflattasks_tasks'    => array( 'project_id' => 'wpflattasks_projects' ),
				),
				'roles'         => array(
					'wpflat_tasks_team' => array( 'label' => 'WPFlat Tasks Team', 'desc' => 'Does the work: projects, phases and tasks.', 'caps' => array( 'wpflattasks_work', 'wpflatcore_messages' ) ),
				),
				'client_manager_caps' => array( 'wpflattasks_work' ),
				'team_manager_caps'   => array( 'wpflattasks_work' ),
				'manager_grants'      => array( 'tasks' => array( 'label' => 'Tasks', 'caps' => array( 'wpflattasks_work' ) ) ),
				'menu'          => array(
					array( 'label' => 'Overview', 'route' => '/tasks' ),
					array( 'label' => 'My tasks', 'route' => '/tasks/mine' ),
					array( 'label' => 'All tasks', 'route' => '/tasks/all' ),
					array( 'label' => 'Projects', 'route' => '/tasks/projects' ),
					array( 'label' => 'Analytics', 'route' => '/tasks/analytics' ),
				),
				'assets'        => array( 'script' => 'wpflattasks.js', 'style' => 'wpflattasks.css' ),
				'assets_dir'    => WPFLATTASKS_DIR . 'build',
				'wp'            => array( 'dashicons-yes-alt', 27 ),
			)
		);
	}

	public static function terms() {
		\WPFlatCore\Terminology::register( 'project', 'Project', 'Projects' );
		\WPFlatCore\Terminology::register( 'task', 'Task', 'Tasks' );
	}
}
