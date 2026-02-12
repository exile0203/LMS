<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import {create,edit} from '@/routes/products'
import { useProduct } from '@/composables/useProduct';
import Button from '@/components/ui/button/Button.vue';
import type { PageProps as InertiaPageProps } from '@inertiajs/core';
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Products',
        href: '/products',
    },
];

interface Flash {
  success?: string;
  error?: string;
}

interface Product {
  id: number;
  name: string;
  price: number;
  quantity: number;
}
interface PageProps extends InertiaPageProps {
  products: Product[];
  flash?: Flash;
}






const {deleteProduct}= useProduct();

const handleDelete = ($id: number)=>{
  deleteProduct($id);
}

const page = usePage<PageProps>();

</script>

<template>
    <Head title="Dashboard"/>

    <AppLayout :breadcrumbs="breadcrumbs">
        
       <h1>This is the index page</h1>
        <div v-if="page.props.flash?.success" class="bg-green-100 p-4 mb-4 text-green-800 rounded">
      {{ page.props.flash?.success }}
    </div>
     <Table>
    <TableCaption>A list of your recent invoices.</TableCaption>
    <TableHeader>
      <TableRow>
        <TableHead class="w-[100px]">
          ID
        </TableHead>
        <TableHead>Name</TableHead>
        <TableHead>Price</TableHead>
        <TableHead>Quantity</TableHead>
        <TableHead class="text-right">
          Action
        </TableHead>
      </TableRow>
    </TableHeader>
    <TableBody>
      <TableRow v-for="product in page.props.products" key="product.id">
        <TableCell class="font-medium">
          {{product.id}}
        </TableCell>
        <TableCell>{{ product.name }}</TableCell>
        <TableCell>{{product.price}}</TableCell>
        <TableCell>{{ product.quantity }}</TableCell>
        <TableCell class="text-right">
          <Link :href="edit.url(product)" class="p-3"><Button>Edit</Button> </Link>
            <Button @click="handleDelete(product.id)" class="bg-red-600">Delete</Button>
        </TableCell>
      </TableRow>
    </TableBody>
  </Table>
       <Link :href="create.url()"><Button>Create a Product</Button></Link>
    </AppLayout>
</template>
