/**
 * Table and form configuration for projects and tasks. Other modules extend them through BizyCore's extension
 * points: task.fields, task.columns, project.fields, project.columns (BizyFinance adds a task's pay and a project's price).
 */
import { h } from 'vue';
import { sdk, api, errorMessage, navigate, ElMessage, avatarStyle, fmtDate, initials, relDays, today, dept } from './core';
import { PROJECT_STATUS, PROJECT_STATUS_OPTIONS, TASK_STATUS, TASK_STATUS_OPTIONS } from './util';

const muted = ( t ) => h( 'span', { class: 'bd-muted' }, t );
const pill = ( def, text ) => h( 'span', { class: [ 'bd-badge', 'bd-badge--' + ( def ? def.tone : 'neutral' ) ] }, text || ( def ? def.label : '' ) );
const person = ( name ) => ( name ? h( 'div', { class: 'bd-cell-client' }, [ h( 'span', { class: 'bd-ava', style: avatarStyle( name ) }, initials( name ) ), h( 'span', name ) ] ) : muted( 'Unassigned' ) );
const daysTo = ( d ) => Math.round( ( new Date( d + 'T00:00:00' ) - new Date( today() + 'T00:00:00' ) ) / 86400000 );
const dueCell = ( r ) => {
	if ( ! r.due_date ) {
		return muted( '—' );
	}
	const days = daysTo( r.due_date );
	return h( 'div', [ h( 'div', fmtDate( r.due_date ) ), r.status !== 'done' ? h( 'div', { class: ( days < 0 ? 'bd-late' : 'bd-muted' ) + ' bd-small' }, relDays( days ) ) : null ] );
};
const merged = ( base, point, key ) => [ ...base.map( ( x, i ) => ( { order: ( i + 1 ) * 10, item: x } ) ), ...sdk.extensions( point ).map( ( e ) => ( { order: e.order, item: e[ key ] } ) ) ].sort( ( a, b ) => a.order - b.order ).map( ( x ) => x.item );
const deptField = ( help ) => ( { name: 'department_id', label: dept().one, type: 'remote', remote: { resource: 'departments', label: ( b ) => b.name, params: () => ( { status: 'active' } ) }, help: help || '' } );
const deptFilter = () => ( { name: 'department_id', label: dept().one, remote: { resource: 'departments', label: ( b ) => b.name, params: { status: 'active' } } } );

export const setTaskStatus = async ( r, status, reload ) => {
	try {
		await api.update( 'tasks', r.id, { status } );
		reload && reload();
	} catch ( e ) {
		ElMessage.error( errorMessage( e ) );
	}
};

export const taskFields = ( { fixed = {} } = {} ) =>
	merged(
		[
			{ name: 'title', label: sdk.t( 'task' ), required: true, wide: true, placeholder: 'What needs to be done?' },
			{ name: 'client_id', label: sdk.t( 'client' ), type: 'remote', wide: true, remote: { resource: 'clients', label: ( c ) => c.name, params: () => ( { status: 'active' } ) }, help: 'Optional — leave empty for an internal task.' },
			{ name: 'project_id', label: sdk.t( 'project' ), type: 'remote', wide: true, remote: { resource: 'projects', label: ( p ) => p.name + ( p.client_name ? ' — ' + p.client_name : '' ), params: ( v ) => ( v && v.client_id ? { client_id: v.client_id } : {} ) }, onPick: ( p ) => ( p.client_id ? { client_id: p.client_id } : {} ) },
			{ name: 'phase_id', label: 'Phase', type: 'remote', remote: { resource: 'project-phases', label: ( p ) => p.name, params: ( v ) => ( v && v.project_id ? { project_id: v.project_id } : null ) }, show: ( v ) => !! v.project_id, help: 'Which phase of the project this task belongs to.' },
			{ name: 'assignee_id', label: 'Assigned to', type: 'remote', remote: { resource: 'team', label: ( t ) => t.name, params: () => ( { status: 'active' } ) } },
			deptField( 'Optional. Leave empty to follow the phase or project.' ),
			{ name: 'due_date', label: 'Due date', type: 'date' },
			{ name: 'priority', label: 'Priority', type: 'select', options: [ { value: 'normal', label: 'Normal' }, { value: 'high', label: 'High' } ] },
			{ name: 'status', label: 'Status', type: 'select', required: true, options: TASK_STATUS_OPTIONS },
			{ name: 'notes', label: 'Notes', type: 'textarea', wide: true },
		],
		'task.fields',
		'field'
	).filter( ( f ) => ! ( f.name in fixed ) );

