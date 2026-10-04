<script setup>
import { Finished, Calendar } from '@element-plus/icons-vue';
import { StatCard, sdk } from '../core';

defineProps( { member: { type: Object, default: null } } );
</script>

<template>
	<template v-if="member">
		<el-col :xs="12" :md="6"><StatCard :label="'Open ' + sdk.tp( 'task' ).toLowerCase()" :icon="Finished" :tone="member.overdue_tasks ? 'danger' : ''" :sub="member.overdue_tasks ? member.overdue_tasks + ' overdue' : 'Nothing overdue'"><span class="bd-stat__text">{{ member.open_tasks }}</span></StatCard></el-col>
		<el-col :xs="12" :md="6"><StatCard :label="'Active ' + sdk.tp( 'project' ).toLowerCase()" :icon="Calendar" sub="With open tasks"><span class="bd-stat__text">{{ member.active_projects }}</span></StatCard></el-col>
	</template>
</template>
