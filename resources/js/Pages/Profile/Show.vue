<script setup>
import ManagerLayout from '@/Layouts/ManagerLayout.vue';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';

defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});
</script>

<template>
    <ManagerLayout title="Profile">
        <div class="profile-heading"><div><div class="eyebrow">Account / settings</div><h1>Your profile</h1><p>จัดการข้อมูลส่วนตัว ความปลอดภัย และ session ของคุณ</p></div><div class="profile-role">{{ $page.props.auth.user.role }}</div></div>
        <div class="profile-grid">
            <aside class="profile-menu"><div class="profile-menu-title">Settings</div><a href="#profile-information" class="profile-menu-link active">◎ <span>Profile information</span></a><a href="#password" class="profile-menu-link">⌁ <span>Password & security</span></a><a href="#sessions" class="profile-menu-link">◷ <span>Active sessions</span></a><a v-if="$page.props.jetstream.canManageTwoFactorAuthentication" href="#two-factor" class="profile-menu-link">◈ <span>Two-factor auth</span></a><a v-if="$page.props.jetstream.hasAccountDeletionFeatures" href="#danger-zone" class="profile-menu-link profile-menu-link--danger">× <span>Danger zone</span></a></aside>
            <div class="profile-sections">
                <section id="profile-information" class="profile-card" v-if="$page.props.jetstream.canUpdateProfileInformation">
                    <div class="profile-card-heading"><div><h2>Profile information</h2><p>อัปเดตชื่อและ email ที่ใช้สำหรับบัญชี</p></div><span class="section-number">01</span></div>
                    <UpdateProfileInformationForm :user="$page.props.auth.user" />
                </section>
                <section id="password" class="profile-card" v-if="$page.props.jetstream.canUpdatePassword">
                    <div class="profile-card-heading"><div><h2>Password & security</h2><p>เปลี่ยนรหัสผ่านเพื่อรักษาความปลอดภัยของบัญชี</p></div><span class="section-number">02</span></div>
                    <UpdatePasswordForm />
                </section>
                <section id="two-factor" class="profile-card" v-if="$page.props.jetstream.canManageTwoFactorAuthentication">
                    <div class="profile-card-heading"><div><h2>Two-factor authentication</h2><p>เพิ่มการยืนยันอีกชั้นก่อนเข้าสู่ระบบ</p></div><span class="section-number">03</span></div>
                    <TwoFactorAuthenticationForm :requires-confirmation="confirmsTwoFactorAuthentication" />
                </section>
                <section id="sessions" class="profile-card"><div class="profile-card-heading"><div><h2>Active sessions</h2><p>ตรวจสอบ browser ที่กำลัง login อยู่</p></div><span class="section-number">04</span></div><LogoutOtherBrowserSessionsForm :sessions="sessions" /></section>
                <section id="danger-zone" class="profile-card profile-card--danger" v-if="$page.props.jetstream.hasAccountDeletionFeatures"><div class="profile-card-heading"><div><h2>Danger zone</h2><p>การลบบัญชีไม่สามารถย้อนกลับได้</p></div><span class="section-number">!</span></div><DeleteUserForm /></section>
            </div>
        </div>
    </ManagerLayout>
</template>
