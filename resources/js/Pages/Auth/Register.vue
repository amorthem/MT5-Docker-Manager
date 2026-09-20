<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Register" />

    <AuthLayout title="Create your account" eyebrow="Start operating">
        <form class="auth-form" @submit.prevent="submit">
            <div class="auth-field"><InputLabel for="name" value="Full name" /><TextInput id="name" v-model="form.name" type="text" required autofocus autocomplete="name" placeholder="Your name" /><InputError :message="form.errors.name" /></div>
            <div class="auth-field"><InputLabel for="email" value="Email address" /><TextInput id="email" v-model="form.email" type="email" required autocomplete="username" placeholder="you@company.com" /><InputError :message="form.errors.email" /></div>
            <div class="auth-field"><InputLabel for="password" value="Password" /><TextInput id="password" v-model="form.password" type="password" required autocomplete="new-password" placeholder="At least 12 characters" /><InputError :message="form.errors.password" /></div>
            <div class="auth-field"><InputLabel for="password_confirmation" value="Confirm password" /><TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" required autocomplete="new-password" placeholder="Repeat your password" /><InputError :message="form.errors.password_confirmation" /></div>
            <label v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature" class="auth-check"><Checkbox id="terms" v-model:checked="form.terms" name="terms" required /><span>I agree to the <a :href="route('terms.show')">Terms</a> and <a :href="route('policy.show')">Privacy Policy</a>.</span></label>
            <PrimaryButton class="auth-submit" :class="{ 'opacity-50': form.processing }" :disabled="form.processing">{{ form.processing ? 'Creating...' : 'Create account' }} <span>→</span></PrimaryButton>
        </form>
        <p class="auth-switch">Already have an account? <Link :href="route('login')">Sign in</Link></p>
    </AuthLayout>
</template>
