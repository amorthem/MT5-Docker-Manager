<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import DialogModal from "@/Components/DialogModal.vue";
import ManagerLayout from "@/Layouts/ManagerLayout.vue";

const containers = ref([]);
const search = ref("");
const stateFilter = ref("all");
const loading = ref(true);
const refreshing = ref(false);
const error = ref(null);
const actionId = ref(null);
const showCreate = ref(false);
const showResources = ref(false);
const creating = ref(false);
const updatingResources = ref(false);
const createError = ref(null);
const resourcesError = ref(null);
const images = ref([]);
const imagesLoading = ref(false);
const newContainer = ref({
    name: "",
    image: "",
    command: "",
    cpus: "0.8",
    memory: "1.2g",
    memory_reservation: "512m",
    restart: "unless-stopped",
    vnc_port: "",
    api_port: "",
    mt5_login: "",
    mt5_password: "",
    mt5_server: "",
    vnc_user: "",
    vnc_password: "",
    api_key_seed: "",
    app_volume: "",
    experts_volume: "",
});
const resourceForm = ref({
    id: "",
    name: "",
    cpus: "0.8",
    memory: "1g",
    memory_reservation: "512m",
    restart: "unless-stopped",
});
let refreshTimer;

const page = usePage();
const canRestart = computed(() =>
    ["support", "admin", "dev"].includes(page.props.auth.user.role),
);
const isDev = computed(() => page.props.auth.user.role === "dev");
const filteredContainers = computed(() =>
    containers.value.filter((container) => {
        const query = search.value.toLowerCase();
        const matchesSearch =
            `${container.name} ${container.image} ${container.id}`
                .toLowerCase()
                .includes(query);
        const matchesState =
            stateFilter.value === "all" ||
            container.state === stateFilter.value;

        return matchesSearch && matchesState;
    }),
);
const runningCount = computed(
    () =>
        containers.value.filter((container) => container.state === "running")
            .length,
);
const stoppedCount = computed(
    () => containers.value.length - runningCount.value,
);

const errorMessage = (exception, fallback) => {
    const status = exception.response?.status;

    if (status === 401) return "Session หมดอายุ กรุณาเข้าสู่ระบบใหม่";
    if (status === 403) return "บัญชีนี้ไม่มีสิทธิ์ดำเนินการกับ container";
    if (status === 503) return "Docker daemon ไม่พร้อมใช้งาน";

    return exception.response?.data?.message ?? fallback;
};

const loadContainers = async (silent = false) => {
    if (silent) refreshing.value = true;
    error.value = null;

    try {
        const response = await window.axios.get(
            "/docker-containers/data/overview",
        );
        containers.value = response.data.data ?? [];
    } catch (exception) {
        error.value = errorMessage(exception, "โหลดรายการ container ไม่สำเร็จ");
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
        await window.axios.post(
            `/docker-containers/data/${container.id}/restart`,
        );
        await loadContainers(true);
    } catch (exception) {
        error.value = errorMessage(
            exception,
            `Restart ${container.name} ไม่สำเร็จ`,
        );
    } finally {
        actionId.value = null;
    }
};

const removeContainer = async (container) => {
    if (!window.confirm(`ลบ ${container.name} ออกจาก Docker หรือไม่?`)) return;

    actionId.value = container.id;
    error.value = null;
    try {
        await window.axios.delete(`/docker-containers/data/${container.id}`);
        await loadContainers(true);
    } catch (exception) {
        error.value = errorMessage(exception, `ลบ ${container.name} ไม่สำเร็จ`);
    } finally {
        actionId.value = null;
    }
};

const openResources = async (container) => {
    resourcesError.value = null;
    try {
        const response = await window.axios.get(
            `/docker-containers/data/${container.id}`,
        );
        const host = response.data.data.HostConfig ?? {};
        resourceForm.value = {
            id: container.id,
            name: container.name,
            cpus: host.NanoCpus
                ? (host.NanoCpus / 1_000_000_000).toString()
                : "0.8",
            memory: host.Memory
                ? `${(host.Memory / 1024 / 1024).toFixed(0)}m`
                : "1g",
            memory_reservation: host.MemoryReservation
                ? `${(host.MemoryReservation / 1024 / 1024).toFixed(0)}m`
                : "512m",
            restart: host.RestartPolicy?.Name || "unless-stopped",
        };
        showResources.value = true;
    } catch (exception) {
        error.value = errorMessage(
            exception,
            "อ่าน resource ของ container ไม่สำเร็จ",
        );
    }
};

