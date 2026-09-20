<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';

const containers = ref([]);
const search = ref('');
const stateFilter = ref('all');
const loading = ref(true);
const refreshing = ref(false);
const error = ref(null);
const hostMetrics = ref(null);
let refreshTimer;

const filteredContainers = computed(() => containers.value.filter((container) => {
    const matchesSearch = `${container.name} ${container.image}`.toLowerCase().includes(search.value.toLowerCase());
    return matchesSearch && (stateFilter.value === 'all' || container.state === stateFilter.value);
}));
const runningCount = computed(() => containers.value.filter((container) => container.state === 'running').length);
const stoppedCount = computed(() => containers.value.length - runningCount.value);
const chartOptions = computed(() => ({
    chart: { type: 'donut', sparkline: { enabled: true }, animations: { enabled: true } },
    labels: ['Used', 'Available'],
    colors: ['#ff866f', '#e7efeb'],
    legend: { show: false },
    dataLabels: { enabled: false },
    stroke: { width: 0 },
    plotOptions: { pie: { donut: { size: '72%', labels: { show: true, total: { show: true, label: 'Used', color: '#74808a', fontSize: '11px', formatter: () => `${(hostMetrics.value?.cpu?.used ?? 0).toFixed(1)}%` } } } } },
    tooltip: { y: { formatter: (value) => `${value.toFixed(1)}%` } },
}));
const cpuSeries = computed(() => {
    const used = hostMetrics.value?.cpu?.used ?? 0;
    return [used, Math.max(0, 100 - used)];
});
const ramSeries = computed(() => {
    const used = hostMetrics.value?.ram?.used ?? 0;
    return [used, Math.max(0, 100 - used)];
});
const hostScopeLabel = computed(() => hostMetrics.value?.scope === 'docker-vm' ? 'DOCKER DESKTOP VM RESOURCES' : 'VPS HOST RESOURCES');
const hostScopeDescription = computed(() => hostMetrics.value?.scope === 'docker-vm'
    ? 'ทรัพยากรของ Linux VM ที่ Docker Desktop จัดสรรให้ ไม่ใช่ RAM ทั้งหมดของ macOS'
    : 'ทรัพยากรของ VPS/host ไม่รวมการใช้งานของ container รายตัว');
const ramChartOptions = computed(() => ({
    ...chartOptions.value,
    plotOptions: { pie: { donut: { size: '72%', labels: { show: true, total: { show: true, label: 'Used', color: '#74808a', fontSize: '11px', formatter: () => `${(hostMetrics.value?.ram?.used ?? 0).toFixed(1)}%` } } } } },
}));

const loadContainers = async (silent = false) => {
    if (silent) refreshing.value = true;
    error.value = null;
    try {
        const response = await window.axios.get('/dashboard/data/containers/overview');
        containers.value = response.data.data ?? [];
    } catch (exception) {
        error.value = exception.response?.data?.message ?? 'Docker daemon ยังไม่พร้อมใช้งาน';
    } finally {
        loading.value = false;
        refreshing.value = false;
    }
};

const loadHostMetrics = async () => {
    try {
        hostMetrics.value = (await window.axios.get('/dashboard/data/metrics/host')).data.data;
    } catch (exception) {
        hostMetrics.value = null;
    }
};

const formatBytes = (value) => {
    if (value === null || value === undefined) return '—';
    if (value < 1024 * 1024) return `${Math.round(value / 1024)} KB`;
    return `${(value / 1024 / 1024).toFixed(1)} MB`;
};

onMounted(() => { loadContainers(); loadHostMetrics(); refreshTimer = window.setInterval(() => { loadContainers(true); loadHostMetrics(); }, 10000); });
onBeforeUnmount(() => window.clearInterval(refreshTimer));
</script>

