<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Brand;

class BrandController extends Controller
{
    public function index() { return Brand::all(); }
    public function store(Request $request) {
        $data = $request->validate(['name' => 'required|unique:brands']);
        return Brand::create($data);
    }
}