const updateResources = async () => {
    updatingResources.value = true;
    resourcesError.value = null;
    try {
        await window.axios.put(
            `/docker-containers/data/${resourceForm.value.id}/resources`,
            resourceForm.value,
        );
        showResources.value = false;
        await loadContainers(true);
    } catch (exception) {
        resourcesError.value = errorMessage(
            exception,
            "ปรับ resource ไม่สำเร็จ",
        );
    } finally {
        updatingResources.value = false;
    }
};

const createContainer = async () => {
    creating.value = true;
    createError.value = null;

    try {
        const payload = { ...newContainer.value };
        Object.keys(payload).forEach((key) => {
            if (payload[key] === "") delete payload[key];
        });
        await window.axios.post("/docker-containers/data", payload);
        showCreate.value = false;
        newContainer.value = {
            name: "",
            image: "",
            command: "",
            cpus: "0.8",
            memory: "1.2g",
            memory_reservation: "512m",
            restart: "unless-stopped",
            vnc_port: "",
            api_port: "",
            mt5_login: "",
            mt5_password: "",
            mt5_server: "",
            vnc_user: "",
            vnc_password: "",
            api_key_seed: "",
            app_volume: "",
            experts_volume: "",
        };
        await loadContainers(true);
    } catch (exception) {
        createError.value = errorMessage(
            exception,
            "สร้าง container ไม่สำเร็จ",
        );
    } finally {
        creating.value = false;
    }
};

const openCreate = async () => {
    showCreate.value = true;
    imagesLoading.value = true;
    createError.value = null;

    try {
        const response = await window.axios.get(
            "/docker-containers/data/images/list",
        );
        images.value = response.data.data ?? [];
        if (!newContainer.value.image && images.value[0]?.tags?.[0]) {
            newContainer.value.image = images.value[0].tags[0];
        }
    } catch (exception) {
        images.value = [];
        createError.value = errorMessage(
            exception,
            "โหลดรายการ Docker images ไม่สำเร็จ",
        );
    } finally {
        imagesLoading.value = false;
    }
};

