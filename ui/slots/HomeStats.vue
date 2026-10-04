<script setup>
import { Finished } from '@element-plus/icons-vue';
import { StatCard, sdk } from '../core';

defineProps( { home: { type: Object, default: null } } );
</script>

<template>
	<el-col v-if="home && home.stats.open_tasks !== undefined" :xs="12" :md="6">
		<StatCard :label="'Open ' + sdk.tp( 'task' ).toLowerCase()" :icon="Finished" :tone="home.stats.tasks_overdue ? 'danger' : ''" :sub="home.stats.tasks_overdue ? home.stats.tasks_overdue + ' overdue' : 'Nothing overdue'" to="/tasks/all"><span class="bd-stat__text">{{ home.stats.open_tasks }}</span></StatCard>
	</el-col>
</template>
