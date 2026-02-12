<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Mail Box',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Mail Box" />

    <AppLayout :breadcrumbs="breadcrumbs">
        
    </AppLayout>
</template>
