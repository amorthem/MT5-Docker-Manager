<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';

const props = defineProps({
    users: { type: Object, required: true },
});

const showForm = ref(false);
const editingUser = ref(null);
const page = usePage();
const isDev = computed(() => page.props.auth.user.role === 'dev');
const canManageUsers = computed(() => ['admin', 'dev'].includes(page.props.auth.user.role));
const form = useForm({ name: '', email: '', password: '', role: 'user' });

const openCreate = () => {
    editingUser.value = null;
    form.reset();
    form.role = 'user';
    showForm.value = true;
};

const openEdit = (user) => {
    editingUser.value = user;
    form.name = user.name;
    form.email = user.email;
    form.password = '';
    form.role = user.role;
    form.clearErrors();
    showForm.value = true;
};

const saveUser = () => {
    const options = { preserveScroll: true, onSuccess: () => { showForm.value = false; } };
    if (editingUser.value) form.put(route('users.update', editingUser.value.id), options);
    else form.post(route('users.store'), options);
};

const deleteUser = (user) => {
    if (!window.confirm(`ลบผู้ใช้ ${user.name} หรือไม่?`)) return;
    useForm({}).delete(route('users.destroy', user.id), { preserveScroll: true });
};
</script>

<template>
    <ManagerLayout title="User Management">
        <Head title="User Management" />
        <div class="page-heading"><div><div class="eyebrow">Access control / directory</div><h1>User management</h1><p>จัดการบัญชีและ role ของทีมผ่าน web controller และ Inertia</p></div><button v-if="canManageUsers" class="button button-primary" @click="openCreate">＋ Add user</button></div>
        <div v-if="page.props.flash?.success" class="alert-success">{{ page.props.flash.success }}</div>
        <section class="content-panel user-panel"><div class="panel-header"><div><h2>Team directory</h2><p>{{ users.total }} accounts</p></div></div><div v-if="!users.data.length" class="empty-state">ยังไม่มีผู้ใช้</div><div v-else class="table-wrap"><table class="container-table user-table"><thead><tr><th>USER</th><th>EMAIL</th><th>ROLE</th><th>ACTION</th></tr></thead><tbody><tr v-for="user in users.data" :key="user.id"><td><strong>{{ user.name }}</strong></td><td>{{ user.email }}</td><td><span class="profile-role">{{ user.role }}</span></td><td><div class="row-actions"><button title="Edit user" @click="openEdit(user)">✎</button><button class="danger-action" title="Delete user" @click="deleteUser(user)">×</button></div></td></tr></tbody></table></div></section>
        <div v-if="showForm" class="modal-backdrop" @click.self="showForm = false"><form class="create-modal" @submit.prevent="saveUser"><div class="modal-kicker">ACCESS / ROLE</div><h2>{{ editingUser ? 'แก้ไขผู้ใช้' : 'เพิ่มผู้ใช้' }}</h2><p>สิทธิ์จะถูกตรวจสอบซ้ำที่ web controller ทุกครั้ง</p><label>ชื่อผู้ใช้<input v-model="form.name" required /><small v-if="form.errors.name" class="form-error">{{ form.errors.name }}</small></label><label>Email<input v-model="form.email" type="email" required /><small v-if="form.errors.email" class="form-error">{{ form.errors.email }}</small></label><label>Password<input v-model="form.password" type="password" :required="!editingUser" minlength="12" placeholder="อย่างน้อย 12 ตัวอักษร" /><small v-if="form.errors.password" class="form-error">{{ form.errors.password }}</small></label><label>Role<select v-model="form.role"><option value="user">user</option><option value="support">support</option><option v-if="isDev" value="admin">admin</option><option v-if="isDev" value="dev">dev</option></select></label><div class="modal-actions"><button type="button" class="button button-ghost" @click="showForm = false">ยกเลิก</button><button class="button button-primary" :disabled="form.processing">{{ form.processing ? 'กำลังบันทึก...' : 'บันทึกผู้ใช้' }}</button></div></form></div>
    </ManagerLayout>
</template>