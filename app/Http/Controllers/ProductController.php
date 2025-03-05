<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $products = Product::with('image')->select(['id', 'name', 'sku', 'price', 'created_at']);

            return DataTables::of($products)
                ->addIndexColumn()
                ->editColumn('image', function ($row) {
                    $imageUrl = $row->image ? $row->image->image_path : 'https://via.placeholder.com/50'; // Default image
                    return '<img src="' . $imageUrl . '" alt="' . $row->name . '" width="50">';
                })
                ->addColumn('action', function ($row) {
                    return '<a href="" class="btn btn-primary btn-sm">Shopify</a> ';
                })
                ->rawColumns(['image', 'action'])
                ->make(true);
        }

        return view('backend.product.index');
    }
}
