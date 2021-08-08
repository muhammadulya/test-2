<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use DB;

// MODELS
use App\Models\Product_category;
use App\Models\Product_item;

class ProductItemController extends Controller
{
    /**
     * LIST PRODUCT ITEM
     */
    public function index(Request $request)
    {
        $data = Product_item::select(
                'product_item.*',
                'product_category.name as category_name'
            )
            ->leftJoin('product_category', 'product_item.category_id', 'product_category.id');

        // IF FILTER BY CATEGORY
        if ($request->category) {
            $data = $data->where('product_item.category_id', (int) $request->category);
        }

        // IF SEARCH BY KEYWORD
        if ($request->keyword) {
            $data = $data->where('product_item.name', 'LIKE', "%$request->keyword%")
                ->orWhere('product_item.description', 'LIKE', "%$request->keyword%")
                ->orWhere('product_item.colors', 'LIKE', "%$request->keyword%")
                ->orWhere('product_item.size_and_price', 'LIKE', "%$request->keyword%")
                ->orWhere('product_category.name', 'LIKE', "%$request->keyword%");
        }

        $data = $data->get();

        return response()->json([
            'status'    => true,
            'message'   => 'Berhasil mendapatkan seluruh data produk',
            'data'      => $data
        ]);
    }

    /**
     * DETAIL PRODUCT ITEM
     */
    public function detail(Request $request)
    {
        // LUMEN VALIDATOR
        $validation = [
            'product_id' => 'required|integer'
        ];

        $message = [
            'required'  => ':attribute tidak boleh kosong',
            'integer'   => ':attribute harus berupa angka'
        ];

        $names = [
            'product_id' => 'Product'
        ];

        $validator = Validator::make($request->all(), $validation, $message, $names);

        if ($validator->fails()) {
            return response()->json([
                'status'    => false,
                'message'   => 'Validation error',
                'data'      => $validator->errors()->all()
            ]);    
        }

        $data = Product_item::select(
                'product_item.*',
                'product_category.name as category_name'
            )
            ->leftJoin('product_category', 'product_item.category_id', 'product_category.id')
            ->where('product_item.id', $request->product_id)
            ->first();
        
        if (empty($data)) {
            return response()->json([
                'status'    => false,
                'message'   => 'Produk tidak ditemukan',
                'data'      => null
            ]);
        }

        return response()->json([
            'status'    => true,
            'message'   => 'Berhasil menemukan data',
            'data'      => $data
        ]);
    }

