// BizyTasks runs inside BizyCore's page: everything it shares comes from window.BizyCore.
const bc = window.BizyCore;
export const { sdk, api, errorMessage, store, navigate, util, ui, components } = bc;
export const { ElMessage, ElMessageBox } = ui;
export const { fmtDate, fmtDateTime, avatarStyle, initials, dept, relDays, labelOf, options, humanize, today } = util;
export const { PageHeader, StatCard, ResourceTable, RecordDrawer, RemoteSelect, Badge, StatusTag, SlotOutlet, LinkedRecords, ChartCard, Chart, AnalyticsBar } = components;
