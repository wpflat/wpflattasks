import { h } from 'vue';
import { SquareCheckBig } from 'lucide-vue-next';
import { sdk } from './core';
import WorkOverview from './views/WorkOverview.vue';
import Work from './views/Work.vue';
import ProjectDetail from './views/ProjectDetail.vue';
import ClientTasksTab from './slots/ClientTasksTab.vue';
import ClientStat from './slots/ClientStat.vue';
import ClientOverview from './slots/ClientOverview.vue';
import TeamStats from './slots/TeamStats.vue';
import TeamOverview from './slots/TeamOverview.vue';
import TeamTasksTab from './slots/TeamTasksTab.vue';
import DepartmentStats from './slots/DepartmentStats.vue';
import DepartmentSections from './slots/DepartmentSections.vue';
import DepartmentCardCounts from './slots/DepartmentCardCounts.vue';
import HomeStats from './slots/HomeStats.vue';

/** WPFlat Tasks registers its screens and what it adds to WPFlat Core's pages. Nothing runs unless the module is on. */
const M = 'tasks';
sdk.registerIcon( 'tasks', SquareCheckBig );

sdk.registerRoute( { path: '/tasks', module: M, component: WorkOverview } );
sdk.registerRoute( { path: '/tasks/:tab', module: M, component: Work } );
sdk.registerRoute( { path: '/projects/:id', module: M, component: ProjectDetail } );

sdk.registerSlot( 'client.tabs', { id: 'tasks', module: M, order: 30, label: sdk.tp( 'task' ), component: ClientTasksTab } );
sdk.registerSlot( 'client.stats', { id: 'tasks', module: M, order: 30, component: ClientStat } );
sdk.registerSlot( 'client.overview', { id: 'tasks', module: M, order: 30, component: ClientOverview } );
sdk.registerSlot( 'team.tabs', { id: 'tasks', module: M, order: 30, label: sdk.tp( 'task' ), component: TeamTasksTab } );
sdk.registerSlot( 'team.stats', { id: 'tasks', module: M, order: 30, component: TeamStats } );
sdk.registerSlot( 'team.overview', { id: 'tasks', module: M, order: 30, component: TeamOverview } );
sdk.registerSlot( 'department.stats', { id: 'tasks', module: M, order: 30, component: DepartmentStats } );
sdk.registerSlot( 'department.sections', { id: 'tasks', module: M, order: 30, component: DepartmentSections } );
sdk.registerSlot( 'department.card.counts', { id: 'tasks', module: M, order: 30, component: DepartmentCardCounts } );
sdk.registerSlot( 'home.stats', { id: 'tasks', module: M, order: 30, component: HomeStats } );

// A "Work" column on the team list.
sdk.extend( 'team.columns', {
	id: 'tasks-work',
	module: M,
	order: 30,
	column: {
		label: 'Work',
		minWidth: 150,
		render: ( r ) => ( ! r.open_tasks && ! r.active_projects ? h( 'span', { class: 'bd-muted' }, 'Nothing open' ) : h( 'div', [ h( 'div', `${ r.open_tasks } open task${ r.open_tasks === 1 ? '' : 's' }` ), h( 'div', { class: ( r.overdue_tasks ? 'bd-late' : 'bd-muted' ) + ' bd-small' }, r.overdue_tasks ? `${ r.overdue_tasks } overdue` : `${ r.active_projects } project${ r.active_projects === 1 ? '' : 's' }` ) ] ) ),
	},
} );
sdk.extend( 'activity.types', { id: 'tasks-activity', module: M, type: { value: 'tasks', label: sdk.tp( 'task' ) } } );
sdk.extend( 'activity.types', { id: 'projects-activity', module: M, order: 51, type: { value: 'projects', label: sdk.tp( 'project' ) } } );
