<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('user')->latest()->get();

        if (!$products) {
            return response()->json([
                'status' => 'error',
                'message' => 'No records found',
            ], 404);
        }
        return response()->json([
            'status' => 'success',
            'data' => $products,
        ]);

        // $products = Product::all();
        // return view('CRUD.index', compact('products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:40',
            'type' => 'required|string|max:100',
            'breed' => 'required|string|max:100',
            'health_status' => 'required|string|max:100',
            'birth_date' => 'required|date',
        ]);


        $validated['user_id'] = 1;

        $product = Product::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'AAnimal inserted successfully',
            'data' => $product,
        ], 201);
    }

    public function show(int $id)
    {
        $product = Product::with('user')->find($id);
        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Animal not found',
            ], 404);
        }
        return response()->json([
            'status' => 'success',
            'data' => $product,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'AAnimal not found',
            ], 404);
        }


        if ($product->user_id !== auth()->id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:40',
            'type' => 'required|string|max:100',
            'breed' => 'required|string|max:100',
            'health_status' => 'required|string|max:100',
            'birth_date' => 'required|date',
        ]);

        $product->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'ANimela updated successfully',
            'data' => $product,
        ]);
    }

    public function destroy(string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'ANimal not found',
            ], 404);
        }

        if ($product->user_id !== auth()->id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action',
            ], 403);
        }

        $product->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'ANimal deleted successfully',
        ]);
    }


    public function create()
    {
        return view('CRUD.create');
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);
        return view('CRUD.update', compact('product'));
    }
}
