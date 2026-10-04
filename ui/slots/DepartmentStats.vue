<script setup>
import { StatCard, sdk } from '../core';

const props = defineProps( { department: { type: Object, default: () => ( {} ) }, departmentId: { type: Number, default: 0 }, q: { type: Function, default: ( x ) => x } } );
</script>

<template>
	<el-col v-if="department.modules && department.modules.tasks" :xs="12" :md="6">
		<StatCard :label="'Open ' + sdk.tp( 'task' ).toLowerCase()" :sub="department.modules.tasks.overdue ? department.modules.tasks.overdue + ' overdue' : department.modules.tasks.done + ' done in period'" :tone="department.modules.tasks.overdue ? 'warning' : ''" :to="q( '/tasks/all' )">{{ department.modules.tasks.open }}</StatCard>
	</el-col>
</template>
