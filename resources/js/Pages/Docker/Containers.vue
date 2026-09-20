<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';

const containers = ref([]);
const search = ref('');
const stateFilter = ref('all');
const loading = ref(true);
const refreshing = ref(false);
const error = ref(null);
const actionId = ref(null);
const showCreate = ref(false);
const creating = ref(false);
const createError = ref(null);
const images = ref([]);
const imagesLoading = ref(false);
const newContainer = ref({ name: '', image: '', command: '' });
let refreshTimer;

const page = usePage();
const canRestart = computed(() => ['support', 'admin', 'dev'].includes(page.props.auth.user.role));
const isDev = computed(() => page.props.auth.user.role === 'dev');
const filteredContainers = computed(() => containers.value.filter((container) => {
    const query = search.value.toLowerCase();
    const matchesSearch = `${container.name} ${container.image} ${container.id}`.toLowerCase().includes(query);
    const matchesState = stateFilter.value === 'all' || container.state === stateFilter.value;

    return matchesSearch && matchesState;
}));
const runningCount = computed(() => containers.value.filter((container) => container.state === 'running').length);
const stoppedCount = computed(() => containers.value.length - runningCount.value);

const errorMessage = (exception, fallback) => {
    const status = exception.response?.status;

    if (status === 401) return 'Session หมดอายุ กรุณาเข้าสู่ระบบใหม่';
    if (status === 403) return 'บัญชีนี้ไม่มีสิทธิ์ดำเนินการกับ container';
    if (status === 503) return 'Docker daemon ไม่พร้อมใช้งาน';

    return exception.response?.data?.message ?? fallback;
};

const loadContainers = async (silent = false) => {
    if (silent) refreshing.value = true;
    error.value = null;

    try {
        const response = await window.axios.get('/api/containers/overview');
        containers.value = response.data.data ?? [];
    } catch (exception) {
        error.value = errorMessage(exception, 'โหลดรายการ container ไม่สำเร็จ');
    } finally {
        loading.value = false;
        refreshing.value = false;
    }
};

const restartContainer = async (container) => {
    if (!window.confirm(`Restart ${container.name} หรือไม่?`)) return;

    actionId.value = container.id;
    error.value = null;

    try {
        await window.axios.post(`/api/containers/${container.id}/restart`);
        await loadContainers(true);
    } catch (exception) {
        error.value = errorMessage(exception, `Restart ${container.name} ไม่สำเร็จ`);
    } finally {
        actionId.value = null;
    }
};

const createContainer = async () => {
    creating.value = true;
    createError.value = null;

    try {
        const payload = { name: newContainer.value.name, image: newContainer.value.image };
        if (newContainer.value.command.trim()) payload.command = newContainer.value.command.trim().split(/\s+/);
        await window.axios.post('/api/containers', payload);
        showCreate.value = false;
        newContainer.value = { name: '', image: '', command: '' };
        await loadContainers(true);
    } catch (exception) {
        createError.value = errorMessage(exception, 'สร้าง container ไม่สำเร็จ');
    } finally {
        creating.value = false;
    }
};

const openCreate = async () => {
    showCreate.value = true;
    imagesLoading.value = true;
    createError.value = null;

    try {
        const response = await window.axios.get('/api/containers/images');
        images.value = response.data.data ?? [];
        if (!newContainer.value.image && images.value[0]?.tags?.[0]) {
            newContainer.value.image = images.value[0].tags[0];
        }
    } catch (exception) {
        images.value = [];
        createError.value = errorMessage(exception, 'โหลดรายการ Docker images ไม่สำเร็จ');
    } finally {
        imagesLoading.value = false;
    }
};

const formatBytes = (value) => {
    if (value === null || value === undefined) return '—';
    if (value < 1024 * 1024) return `${Math.round(value / 1024)} KB`;

    return `${(value / 1024 / 1024).toFixed(1)} MB`;
};

onMounted(() => {
    loadContainers();
    refreshTimer = window.setInterval(() => loadContainers(true), 10000);
});

onBeforeUnmount(() => window.clearInterval(refreshTimer));
</script>

