<script setup>
import { computed, onMounted, ref } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import ManagerLayout from "@/Layouts/ManagerLayout.vue";

const props = defineProps({ containerId: { type: String, required: true } });
const container = ref(null);
const logs = ref("");
const metrics = ref(null);
const error = ref(null);
const logsError = ref(null);
const metricsError = ref(null);
const loading = ref(true);
const logsLoading = ref(true);
const metricsLoading = ref(true);
const restarting = ref(false);
const archives = ref({});
const selectedDate = ref("");
const selectedFile = ref("");
const page = usePage();
const canRestart = computed(() =>
  ["support", "admin", "dev"].includes(page.props.auth.user.role)
);

const loadContainer = async () => {
  try {
    const response = await window.axios.get(
      `/docker-containers/data/${props.containerId}`
    );
    container.value = response.data.data;
  } catch (exception) {
    error.value = exception.response?.data?.message ?? "โหลดข้อมูล container ไม่สำเร็จ";
  } finally {
    loading.value = false;
  }
};

const loadLogs = async () => {
  logsLoading.value = true;
  logsError.value = null;

  try {
    const query = new URLSearchParams({ tail: "200" });
    if (selectedDate.value && selectedFile.value) {
      query.set("date", selectedDate.value);
      query.set("file", selectedFile.value);
    }
    const response = await window.axios.get(
      `/docker-containers/data/${props.containerId}/logs?${query}`
    );
    logs.value = response.data.data.logs ?? "";
  } catch (exception) {
    logsError.value =
      exception.response?.data?.message ?? "โหลด Docker container logs ไม่สำเร็จ";
  } finally {
    logsLoading.value = false;
  }
};

const loadArchives = async () => {
  try {
    archives.value =
      (
        await window.axios.get(
          `/docker-containers/data/${props.containerId}/logs/archives`
        )
      ).data.data ?? {};
    const dates = Object.keys(archives.value);
    selectedDate.value = dates[0] ?? "";
    selectedFile.value = archives.value[selectedDate.value]?.[0]?.file ?? "";
  } catch (exception) {
    archives.value = {};
  }
};

const selectDate = () => {
  selectedFile.value = archives.value[selectedDate.value]?.[0]?.file ?? "";
  loadLogs();
};

const loadMetrics = async () => {
  metricsLoading.value = true;
  metricsError.value = null;

  try {
    const response = await window.axios.get(
      `/docker-containers/data/${props.containerId}/metrics`
    );
    metrics.value = response.data.data;
  } catch (exception) {
    metricsError.value = exception.response?.data?.message ?? "โหลด metrics ไม่สำเร็จ";
  } finally {
    metricsLoading.value = false;
  }
};

const restartContainer = async () => {
  if (!window.confirm(`Restart ${container.value?.Name ?? props.containerId} หรือไม่?`))
    return;

  restarting.value = true;
  error.value = null;

  try {
    await window.axios.post(`/docker-containers/data/${props.containerId}/restart`);
    await Promise.all([loadContainer(), loadMetrics()]);
  } catch (exception) {
    error.value = exception.response?.data?.message ?? "Restart container ไม่สำเร็จ";
  } finally {
    restarting.value = false;
  }
};

onMounted(async () => {
  await loadArchives();
  await Promise.all([loadContainer(), loadLogs(), loadMetrics()]);
});
</script>

<template>
  <ManagerLayout title="Container Details">
    <Head title="Container Details" />
    <div class="page-heading">
      <div>
        <div class="eyebrow">Docker Engine / container detail</div>
        <h1>{{ container?.Name?.replace("/", "") ?? props.containerId.slice(0, 12) }}</h1>
        <p>{{ container?.Config?.Image ?? "กำลังโหลด container..." }}</p>
      </div>
      <div class="heading-actions">
        <Link class="button button-ghost" :href="route('docker.containers.index')"
          >← Containers</Link
        ><button
          v-if="canRestart"
          class="button button-primary"
          :disabled="restarting"
          @click="restartContainer"
        >
          {{ restarting ? "กำลัง restart..." : "↻ Restart" }}
        </button>
      </div>
    </div>

    <div v-if="error" class="alert-error">{{ error }}</div>
    <section class="container-detail-grid">
      <article class="content-panel detail-summary">
        <div class="panel-header">
          <div>
            <h2>Container status</h2>
            <p>ข้อมูลจาก Docker Engine API</p>
          </div>
          <span
            :class="[
              'state-pill',
              container?.State?.Running ? 'state-pill--running' : 'state-pill--stopped',
            ]"
            ><i />{{ container?.State?.Status ?? "loading" }}</span
          >
        </div>
        <div class="detail-facts">
          <div>
            <span>IMAGE</span><strong>{{ container?.Config?.Image ?? "—" }}</strong>
          </div>
          <div>
            <span>CONTAINER ID</span><strong>{{ props.containerId }}</strong>
          </div>
          <div>
            <span>STARTED</span><strong>{{ container?.State?.StartedAt ?? "—" }}</strong>
          </div>
        </div>
      </article>
      <article class="content-panel logs-panel">
        <div class="panel-header">
          <div>
            <h2>Docker container logs</h2>
            <p>อ่านจาก archive file · tail 200 lines</p>
          </div>
          <div class="heading-actions">
            <select v-model="selectedDate" class="log-select" @change="selectDate">
              <option value="">ทุกวันที่ / ล่าสุด</option>
              <option v-for="date in Object.keys(archives)" :key="date" :value="date">
                {{ date }}
              </option></select
            ><select
              v-model="selectedFile"
              class="log-select"
              :disabled="!selectedDate"
              @change="loadLogs"
            >
              <option value="">ไฟล์ล่าสุด</option>
              <option
                v-for="file in archives[selectedDate] ?? []"
                :key="file.file"
                :value="file.file"
              >
                {{ file.file }} ({{ Math.ceil(file.size / 1024) }} KB)
              </option></select
            ><button
              class="button button-ghost"
              :disabled="logsLoading"
              @click="loadLogs"
            >
              {{ logsLoading ? "กำลังโหลด..." : "↻ Refresh logs" }}
            </button>
          </div>
        </div>
        <div v-if="logsError" class="detail-error">{{ logsError }}</div>
        <div v-else-if="logsLoading" class="empty-state">
          กำลังอ่าน logs จาก archive...
        </div>
        <pre v-else class="docker-log-output">{{
          logs || "Container นี้ยังไม่มี Docker logs"
        }}</pre>
      </article>
      <article class="content-panel">
        <div class="panel-header">
          <div>
            <h2>Resource metrics</h2>
            <p>ข้อมูล usage จาก Docker stats API</p>
          </div>
        </div>
        <div v-if="metricsError" class="detail-error">{{ metricsError }}</div>
        <pre v-else-if="metricsLoading" class="detail-code">กำลังโหลด metrics...</pre>
        <pre v-else class="detail-code">{{
          JSON.stringify(metrics?.stats, null, 2)
        }}</pre>
      </article>
    </section>
  </ManagerLayout>
</template>
