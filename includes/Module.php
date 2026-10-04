<?php
namespace BizyTasks;

defined( 'ABSPATH' ) || exit;

/** BizyTasks' connection to BizyCore: it describes itself, then hooks into the shared places. Nothing else is global. */
class Module {

	public static function init() {
		Install::maybe_upgrade();
		add_action( 'bizycore_register', array( __CLASS__, 'register' ) );
		add_action( 'bizycore_register_terms', array( __CLASS__, 'terms' ) );
		add_action( 'bizycore_register_resources', array( Resources_Def::class, 'register' ) );
		add_action( 'bizycore_register_link_types', array( Integrations::class, 'link_types' ) );
		add_action( 'bizycore_register_notifications', array( Notify::class, 'types' ) );
		add_action( 'bizycore_register_portal', array( Portal_Page::class, 'register' ) );
		add_action( 'bizycore_register_routes', array( Routes::class, 'register' ) );
		Integrations::init();
		Notify::init();
		Legacy::init();
		Demo::init();
	}

	public static function register() {
		bizycore_register_module(
			array(
				'id'            => 'tasks',
				'label'         => 'Tasks',
				'version'       => BIZYTASKS_VERSION,
				'requires_core' => '0.1.0',
				'desc'          => 'Projects, phases and tasks. Who is doing what, and when it is due.',
				'icon'          => 'tasks',
				'route'         => '/tasks',
				'routes'        => array( 'tasks', 'projects' ),
				'cap'           => 'bizytasks_work',
				'caps'          => array( 'bizytasks_work' ),
				'resources'     => array( 'projects', 'tasks', 'project-phases' ),
				'tables'        => array( 'bizytasks_projects', 'bizytasks_phases', 'bizytasks_tasks' ),
				'refs'          => array(
					'bizytasks_projects' => array( 'client_id' => 'bizy_clients' ),
					'bizytasks_phases'   => array( 'project_id' => 'bizytasks_projects' ),
					'bizytasks_tasks'    => array( 'project_id' => 'bizytasks_projects' ),
				),
				'roles'         => array(
					'bizy_tasks_team' => array( 'label' => 'Bizy Tasks Team', 'desc' => 'Does the work: projects, phases and tasks.', 'caps' => array( 'bizytasks_work', 'bizycore_messages' ) ),
				),
				'client_manager_caps' => array( 'bizytasks_work' ),
				'team_manager_caps'   => array( 'bizytasks_work' ),
				'manager_grants'      => array( 'tasks' => array( 'label' => 'Tasks', 'caps' => array( 'bizytasks_work' ) ) ),
				'menu'          => array(
					array( 'label' => 'Overview', 'route' => '/tasks' ),
					array( 'label' => 'My tasks', 'route' => '/tasks/mine' ),
					array( 'label' => 'All tasks', 'route' => '/tasks/all' ),
					array( 'label' => 'Projects', 'route' => '/tasks/projects' ),
					array( 'label' => 'Analytics', 'route' => '/tasks/analytics' ),
				),
				'assets'        => array( 'script' => 'bizytasks.js', 'style' => 'bizytasks.css' ),
				'assets_dir'    => BIZYTASKS_DIR . 'build',
				'wp'            => array( 'dashicons-yes-alt', 27 ),
			)
		);
	}

	public static function terms() {
		\BizyCore\Terminology::register( 'project', 'Project', 'Projects' );
		\BizyCore\Terminology::register( 'task', 'Task', 'Tasks' );
	}
}
