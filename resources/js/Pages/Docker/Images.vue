<script setup>
import { computed, onMounted, ref } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import ManagerLayout from "@/Layouts/ManagerLayout.vue";

const images = ref([]);
const loading = ref(true);
const busy = ref(false);
const error = ref(null);
const deletingImage = ref("");
const page = usePage();
const isDev = computed(() => page.props.auth.user.role === "dev");

const errorMessage = (exception, fallback) =>
  exception.response?.data?.message ?? fallback;

const loadImages = async () => {
  loading.value = true;
  try {
    images.value =
      (await window.axios.get("/docker-containers/data/images/list")).data.data ?? [];
  } catch (exception) {
    error.value = errorMessage(exception, "โหลดรายการ Docker images ไม่สำเร็จ");
  } finally {
    loading.value = false;
  }
};

const removeImage = async (image) => {
  const reference = image.tags?.[0] ?? image.id;
  if (!window.confirm(`ลบ image ${reference} หรือไม่?`)) return;

  busy.value = true;
  deletingImage.value = reference;
  error.value = null;
  try {
    await window.axios.delete(
      `/docker-containers/data/images/${encodeURIComponent(reference)}`
    );
    await loadImages();
  } catch (exception) {
    error.value = errorMessage(exception, "ลบ Docker image ไม่สำเร็จ");
  } finally {
    busy.value = false;
    deletingImage.value = "";
  }
};

const formatBytes = (value) => {
  if (!value) return "—";
  if (value >= 1024 ** 3) return `${(value / 1024 ** 3).toFixed(1)} GB`;
  return `${(value / 1024 ** 2).toFixed(1)} MB`;
};

onMounted(loadImages);
</script>

<template>
  <ManagerLayout title="Docker Images">
    <Head title="Docker Images" />
    <div class="page-heading">
      <div>
        <div class="eyebrow">Docker Engine / image registry</div>
        <h1>Docker Images</h1>
        <p>ตรวจสอบและจัดการ image สำหรับสร้าง MT5 containers</p>
      </div>
    </div>

    <div v-if="error" class="alert-error">{{ error }}</div>
    <section class="content-panel">
      <div class="panel-header">
        <div>
          <h2>Available images</h2>
          <p>{{ images.length }} images on this Docker Engine</p>
        </div>
        <button
          class="button button-ghost"
          :disabled="loading || busy"
          @click="loadImages"
        >
          ↻ Refresh
        </button>
      </div>
      <div v-if="loading" class="empty-state">กำลังโหลด Docker images...</div>
      <div v-else-if="!images.length" class="empty-state">ยังไม่มี Docker image</div>
      <div v-else class="table-wrap">
        <table class="container-table">
          <thead>
            <tr>
              <th>IMAGE</th>
              <th>TAGS</th>
              <th>SIZE</th>
              <th>CREATED</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="image in images" :key="image.id">
              <td>
                <b>{{ image.tags?.[0] ?? image.id }}</b
                ><small class="status-detail">{{ image.id }}</small>
              </td>
              <td>{{ image.tags?.join(", ") || "untagged" }}</td>
              <td>{{ formatBytes(image.size) }}</td>
              <td>
                {{
                  image.created ? new Date(image.created * 1000).toLocaleString() : "—"
                }}
              </td>
              <td>
                <button
                  v-if="isDev"
                  class="button button-danger"
                  :disabled="busy"
                  @click="removeImage(image)"
                >
                  <span
                    v-if="deletingImage === (image.tags?.[0] ?? image.id)"
                    class="loading-spinner"
                    aria-hidden="true"
                  />{{
                    deletingImage === (image.tags?.[0] ?? image.id)
                      ? "Deleting..."
                      : "Delete"
                  }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </ManagerLayout>
</template>