const formatBytes = (value) => {
    if (value === null || value === undefined) return "—";
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
            <div>
                <div class="eyebrow">Docker infrastructure / live fleet</div>
                <h1>Containers</h1>
                <p>ติดตามสถานะและจัดการ container จากหน้าจอเดียว</p>
            </div>
            <div class="heading-actions">
                <span class="live-badge"><i /> Live · 10s</span
                ><button
                    class="button button-ghost"
                    :disabled="refreshing"
                    @click="loadContainers(true)"
                >
                    {{ refreshing ? "กำลัง sync..." : "↻ Refresh" }}</button
                ><button
                    v-if="isDev"
                    class="button button-primary"
                    @click="openCreate"
                >
                    ＋ Create container
                </button>
            </div>
        </div>

        <div v-if="error" class="alert-error">{{ error }}</div>
        <section class="metric-grid">
            <article class="metric-card metric-card--accent">
                <span class="metric-label">CONTAINERS</span
                ><strong>{{ containers.length }}</strong
                ><small
                    ><b>{{ runningCount }}</b> running now</small
                >
                <div class="metric-spark spark-green" />
            </article>
            <article class="metric-card">
                <span class="metric-label">RUNNING</span
                ><strong>{{ runningCount }}</strong
                ><small class="text-green"
                    >{{
                        containers.length
                            ? Math.round(
                                  (runningCount / containers.length) * 100,
                              )
                            : 0
                    }}% of fleet</small
                >
                <div class="metric-spark spark-blue" />
            </article>
            <article class="metric-card">
                <span class="metric-label">STOPPED / OTHER</span
                ><strong>{{ stoppedCount }}</strong
                ><small>{{
                    stoppedCount ? "ต้องตรวจสอบ" : "ทุกอย่างปกติ"
                }}</small>
                <div class="metric-spark spark-orange" />
            </article>
            <article class="metric-card">
                <span class="metric-label">DOCKER STATUS</span
                ><strong class="status-word"
                    ><i /> {{ loading ? "Checking" : "Online" }}</strong
                ><small>Socket response healthy</small>
                <div class="metric-spark spark-cyan" />
            </article>
        </section>

        <section class="content-panel">
            <div class="panel-header">
                <div>
                    <h2>Container fleet</h2>
                    <p>สถานะและ resource usage แบบปัจจุบัน</p>
                </div>
                <div class="panel-tools">
                    <label class="search-box">
                        <span>⌕</span>
                        <input
                            v-model="search"
                            placeholder="ค้นหา container..."
                        />
                    </label>
                    <select v-model="stateFilter">
                        <option value="all">ทุกสถานะ</option>
                        <option value="running">Running</option>
                        <option value="exited">Stopped</option>
                    </select>
                </div>
            </div>
            <div v-if="loading" class="empty-state">
                กำลังเชื่อมต่อ Docker daemon...
            </div>
            <div v-else-if="!filteredContainers.length" class="empty-state">
                ไม่พบ container ตามเงื่อนไข
            </div>
            <div v-else class="table-wrap">
                <table class="container-table">
                    <thead>
                        <tr>
                            <th>CONTAINER</th>
                            <th>STATUS</th>
                            <th>CPU</th>
                            <th>MEMORY</th>
                            <th>IMAGE</th>
                            <th>PORTS / URL</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="container in filteredContainers"
                            :key="container.id"
                        >
                            <td>
                                <Link
                                    :href="
                                        route(
                                            'docker.containers.show',
                                            container.id,
                                        )
                                    "
                                    class="container-name"
                                    ><span class="container-glyph">▦</span
                                    ><span
                                        ><b>{{ container.name }}</b
                                        ><small>{{
                                            container.id.slice(0, 12)
                                        }}</small></span
                                    ></Link
                                >
                            </td>
                            <td>
                                <span
                                    :class="[
                                        'state-pill',
                                        container.state === 'running'
                                            ? 'state-pill--running'
                                            : 'state-pill--stopped',
                                    ]"
                                    ><i />{{ container.state }}</span
                                ><small class="status-detail">{{
                                    container.status
                                }}</small>
                            </td>
                            <td>
                                <div class="usage-cell">
                                    <b>{{
                                        container.metrics.cpu_percent === null
                                            ? "—"
                                            : `${container.metrics.cpu_percent}%`
                                    }}</b
                                    ><span class="usage-track"
                                        ><i
                                            :style="{
                                                width: `${Math.min(container.metrics.cpu_percent ?? 0, 100)}%`,
                                            }"
                                    /></span>
                                </div>
                            </td>
                            <td>
                                <div class="memory-cell">
                                    <b>{{
                                        formatBytes(
                                            container.metrics.memory_used,
                                        )
                                    }}</b
                                    ><small class="status-detail"
                                        >of
                                        {{
                                            formatBytes(
                                                container.metrics.memory_limit,
                                            )
                                        }}</small
                                    ><span class="memory-track"
                                        ><i
                                            :style="{
                                                width: `${Math.min(container.metrics.memory_percent ?? 0, 100)}%`,
                                            }" /></span
                                    ><small class="memory-percent">{{
                                        container.metrics.memory_percent ===
                                        null
                                            ? "—"
                                            : `${container.metrics.memory_percent}% used`
                                    }}</small>
                                </div>
                            </td>
                            <td>
                                <span class="image-name">{{
                                    container.image
                                }}</span>
                            </td>
                            <td>
                                <div class="port-list">
                                    <a
                                        v-for="port in container.ports"
                                        :key="`${port.public}-${port.private}-${port.type}`"
                                        :href="port.url ?? undefined"
                                        :target="
                                            port.url ? '_blank' : undefined
                                        "
                                        rel="noreferrer"
                                        class="port-link"
                                        >{{
                                            port.url ??
                                            `container:${port.private}/${port.type}`
                                        }}</a
                                    ><span
                                        v-if="!container.ports?.length"
                                        class="action-muted"
                                        >No published port</span
                                    >
                                </div>
                            </td>
                            <td>
                                <button
                                    v-if="canRestart"
                                    class="action-button action-button--restart"
                                    :disabled="actionId === container.id"
                                    title="Restart container"
                                    @click="restartContainer(container)"
                                >
                                    {{
                                        actionId === container.id
                                            ? "..."
                                            : "↻ Restart"
                                    }}</button
                                ><button
                                    v-if="isDev"
                                    class="action-button"
                                    :disabled="updatingResources"
                                    title="Edit CPU/RAM"
                                    @click="openResources(container)"
                                >
                                    ⚙ Resources</button
                                ><button
                                    v-if="isDev"
                                    class="action-button danger-action"
                                    :disabled="actionId === container.id"
                                    title="Delete container"
                                    @click="removeContainer(container)"
                                >
                                    × Delete</button
                                ><span v-if="!canRestart" class="action-muted"
                                    >Read only</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <DialogModal
            v-if="showCreate"
            :show="showCreate"
            max-width="4xl"
            @close="showCreate = false"
        >
            <form
                class="dialog-form dialog-form--wide"
                @submit.prevent="createContainer"
            >
                <div class="modal-kicker">DEV ONLY / DOCKER ENGINE</div>
                <h2>Create container</h2>
                <p>
                    กำหนด resource, ports, environment และ volumes สำหรับ Docker
                    container
                </p>
                <div class="form-grid">
                    <label
                        >ชื่อ container<input
                            v-model="newContainer.name"
                            required
                            pattern="[a-zA-Z0-9][a-zA-Z0-9_.-]*"
                            placeholder="mt5-terminal-01" /></label
                    ><label
                        >Image<select
                            v-model="newContainer.image"
                            required
                            :disabled="imagesLoading || !images.length"
                        >
                            <option value="" disabled>
                                {{
                                    imagesLoading
                                        ? "กำลังโหลด Docker images..."
                                        : "เลือก image"
                                }}
                            </option>
                            <template v-for="image in images" :key="image.id"
                                ><option
                                    v-for="tag in image.tags"
                                    :key="tag"
                                    :value="tag"
                                >
                                    {{ tag }}
                                </option></template
                            >
                        </select></label
                    ><label
                        >Command<input
                            v-model="newContainer.command"
                            placeholder="เช่น /start.sh" /></label
                    ><label
                        >CPU limit<input
                            v-model="newContainer.cpus"
                            placeholder="0.8" /></label
                    ><label
                        >Memory limit<input
                            v-model="newContainer.memory"
                            placeholder="1.2g" /></label
                    ><label
                        >Memory reservation<input
                            v-model="newContainer.memory_reservation"
                            placeholder="512m" /></label
                    ><label
                        >Restart policy<select v-model="newContainer.restart">
                            <option value="unless-stopped">
                                unless-stopped
                            </option>
                            <option value="always">always</option>
                            <option value="on-failure">on-failure</option>
                            <option value="no">no</option>
                        </select></label
                    ><label
                        >VNC host port<input
                            v-model="newContainer.vnc_port"
                            type="number"
                            placeholder="เช่น 6901" /></label
                    ><label
                        >API host port<input
                            v-model="newContainer.api_port"
                            type="number"
                            placeholder="เช่น 8000" /></label
                    ><label
                        >MT5 login<input
                            v-model="newContainer.mt5_login" /></label
                    ><label
                        >MT5 password<input
                            v-model="newContainer.mt5_password"
                            type="password" /></label
                    ><label
                        >MT5 server<input
                            v-model="newContainer.mt5_server" /></label
                    ><label
                        >VNC user<input
                            v-model="newContainer.vnc_user" /></label
                    ><label
                        >VNC password<input
                            v-model="newContainer.vnc_password"
                            type="password" /></label
                    ><label
                        >API key seed<input
                            v-model="newContainer.api_key_seed" /></label
                    ><label
                        >App host path<input
                            v-model="newContainer.app_volume"
                            placeholder="/srv/mt5/app" /></label
                    ><label
                        >Experts host path<input
                            v-model="newContainer.experts_volume"
                            placeholder="/srv/mt5/EA"
                    /></label>
                </div>
                <p v-if="!imagesLoading && !images.length" class="form-error">
                    ไม่พบ image ใน Docker Engine กรุณา pull image ก่อน
                </p>
                <div v-if="createError" class="form-error">
                    {{ createError }}
                </div>
                <div class="modal-actions">
                    <button
                        type="button"
                        class="button button-ghost"
                        @click="showCreate = false"
                    >
                        ยกเลิก</button
                    ><button
                        class="button button-primary"
                        :disabled="creating || imagesLoading || !images.length"
                    >
                        {{ creating ? "กำลังสร้าง..." : "Create container" }}
                    </button>
                </div>
            </form>
        </DialogModal>
        <DialogModal
            v-if="showResources"
            :show="showResources"
            @close="showResources = false"
        >
            <form class="dialog-form" @submit.prevent="updateResources">
                <div class="modal-kicker">DOCKER HOST CONFIG</div>
                <h2>ปรับ resource</h2>
                <p>{{ resourceForm.name }}</p>
                <label
                    >CPU limit<input
                        v-model="resourceForm.cpus"
                        required
                        placeholder="0.8" /></label
                ><label
                    >Memory limit<input
                        v-model="resourceForm.memory"
                        required
                        placeholder="1.2g" /></label
                ><label
                    >Memory reservation<input
                        v-model="resourceForm.memory_reservation"
                        required
                        placeholder="512m" /></label
                ><label
                    >Restart policy<select v-model="resourceForm.restart">
                        <option value="unless-stopped">unless-stopped</option>
                        <option value="always">always</option>
                        <option value="on-failure">on-failure</option>
                        <option value="no">no</option>
                    </select></label
                >
                <div v-if="resourcesError" class="form-error">
                    {{ resourcesError }}
                </div>
                <div class="modal-actions">
                    <button
                        type="button"
                        class="button button-ghost"
                        @click="showResources = false"
                    >
                        ยกเลิก</button
                    ><button
                        class="button button-primary"
                        :disabled="updatingResources"
                    >
                        {{
                            updatingResources
                                ? "กำลังบันทึก..."
                                : "บันทึก resource"
                        }}
                    </button>
                </div>
            </form>
        </DialogModal>
    </ManagerLayout>
</template>