    /**
     * STORE PRODUCT ITEM
     */
    public function store(Request $request)
    {
        // LUMEN VALIDATOR
        $validation = [
            'name'          => 'required',
            'category_id'   => 'required|integer',
            'description'   => 'required',
            'colors'        => 'required',
            'sizes'         => 'required',
            'prices'        => 'required',
        ];

        $message = [
            'required'  => ':attribute tidak boleh kosong',
            'integer'   => ':attribute harus berupa angka'
        ];

        $names = [
            'product_id'    => 'Product',
            'name'          => 'Nama',
            'category_id'   => 'Kategori',
            'description'   => 'Deskripsi',
            'images'        => 'Gambar',
            'colors'        => 'Warna',
            'sizes'         => 'Ukuran',
            'prices'        => 'Harga',
        ];

        $validator = Validator::make($request->all(), $validation, $message, $names);

        if ($validator->fails()) {
            return response()->json([
                'status'    => false,
                'message'   => 'Validation error',
                'data'      => $validator->errors()->all()
            ]);    
        }

        // INITIALIZE NEW PRODUCT
        $data = new Product_item();

        // CHECK CATEGORY ID
        $data->category_id  = (int) $request->category_id;
        $category_product   = Product_category::find($data->category_id);

        if (empty($category_product)) {
            return response()->json([
                'status'    => false,
                'message'   => 'Kategori produk tidak ditemukan, silahkan muat ulang halaman dan coba kembali',
                'data'      => null
            ]);
        }

        // NAME & DESCRIPTION
        $data->name         = $request->name;
        $data->description  = $request->description;

        // CHECK COLORS
        $color_collection = [];
        if ($request->colors) {
            foreach ($request->colors as $color) {
                $color_collection[] = $color;
            }

            $data->colors = json_encode($color_collection);
        } else {
            return response()->json([
                'status'    => false,
                'message'   => 'Warna produk harus diisi, minimal 1 warna',
                'data'      => null
            ]);
        }

        // CHECK SIZE AND PRICE
        if (!$request->sizes || !$request->prices) {
            return response()->json([
                'status'    => false,
                'message'   => 'Ukuran beserta harga produk harus diisi, minimal 1 ukuran dan 1 harga',
                'data'      => null
            ]);
        }

        // LOOP BY SIZE
        $loop_size = count($request->sizes);

        // SETUP COLLECTION
        $size_and_price_collection = [];

        for ($c = 0; $c < $loop_size; $c++) { 
            $size_price         = new \stdClass();
            $size_price->size   = $request->sizes[$c];

            // CHECK PRICES
            if (isset($request->prices[$c])) {
                $size_price->price  = $request->prices[$c];
    
                // IF SIZE 1 AND PRICE 1 EXIST, SO SAVE IT
                $size_and_price_collection[] = $size_price;
            }
        }

        $data->size_and_price = json_encode($size_and_price_collection);

        
        // IMAGES
        $images_collection  = [];
        if ($request->hasFile('images')) {
            // MAKE SURE DESTINATION PATH
            $path               = 'uploads/images/product_item';
            $images             = $request->file('images');

            foreach ($images as $img) {
                // GET EXTENTION
                $extention = strtolower($img->getClientOriginalExtension());

                // ALLOWED EXTENTION
                $allowed_extention = ['png', 'jpg', 'jpeg'];
                if (!in_array($extention, $allowed_extention)) {
                    // FAILED
                    return response()->json([
                        'status'    => false,
                        'message'   => 'Ekstensi gambar yang diperbolehkan adalah png, jpg, atau jpeg',
                        'data'      => null
                    ]);
                }

                // GET ORIGINAL NAME
                $origin_name = $img->getClientOriginalName();

                // GENERATE FILE NAME
                $file_name = time() . '-' . $origin_name . '-product_item.' . $extention; 

                // UPLOADING
                $uploading = $img->move($path, $file_name);

                // IF SUCCESS
                if ($uploading) {
                    $images_collection[] = $file_name;
                }
            }
        }

        $data->images = json_encode($images_collection);
        $data->status = (int) $request->status;

        DB::beginTransaction();
        
        try {
            if ($data->save()) {
                DB::commit();
                return response()->json([
                    'status'    => true,
                    'message'   => 'Berhasil menambahkan data',
                    'data'      => $data
                ]);
            } else {
                DB::rollback();
                return response()->json([
                    'status'    => false,
                    'message'   => 'Gagal menambahkan data',
                    'data'      => null
                ]);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'status'    => false,
                'message'   => 'Gagal menambahkan data',
                'data'      => null
            ]);
        }
    }

    /**
     * UPDATE PRODUCT ITEM
     */
    public function update(Request $request)
    {
        // LUMEN VALIDATOR
        $validation = [
            'product_id'    => 'required|integer',
            'name'          => 'required',
            'category_id'   => 'required|integer',
            'description'   => 'required',
            'colors'        => 'required',
            'sizes'         => 'required',
            'prices'        => 'required',
        ];

        $message = [
            'required'  => ':attribute tidak boleh kosong',
            'integer'   => ':attribute harus berupa angka'
        ];

        $names = [
            'product_id'    => 'Product',
            'name'          => 'Nama',
            'category_id'   => 'Kategori',
            'description'   => 'Deskripsi',
            'colors'        => 'Warna',
            'sizes'         => 'Ukuran',
            'prices'        => 'Harga',
        ];

        $validator = Validator::make($request->all(), $validation, $message, $names);

        if ($validator->fails()) {
            return response()->json([
                'status'    => false,
                'message'   => 'Validation error',
                'data'      => $validator->errors()->all()
            ]);    
        }

        // CHECK PRODUCT
        $data = Product_item::find($request->product_id);
        
        if (empty($data)) {
            return response()->json([
                'status'    => false,
                'message'   => 'Produk tidak ditemukan',
                'data'      => null
            ]);
        }

        // CHECK CATEGORY ID
        $data->category_id  = (int) $request->category_id;
        $category_product   = Product_category::find($data->category_id);

        if (empty($category_product)) {
            return response()->json([
                'status'    => false,
                'message'   => 'Kategori produk tidak ditemukan, silahkan muat ulang halaman dan coba kembali',
                'data'      => null
            ]);
        }

        // NAME & DESCRIPTION
        $data->name         = $request->name;
        $data->description  = $request->description;

        // CHECK COLORS
        $color_collection = [];
        if ($request->colors) {
            foreach ($request->colors as $color) {
                $color_collection[] = $color;
            }

            $data->colors = json_encode($color_collection);
        } else {
            return response()->json([
                'status'    => false,
                'message'   => 'Warna produk harus diisi, minimal 1 warna',
                'data'      => null
            ]);
        }

        // CHECK SIZE AND PRICE
        if (!$request->sizes || !$request->prices) {
            return response()->json([
                'status'    => false,
                'message'   => 'Ukuran beserta harga produk harus diisi, minimal 1 ukuran dan 1 harga',
                'data'      => null
            ]);
        }

        // LOOP BY SIZE
        $loop_size = count($request->sizes);

        // SETUP COLLECTION
        $size_and_price_collection = [];

        for ($c = 0; $c < $loop_size; $c++) { 
            $size_price         = new \stdClass();
            $size_price->size   = $request->sizes[$c];

            // CHECK PRICES
            if (isset($request->prices[$c])) {
                $size_price->price  = $request->prices[$c];
    
                // IF SIZE 1 AND PRICE 1 EXIST, SO SAVE IT
                $size_and_price_collection[] = $size_price;
            }
        }

        $data->size_and_price = json_encode($size_and_price_collection);
        
        // IMAGES
        if ($request->hasFile('images')) {
            // MAKE SURE DESTINATION PATH
            $path               = 'uploads/images/product_item';
            $images_collection  = [];
            $images             = $request->file('images');

            foreach ($images as $img) {
                // GET EXTENTION
                $extention = strtolower($img->getClientOriginalExtension());

                // ALLOWED EXTENTION
                $allowed_extention = ['png', 'jpg', 'jpeg'];
                if (!in_array($extention, $allowed_extention)) {
                    // FAILED
                    return response()->json([
                        'status'    => false,
                        'message'   => 'Ekstensi gambar yang diperbolehkan adalah png, jpg, atau jpeg',
                        'data'      => null
                    ]);
                }

                // GET ORIGINAL NAME
                $origin_name = $img->getClientOriginalName();

                // GENERATE FILE NAME
                $file_name = time() . '-' . $origin_name . '-product_item.' . $extention; 

                // UPLOADING
                $uploading = $img->move($path, $file_name);

                // IF SUCCESS
                if ($uploading) {
                    $images_collection[] = $file_name;
                }
            }

            $data->images = json_encode($images_collection);
        }

        $data->status = (int) $request->status;

        DB::beginTransaction();
        
        try {
            if ($data->save()) {
                DB::commit();
                return response()->json([
                    'status'    => true,
                    'message'   => 'Berhasil mengubah data',
                    'data'      => $data
                ]);
            } else {
                DB::rollback();
                return response()->json([
                    'status'    => false,
                    'message'   => 'Gagal mengubah data',
                    'data'      => null
                ]);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'status'    => false,
                'message'   => 'Gagal mengubah data',
                'data'      => null
            ]);
        }
    }

    /**
     * DELETE PRODUCT ITEM
     */
    public function delete(Request $request)
    {
        // LUMEN VALIDATOR
        $validation = [
            'product_id'    => 'required|integer'
        ];

        $message = [
            'required'  => ':attribute tidak boleh kosong',
            'integer'   => ':attribute harus berupa angka'
        ];

        $names = [
            'product_id'    => 'Product'
        ];

        $validator = Validator::make($request->all(), $validation, $message, $names);

        if ($validator->fails()) {
            return response()->json([
                'status'    => false,
                'message'   => 'Validation error',
                'data'      => $validator->errors()->all()
            ]);    
        }

        // CHECK PRODUCT
        $data = Product_item::find($request->product_id);
        
        if (empty($data)) {
            return response()->json([
                'status'    => false,
                'message'   => 'Produk tidak ditemukan',
                'data'      => null
            ]);
        }

        if ($data->delete()) {
            return response()->json([
                'status'    => true,
                'message'   => 'Berhasil menghapus data',
                'data'      => $data
            ]);
        } else {
            return response()->json([
                'status'    => false,
                'message'   => 'Gagal mengubah data',
                'data'      => null
            ]);
        }
    }
}