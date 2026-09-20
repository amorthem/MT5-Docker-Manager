<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

    <AuthLayout title="Welcome back" eyebrow="Secure sign in">
        <div v-if="status" class="auth-status">{{ status }}</div>
        <form class="auth-form" @submit.prevent="submit">
            <div class="auth-field"><InputLabel for="email" value="Email address" /><TextInput id="email" v-model="form.email" type="email" required autofocus autocomplete="username" placeholder="you@company.com" /><InputError :message="form.errors.email" /></div>
            <div class="auth-field"><div class="auth-label-row"><InputLabel for="password" value="Password" /><Link v-if="canResetPassword" :href="route('password.request')">Forgot password?</Link></div><TextInput id="password" v-model="form.password" type="password" required autocomplete="current-password" placeholder="Enter your password" /><InputError :message="form.errors.password" /></div>
            <label class="auth-check"><Checkbox v-model:checked="form.remember" name="remember" /><span>Keep me signed in</span></label>
            <PrimaryButton class="auth-submit" :class="{ 'opacity-50': form.processing }" :disabled="form.processing">{{ form.processing ? 'Signing in...' : 'Sign in' }} <span>→</span></PrimaryButton>
        </form>
    </AuthLayout>
</template>