<template>
    <ManagerLayout title="Docker Containers">
        <Head title="Docker Containers" />

        <div class="page-heading">
            <div><div class="eyebrow">Docker infrastructure / live fleet</div><h1>Containers</h1><p>ติดตามสถานะและจัดการ container จากหน้าจอเดียว</p></div>
            <div class="heading-actions"><span class="live-badge"><i /> Live · 10s</span><button class="button button-ghost" :disabled="refreshing" @click="loadContainers(true)">{{ refreshing ? 'กำลัง sync...' : '↻ Refresh' }}</button><button v-if="isDev" class="button button-primary" @click="openCreate">＋ Create container</button></div>
        </div>

        <div v-if="error" class="alert-error">{{ error }}</div>
        <section class="metric-grid">
            <article class="metric-card metric-card--accent"><span class="metric-label">CONTAINERS</span><strong>{{ containers.length }}</strong><small><b>{{ runningCount }}</b> running now</small><div class="metric-spark spark-green" /></article>
            <article class="metric-card"><span class="metric-label">RUNNING</span><strong>{{ runningCount }}</strong><small class="text-green">{{ containers.length ? Math.round(runningCount / containers.length * 100) : 0 }}% of fleet</small><div class="metric-spark spark-blue" /></article>
            <article class="metric-card"><span class="metric-label">STOPPED / OTHER</span><strong>{{ stoppedCount }}</strong><small>{{ stoppedCount ? 'ต้องตรวจสอบ' : 'ทุกอย่างปกติ' }}</small><div class="metric-spark spark-orange" /></article>
            <article class="metric-card"><span class="metric-label">DOCKER STATUS</span><strong class="status-word"><i /> {{ loading ? 'Checking' : 'Online' }}</strong><small>Socket response healthy</small><div class="metric-spark spark-cyan" /></article>
        </section>

        <section class="content-panel">
            <div class="panel-header"><div><h2>Container fleet</h2><p>สถานะและ resource usage แบบปัจจุบัน</p></div><div class="panel-tools"><label class="search-box"><span>⌕</span><input v-model="search" placeholder="ค้นหา container..." /></label><select v-model="stateFilter"><option value="all">ทุกสถานะ</option><option value="running">Running</option><option value="exited">Stopped</option></select></div></div>
            <div v-if="loading" class="empty-state">กำลังเชื่อมต่อ Docker daemon...</div>
            <div v-else-if="!filteredContainers.length" class="empty-state">ไม่พบ container ตามเงื่อนไข</div>
            <div v-else class="table-wrap"><table class="container-table"><thead><tr><th>CONTAINER</th><th>STATUS</th><th>CPU</th><th>MEMORY</th><th>IMAGE</th><th>PORTS / URL</th><th>ACTION</th></tr></thead><tbody>
                <tr v-for="container in filteredContainers" :key="container.id"><td><Link :href="route('docker.containers.show', container.id)" class="container-name"><span class="container-glyph">▦</span><span><b>{{ container.name }}</b><small>{{ container.id.slice(0, 12) }}</small></span></Link></td><td><span :class="['state-pill', container.state === 'running' ? 'state-pill--running' : 'state-pill--stopped']"><i />{{ container.state }}</span><small class="status-detail">{{ container.status }}</small></td><td><div class="usage-cell"><b>{{ container.metrics.cpu_percent === null ? '—' : `${container.metrics.cpu_percent}%` }}</b><span class="usage-track"><i :style="{ width: `${Math.min(container.metrics.cpu_percent ?? 0, 100)}%` }" /></span></div></td><td><b>{{ formatBytes(container.metrics.memory_used) }}</b><small class="status-detail">of {{ formatBytes(container.metrics.memory_limit) }}</small></td><td><span class="image-name">{{ container.image }}</span></td><td><div class="port-list"><a v-for="port in container.ports" :key="`${port.public}-${port.private}-${port.type}`" :href="port.url ?? undefined" :target="port.url ? '_blank' : undefined" rel="noreferrer" class="port-link">{{ port.url ?? `container:${port.private}/${port.type}` }}</a><span v-if="!container.ports?.length" class="action-muted">No published port</span></div></td><td><button v-if="canRestart" class="action-button action-button--restart" :disabled="actionId === container.id" title="Restart container" @click="restartContainer(container)">{{ actionId === container.id ? '...' : '↻ Restart' }}</button><span v-else class="action-muted">Read only</span></td></tr>
            </tbody></table></div>
        </section>
        <div v-if="showCreate" class="modal-backdrop" @click.self="showCreate = false"><form class="create-modal" @submit.prevent="createContainer"><div class="modal-kicker">DEV ONLY / DOCKER ENGINE</div><h2>Create container</h2><p>เลือก image ที่มีอยู่จริงจาก Docker Engine</p><label>ชื่อ container<input v-model="newContainer.name" required pattern="[a-zA-Z0-9][a-zA-Z0-9_.-]*" placeholder="mt5-terminal-01" /></label><label>Image<select v-model="newContainer.image" required :disabled="imagesLoading || !images.length"><option value="" disabled>{{ imagesLoading ? 'กำลังโหลด Docker images...' : 'เลือก image' }}</option><template v-for="image in images" :key="image.id"><option v-for="tag in image.tags" :key="tag" :value="tag">{{ tag }}</option></template></select></label><p v-if="!imagesLoading && !images.length" class="form-error">ไม่พบ image ใน Docker Engine กรุณา pull image ก่อน</p><label>Command <span class="field-hint">คั่นด้วยช่องว่าง</span><input v-model="newContainer.command" placeholder="php artisan serve" /></label><div v-if="createError" class="form-error">{{ createError }}</div><div class="modal-actions"><button type="button" class="button button-ghost" @click="showCreate = false">ยกเลิก</button><button class="button button-primary" :disabled="creating || imagesLoading || !images.length">{{ creating ? 'กำลังสร้าง...' : 'Create container' }}</button></div></form></div>
    </ManagerLayout>
</template>