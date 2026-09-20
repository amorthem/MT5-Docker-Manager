<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({ title: { type: String, default: 'trol' } });
const open = ref(false);
const logout = () => router.post(route('logout'));
</script>

<template>
    <div class="manager-shell">
        <Head :title="title" />
        <aside class="manager-sidebar" :class="{ 'is-open': open }">
            <Link :href="route('dashboard')" class="brand-mark"><span class="brand-mark__dot" /><span>MT5 <b>MANAGER</b></span></Link>
            <div class="sidebar-label">Workspace</div>
            <nav class="sidebar-nav">
                <Link :href="route('dashboard')" :class="['sidebar-link', { active: route().current('dashboard') }]"> <span class="nav-icon">⌁</span> Overview </Link>
                <Link :href="route('docker.containers.index')" :class="['sidebar-link', { active: route().current('docker.containers.*') }]"> <span class="nav-icon">▦</span> Containers </Link>
                <Link v-if="$page.props.auth.user.role === 'dev'" :href="route('docker.images.index')" :class="['sidebar-link', { active: route().current('docker.images.*') }]"> <span class="nav-icon">◈</span> Docker Images </Link>
                <Link v-if="['admin', 'dev'].includes($page.props.auth.user.role)" :href="route('users.index')" :class="['sidebar-link', { active: route().current('users.*') }]"> <span class="nav-icon">◎</span> Access & Users </Link>
            </nav>
            <div class="sidebar-footer">
                <div class="connection-pill"><span /> Docker daemon connected</div>
                <div class="account-row"><div class="avatar">{{ $page.props.auth.user.name?.slice(0, 1).toUpperCase() }}</div><div class="account-copy"><strong>{{ $page.props.auth.user.name }}</strong><small>{{ $page.props.auth.user.role }}</small></div><Link class="logout-button" title="Profile" :href="route('profile.show')">⚙</Link><button class="logout-button" title="Log out" @click="logout">↗</button></div>
            </div>
        </aside>
        <div v-if="open" class="sidebar-backdrop" @click="open = false" />
        <main class="manager-main"><header class="mobile-header"><button class="menu-button" aria-label="Open navigation" @click="open = true">☰</button><span class="mobile-brand">MT5 <b>MANAGER</b></span></header><div class="manager-content"><slot /></div></main>
    </div>
</template>