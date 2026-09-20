import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useNavigationMenu() {
    const page = usePage();

    const items = computed(() => {
        const navigation = [
            {
                label: 'Dashboard',
                href: route('dashboard'),
                active: 'dashboard',
            },
            {
                label: 'Containers',
                href: route('docker.containers.index'),
                active: 'docker.containers.*',
            },
        ];

        if (['admin', 'dev'].includes(page.props.auth.user.role)) {
            navigation.push({
                label: 'Users',
                href: route('users.index'),
                active: 'users.*',
            });
        }

        if (page.props.auth.user.role === 'dev') {
            navigation.push({
                label: 'Docker Images',
                href: route('docker.images.index'),
                active: 'docker.images.*',
            });
        }

        return navigation;
    });

    const isActive = (pattern) => route().current(pattern);

    return { items, isActive };
}
