<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('products/index',[
            'products' =>Product::all(),
        ]);
    }

    
    public function create()
    {
        return Inertia::render('products/create');
    }

  
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'=>'required|string',
            'price'=>'required|numeric',
            'quantity'=>'required|numeric'
        ]);

        auth()->user()->products()->create($data);

        return redirect()->route('products.index')->with('success', 'Product created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::findOrFail($id);

        return $product;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        return Inertia::render('products/edit',[
            'product' =>$product,
        ]);
    }

   
    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name'=>'required|string',
            'price'=>'required|numeric',
            'quantity'=>'required|numeric',
        ]);

        $product->update($data);

        return redirect()->route('products.index')->with('success', 'Product updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Product::destroy($id);

        return redirect()->route('products.index')->with('success','Product successfully deleted');
    }
}
