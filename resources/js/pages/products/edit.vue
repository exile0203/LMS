<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import {useProduct} from '@/composables/useProduct'
import Button from '@/components/ui/button/Button.vue';
import Label from '@/components/ui/label/Label.vue';
import Input from '@/components/ui/input/Input.vue';
import products from '@/routes/products';
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Edit A Product',
        href: '',
    },
];

interface Product{
    id:number,
    name:string,
    price:number,
    quantity:number
}
const props = defineProps<{product:Product}>();
const {updateProduct, form}= useProduct(props.product);

const handleUpdateProduct = ()=>{
    updateProduct(props.product.id);
}

</script>

<template>
    <Head title="Edit A Product" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="p-4"
        >
        <form @submit.prevent="handleUpdateProduct" class="w-8/12 space-y-4">
            <div class="space-y-2">
                <Label  for="Product name">Name:</Label>
                <Input v-model="form.name"  type="text" placeholder="name"/>
                <Label for="Product name">Price:</Label>
                <Input v-model="form.price"  type="number" placeholder="price"/>
                <Label for="Product name">Quantity:</Label>
                <Input v-model="form.quantity"  type="number" placeholder="quantity"/>
                <Button  type="submit">Edit a Product</Button>
            </div>
        </form>
        </div>
    </AppLayout>
</template>