export const tasksConfig = ( { hideClient, hideProject, fixed = {} } = {} ) => ( {
	title: sdk.tp( 'task' ),
	description: 'Everything that needs doing — yours and your team’s.',
	resource: 'tasks',
	bulkDepartment: true,
	singular: sdk.t( 'task' ).toLowerCase(),
	newLabel: `Add ${ sdk.t( 'task' ).toLowerCase() }`,
	emptyText: 'No tasks here yet. Add one above, or press “Add”.',
	initialFilters: {},
	filters: [
		{ name: 'status', label: 'Status', options: TASK_STATUS_OPTIONS },
		{ name: 'due', label: 'Due', options: [ { value: 'overdue', label: 'Overdue' }, { value: 'today', label: 'Due today or earlier' }, { value: 'week', label: 'Due this week' } ] },
		{ name: 'priority', label: 'Priority', options: [ { value: 'high', label: 'High priority' } ] },
		...( fixed.client_id || fixed.project_id ? [] : [ deptFilter() ] ),
	],
	defaults: { status: 'todo', priority: 'normal', ...fixed },
	actionsWidth: 120,
	actions: ( r, ctx ) => [
		r.status === 'todo' ? { label: 'Start', onClick: () => setTaskStatus( r, 'doing', ctx.reload ) } : null,
		r.status !== 'done' ? { label: 'Done', type: 'primary', onClick: () => setTaskStatus( r, 'done', ctx.reload ) } : { label: 'Reopen', onClick: () => setTaskStatus( r, 'todo', ctx.reload ) },
	],
	columns: [
		{
			label: sdk.t( 'task' ),
			minWidth: 280,
			render: ( r ) =>
				h( 'div', [
					h( 'div', [ h( 'span', { class: r.status === 'done' ? 'bd-strong bd-done' : 'bd-strong' }, r.title ), r.priority === 'high' ? h( 'span', { class: 'bd-badge bd-badge--danger bd-badge--plain bd-ml' }, 'High' ) : null ] ),
					h( 'div', { class: 'bd-muted bd-small' }, [ ! hideProject && r.project_name ? r.project_name : '', r.phase_name || '', ! hideClient && r.client_name ? r.client_name : '' ].filter( Boolean ).join( ' · ' ) ),
				] ),
		},
		{ label: 'Due', minWidth: 130, render: dueCell },
		{ label: 'Assigned to', minWidth: 160, render: ( r ) => person( r.assignee_name ) },
		...sdk.extensions( 'task.columns' ).map( ( e ) => e.column ),
		{ label: 'Status', minWidth: 100, render: ( r ) => pill( TASK_STATUS[ r.status ] ) },
	],
	fields: taskFields( { fixed } ),
} );

export const projectFields = ( { fixed = {} } = {} ) =>
	merged(
		[
			{ name: 'name', label: `${ sdk.t( 'project' ) } name`, required: true, wide: true, placeholder: 'e.g. Acme website redesign' },
			{ name: 'client_id', label: sdk.t( 'client' ), type: 'remote', wide: true, remote: { resource: 'clients', label: ( c ) => c.name, params: () => ( { status: 'active' } ) }, help: 'Optional — leave empty for an internal project.' },
			deptField(),
			{ name: 'status', label: 'Status', type: 'select', required: true, options: PROJECT_STATUS_OPTIONS },
			{ name: 'owner_id', label: 'Owner', type: 'remote', remote: { resource: 'team', label: ( t ) => t.name, params: () => ( { status: 'active' } ) } },
			{ name: 'start_date', label: 'Start date', type: 'date' },
			{ name: 'due_date', label: 'Due date', type: 'date' },
			{ name: 'description', label: `What is this ${ sdk.t( 'project' ).toLowerCase() } about?`, type: 'textarea', wide: true },
		],
		'project.fields',
		'field'
	).filter( ( f ) => ! ( f.name in fixed ) );

export const projectsConfig = ( { hideClient, fixed = {} } = {} ) => ( {
	title: sdk.tp( 'project' ),
	description: 'Bigger pieces of work, with their tasks inside.',
	resource: 'projects',
	bulkDepartment: true,
	singular: sdk.t( 'project' ).toLowerCase(),
	newLabel: `New ${ sdk.t( 'project' ).toLowerCase() }`,
	emptyText: 'No projects yet. Start one for a website build, a campaign, a rebrand…',
	filters: [ { name: 'status', label: 'Status', options: PROJECT_STATUS_OPTIONS }, ...( fixed.client_id ? [] : [ deptFilter() ] ) ],
	defaults: { status: 'active', ...fixed },
	onEdit: ( r ) => navigate( `/projects/${ r.id }` ),
	columns: [
		{ label: sdk.t( 'project' ), minWidth: 260, render: ( r ) => h( 'div', [ h( 'a', { class: 'bd-strong bd-link', href: `#/projects/${ r.id }` }, r.name ), ! hideClient && r.client_name ? h( 'div', { class: 'bd-muted bd-small' }, r.client_name ) : null ] ) },
		{ label: 'Status', minWidth: 110, render: ( r ) => pill( PROJECT_STATUS[ r.status ] ) },
		{
			label: 'Progress',
			minWidth: 190,
			render: ( r ) =>
				h( 'div', { class: 'bd-prog' }, [
					h( 'div', { class: 'bd-prog__bar' }, [ h( 'span', { style: { width: r.progress + '%' } } ) ] ),
					h( 'div', { class: 'bd-muted bd-small' }, r.task_total ? `${ r.task_done } of ${ r.task_total } tasks done` : 'No tasks yet' ),
				] ),
		},
		{ label: 'Due', minWidth: 130, render: ( r ) => ( r.due_date ? h( 'div', [ h( 'div', fmtDate( r.due_date ) ), r.status !== 'done' ? h( 'div', { class: ( r.is_overdue ? 'bd-late' : 'bd-muted' ) + ' bd-small' }, relDays( daysTo( r.due_date ) ) ) : null ] ) : muted( '—' ) ) },
		{ label: 'Owner', minWidth: 150, render: ( r ) => person( r.owner_name ) },
		...sdk.extensions( 'project.columns' ).map( ( e ) => e.column ),
	],
	fields: projectFields( { fixed } ),
} );
