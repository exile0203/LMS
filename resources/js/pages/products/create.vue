<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import Button from '@/components/ui/button/Button.vue';
import Label from '@/components/ui/label/Label.vue';
import Input from '@/components/ui/input/Input.vue';
import { useProduct } from '@/composables/useProduct';
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Create A Product',
        href: '/products/create',
    },
];

const {form, createProduct} = useProduct();

const handleCreateProduct = ()=>{
   createProduct();
     
}
</script>

<template>
    <Head title="Create A Product" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="p-4"
        >
        <form @submit.prevent="handleCreateProduct" action="" method="post" class="w-8/12 space-y-4">
            <div class="space-y-2">
                <Label for="Product name">Name:</Label>
                <Input v-model="form.name" type="text" placeholder="name"/>
                <Label for="Product name">Price:</Label>
                <Input v-model="form.price" type="number" placeholder="price"/>
                <Label for="Product name">Quantity:</Label>
                <Input v-model="form.quantity" type="number" placeholder="quantity"/>
                <Button type="submit">Add a Product</Button>
            </div>
        </form>
        </div>
    </AppLayout>
</template>
