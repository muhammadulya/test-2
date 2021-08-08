<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use DB;

// MODELS
use App\Models\Product_category;

class ProductCategoryController extends Controller
{
    /**
     * LIST PRODUCT CATEGORY
     */
    public function index(Request $request)
    {
        $data = Product_category::all();

        return response()->json([
            'status'    => true,
            'message'   => 'Berhasil mendapatkan seluruh data kategori produk',
            'data'      => $data
        ]);
    }
}