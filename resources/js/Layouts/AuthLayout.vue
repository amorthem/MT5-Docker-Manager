<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({ title: { type: String, default: 'Welcome back' }, eyebrow: { type: String, default: 'MT5 infrastructure MANAGER' } });
</script>

<template>
    <div class="auth-shell">
        <Head :title="title" />
        <div class="auth-visual">
            <Link :href="route('login')" class="brand-mark auth-brand"><span class="brand-mark__dot" /><span>MT5 <b>MANAGER</b></span></Link>
            <div class="auth-visual__copy"><span class="eyebrow">{{ eyebrow }}</span><h1>Operate your<br /><em>world</em> with clarity.</h1><p>Monitor every terminal, every signal, and every resource from one calm control room.</p></div>
            <div class="auth-grid-lines" /><div class="auth-orbit auth-orbit--one" /><div class="auth-orbit auth-orbit--two" />
            <div class="auth-visual__footer"><span>MT5 DOCKER MANAGER</span><span>SECURE OPERATIONS / 2026</span></div>
        </div>
        <main class="auth-form-side"><div class="auth-mobile-brand"><span class="brand-mark__dot" /> MT5 <b>MANAGER</b></div><div class="auth-form-wrap"><div class="auth-heading"><span class="eyebrow">{{ eyebrow }}</span><h2>{{ title }}</h2></div><slot /></div></main>
    </div>
</template>