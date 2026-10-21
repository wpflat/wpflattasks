// WPFlat Tasks runs inside WPFlat Core's page: everything it shares comes from window.WPFlatCore.
const bc = window.WPFlatCore;
export const { sdk, api, errorMessage, store, navigate, util, ui, components } = bc;
export const { ElMessage, ElMessageBox } = ui;
export const { fmtDate, fmtDateTime, avatarStyle, initials, dept, relDays, labelOf, options, humanize, today } = util;
export const { PageHeader, StatCard, ResourceTable, RecordDrawer, RemoteSelect, Badge, StatusTag, SlotOutlet, LinkedRecords, ChartCard, Chart, AnalyticsBar } = components;
