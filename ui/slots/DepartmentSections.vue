<script setup>
import { fmtDate, navigate, sdk } from '../core';

defineProps( { department: { type: Object, default: () => ( {} ) }, q: { type: Function, default: ( x ) => x } } );
const C = window.WPFlatCore.components.ChartCard;
</script>

<template>
	<div v-if="department.modules && department.modules.tasks" class="bd-angrid bd-mt">
		<C :title="sdk.tp( 'project' )" sub="Active and planned" :empty="!department.modules.tasks.projects.length" empty-text="No open projects.">
			<template #tools><a class="bd-link bd-small" :href="'#' + q( '/tasks/all' )" @click.prevent="navigate( q( '/tasks/all' ) )">Open tasks</a> <a class="bd-link bd-small bd-ml" :href="'#' + q( '/tasks/projects' )" @click.prevent="navigate( q( '/tasks/projects' ) )">All</a></template>
			<el-table :data="department.modules.tasks.projects" size="small">
				<el-table-column label="Project" min-width="170"><template #default="{ row }"><a class="bd-link" :href="'#/projects/' + row.id">{{ row.name }}</a><div class="bd-muted bd-small">{{ row.client_name || 'Internal' }}</div></template></el-table-column>
				<el-table-column label="Progress" min-width="150"><template #default="{ row }"><div class="bd-prog"><div class="bd-prog__bar"><span :style="{ width: row.progress + '%' }" /></div><div class="bd-muted bd-small">{{ row.total ? row.done + ' of ' + row.total + ' done' : 'No tasks' }}</div></div></template></el-table-column>
				<el-table-column label="Due" min-width="110" align="right"><template #default="{ row }">{{ row.due_date ? fmtDate( row.due_date ) : '—' }}</template></el-table-column>
			</el-table>
		</C>
	</div>
</template>
