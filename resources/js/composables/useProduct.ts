import { useForm } from "@inertiajs/vue3";

export function useProduct (initialData?: { name?: string; price?: number; quantity?: number  }){
    const form = useForm({
        name: initialData?.name ||'',
        price: initialData?.price ||0,
        quantity: initialData?.quantity ||0
    })

    const createProduct = ()=>{
        form.post('/products')
    }

    const updateProduct = (id: number) => {
        form.put(`/products/${id}`)
    }

    const deleteProduct = (id: number)=>{
        form.delete(`/products/${id}`)
    }


    return {form, createProduct, updateProduct, deleteProduct}
}