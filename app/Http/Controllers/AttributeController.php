<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class AttributeController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $attributes = Attribute::select(['id', 'name']);

            return DataTables::of($attributes)
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    return '<a class="btn btn-primary btn-sm">Edit</a>';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('backend.attribute.index');
    }
}
