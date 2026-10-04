import { today } from './core';

/** Plain-word statuses for projects, phases and tasks. */
export const TASK_STATUS = {
	todo: { label: 'To do', tone: 'neutral' },
	doing: { label: 'Doing', tone: 'info' },
	done: { label: 'Done', tone: 'success' },
};
export const PROJECT_STATUS = {
	planned: { label: 'Planned', tone: 'neutral' },
	active: { label: 'Active', tone: 'info' },
	on_hold: { label: 'On hold', tone: 'warning' },
	done: { label: 'Done', tone: 'success' },
};
export const TASK_STATUS_OPTIONS = Object.entries( TASK_STATUS ).map( ( [ value, d ] ) => ( { value, label: d.label } ) );
export const PROJECT_STATUS_OPTIONS = Object.entries( PROJECT_STATUS ).map( ( [ value, d ] ) => ( { value, label: d.label } ) );

// Date presets for the analytics bar.
const iso = ( d ) => d.toISOString().slice( 0, 10 );
const shift = ( dateStr, days ) => {
	const d = new Date( dateStr + 'T00:00:00Z' );
	d.setUTCDate( d.getUTCDate() + days );
	return iso( d );
};
/** { from, to } (inclusive, YYYY-MM-DD) for a preset id. */
export const rangeFor = ( id, custom = [] ) => {
	const to = today();
	if ( id === '30d' ) return { from: shift( to, -29 ), to };
	if ( id === '90d' ) return { from: shift( to, -89 ), to };
	if ( id === 'ytd' ) return { from: to.slice( 0, 4 ) + '-01-01', to };
	if ( id === '12m' ) {
		const d = new Date( to + 'T00:00:00Z' );
		d.setUTCMonth( d.getUTCMonth() - 11, 1 );
		return { from: iso( d ), to };
	}
	return { from: custom[ 0 ] || shift( to, -29 ), to: custom[ 1 ] || to };
};
