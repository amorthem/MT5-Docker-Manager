<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import VueApexCharts from "vue3-apexcharts";
import ManagerLayout from "@/Layouts/ManagerLayout.vue";

const hostMetrics = ref(null);
let refreshTimer;
const chartOptions = computed(() => ({
  chart: { type: "donut", sparkline: { enabled: true }, animations: { enabled: true } },
  labels: ["Used", "Available"],
  colors: ["#ff866f", "#e7efeb"],
  legend: { show: false },
  dataLabels: { enabled: false },
  stroke: { width: 0 },
  plotOptions: {
    pie: {
      donut: {
        size: "72%",
        labels: {
          show: true,
          total: {
            show: true,
            label: "Used",
            color: "#74808a",
            fontSize: "11px",
            formatter: () => `${(hostMetrics.value?.cpu?.used ?? 0).toFixed(1)}%`,
          },
        },
      },
    },
  },
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
const hostScopeLabel = computed(() =>
  hostMetrics.value?.scope === "docker-vm"
    ? "DOCKER DESKTOP VM RESOURCES"
    : "VPS HOST RESOURCES"
);
const hostScopeDescription = computed(() =>
  hostMetrics.value?.scope === "docker-vm"
    ? "ทรัพยากรของ Linux VM ที่ Docker Desktop จัดสรรให้ "
    : "ทรัพยากรของ VPS/host ไม่รวมการใช้งานของ container รายตัว"
);
const ramChartOptions = computed(() => ({
  ...chartOptions.value,
  plotOptions: {
    pie: {
      donut: {
        size: "72%",
        labels: {
          show: true,
          total: {
            show: true,
            label: "Used",
            color: "#74808a",
            fontSize: "11px",
            formatter: () => `${(hostMetrics.value?.ram?.used ?? 0).toFixed(1)}%`,
          },
        },
      },
    },
  },
}));

const loadHostMetrics = async () => {
  try {
    hostMetrics.value = (
      await window.axios.get("/dashboard/data/metrics/host")
    ).data.data;
  } catch (exception) {
    hostMetrics.value = null;
  }
};

onMounted(() => {
  loadHostMetrics();
  refreshTimer = window.setInterval(loadHostMetrics, 10000);
});
onBeforeUnmount(() => window.clearInterval(refreshTimer));
</script>

<template>
  <ManagerLayout title="Overview">
    <div class="page-heading">
      <div>
        <div class="eyebrow">MT5 infrastructure / live view</div>
        <h1>Good morning, {{ $page.props.auth.user.name.split(" ")[0] }}.</h1>
        <p>เห็นภาพรวม container ของคุณได้ในที่เดียว</p>
      </div>
    </div>

    <section class="host-charts">
      <div class="host-charts-heading">
        <div class="eyebrow">{{ hostScopeLabel }}</div>
        <p>{{ hostScopeDescription }}</p>
      </div>
      <article class="content-panel host-chart-card">
        <div>
          <span class="metric-label">{{
            hostMetrics?.scope === "docker-vm" ? "DOCKER VM CPU USAGE" : "VPS CPU USAGE"
          }}</span>
          <h2>
            {{
              hostMetrics?.cpu?.used === null || hostMetrics?.cpu?.used === undefined
                ? "—"
                : `${hostMetrics.cpu.used.toFixed(1)}%`
            }}
          </h2>
          <p>
            {{
              hostMetrics?.scope === "docker-vm"
                ? "CPU ของ Docker Linux VM"
                : "CPU ของ VPS host"
            }}
          </p>
        </div>
        <VueApexCharts
          type="donut"
          width="170"
          :options="chartOptions"
          :series="cpuSeries"
        />
      </article>
      <article class="content-panel host-chart-card">
        <div>
          <span class="metric-label">{{
            hostMetrics?.scope === "docker-vm" ? "DOCKER VM RAM USAGE" : "VPS RAM USAGE"
          }}</span>
          <h2>
            {{
              hostMetrics?.ram?.used_gb === null ||
              hostMetrics?.ram?.used_gb === undefined
                ? "—"
                : `${hostMetrics.ram.used_gb.toFixed(1)} GB`
            }}
          </h2>
          <p>
            {{
              hostMetrics?.ram?.total_gb === null ||
              hostMetrics?.ram?.total_gb === undefined
                ? "Memory usage unavailable"
                : `${hostMetrics.ram.used_gb.toFixed(
                    1
                  )} GB / ${hostMetrics.ram.total_gb.toFixed(
                    1
                  )} GB · ${hostMetrics.ram.used.toFixed(1)}%`
            }}
          </p>
        </div>
        <VueApexCharts
          type="donut"
          width="170"
          :options="ramChartOptions"
          :series="ramSeries"
        />
      </article>
    </section>
  </ManagerLayout>
</template>