<template>
    <ManagerLayout title="Overview">
        <div class="page-heading">
            <div><div class="eyebrow">MT5 infrastructure / live view</div><h1>Good morning, {{ $page.props.auth.user.name.split(' ')[0] }}.</h1><p>เห็นภาพรวม container ของคุณได้ในที่เดียว</p></div>
        </div>

        <div v-if="error" class="alert-error">{{ error }}</div>
        <section class="metric-grid">
            <article class="metric-card metric-card--accent"><span class="metric-label">CONTAINERS</span><strong>{{ containers.length }}</strong><small><b>{{ runningCount }}</b> running now</small><div class="metric-spark spark-green" /></article>
            <article class="metric-card"><span class="metric-label">RUNNING</span><strong>{{ runningCount }}</strong><small class="text-green">{{ containers.length ? Math.round(runningCount / containers.length * 100) : 0 }}% of fleet</small><div class="metric-spark spark-blue" /></article>
            <article class="metric-card"><span class="metric-label">STOPPED / OTHER</span><strong>{{ stoppedCount }}</strong><small>{{ stoppedCount ? 'ต้องตรวจสอบ' : 'ทุกอย่างปกติ' }}</small><div class="metric-spark spark-orange" /></article>
            <article class="metric-card"><span class="metric-label">DOCKER STATUS</span><strong class="status-word"><i /> Online</strong><small>Socket response healthy</small><div class="metric-spark spark-cyan" /></article>
        </section>

        <section class="host-charts">
            <div class="host-charts-heading"><div class="eyebrow">{{ hostScopeLabel }}</div><p>{{ hostScopeDescription }}</p></div>
            <article class="content-panel host-chart-card"><div><span class="metric-label">{{ hostMetrics?.scope === 'docker-vm' ? 'DOCKER VM CPU USAGE' : 'VPS CPU USAGE' }}</span><h2>{{ hostMetrics?.cpu?.used === null || hostMetrics?.cpu?.used === undefined ? '—' : `${hostMetrics.cpu.used.toFixed(1)}%` }}</h2><p>{{ hostMetrics?.scope === 'docker-vm' ? 'CPU ของ Docker Linux VM' : 'CPU ของ VPS host' }}</p></div><VueApexCharts type="donut" width="170" :options="chartOptions" :series="cpuSeries" /></article>
            <article class="content-panel host-chart-card"><div><span class="metric-label">{{ hostMetrics?.scope === 'docker-vm' ? 'DOCKER VM RAM USAGE' : 'VPS RAM USAGE' }}</span><h2>{{ hostMetrics?.ram?.used_gb === null || hostMetrics?.ram?.used_gb === undefined ? '—' : `${hostMetrics.ram.used_gb.toFixed(1)} GB` }}</h2><p>{{ hostMetrics?.ram?.total_gb === null || hostMetrics?.ram?.total_gb === undefined ? 'Memory usage unavailable' : `${hostMetrics.ram.used_gb.toFixed(1)} GB / ${hostMetrics.ram.total_gb.toFixed(1)} GB · ${hostMetrics.ram.used.toFixed(1)}%` }}</p></div><VueApexCharts type="donut" width="170" :options="ramChartOptions" :series="ramSeries" /></article>
        </section>

        <section class="content-panel">
            <div class="panel-header"><div><h2>Container fleet</h2><p>สถานะและ resource usage แบบปัจจุบัน</p></div><div class="panel-tools"><label class="search-box"><span>⌕</span><input v-model="search" placeholder="ค้นหา container..." /></label><select v-model="stateFilter"><option value="all">ทุกสถานะ</option><option value="running">Running</option><option value="exited">Stopped</option></select></div></div>
            <div v-if="loading" class="empty-state">กำลังเชื่อมต่อ Docker daemon...</div><div v-else-if="!filteredContainers.length" class="empty-state">ไม่พบ container ตามเงื่อนไข</div>
            <div v-else class="table-wrap"><table class="container-table"><thead><tr><th>CONTAINER</th><th>STATUS</th><th>CPU</th><th>MEMORY</th><th>IMAGE</th><th /></tr></thead><tbody>
                <tr v-for="container in filteredContainers" :key="container.id"><td><Link :href="route('docker.containers.show', container.id)" class="container-name"><span class="container-glyph">▦</span><span><b>{{ container.name }}</b><small>{{ container.id.slice(0, 12) }}</small></span></Link></td><td><span :class="['state-pill', container.state === 'running' ? 'state-pill--running' : 'state-pill--stopped']"><i />{{ container.state }}</span><small class="status-detail">{{ container.status }}</small></td><td><div class="usage-cell"><b>{{ container.metrics.cpu_percent === null ? '—' : `${container.metrics.cpu_percent}%` }}</b><span class="usage-track"><i :style="{ width: `${Math.min(container.metrics.cpu_percent ?? 0, 100)}%` }" /></span></div></td><td><b>{{ formatBytes(container.metrics.memory_used) }}</b><small class="status-detail">of {{ formatBytes(container.metrics.memory_limit) }}</small></td><td><span class="image-name">{{ container.image }}</span></td><td><div v-if="isDev" class="row-actions"><button title="Restart" @click="runAction(container, 'restart')">↻</button><button class="danger-action" title="Delete" @click="removeContainer(container)">×</button></div></td></tr>
            </tbody></table></div>
        </section>

    </ManagerLayout>
</template>
