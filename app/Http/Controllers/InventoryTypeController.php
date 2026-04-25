<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InventoryType;

class InventoryTypeController extends Controller
{
    public function index()
    {
        return InventoryType::orderBy('name', 'asc')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|unique:inventory_types|max:50', 'accessory' => 'nullable|boolean']);
        return InventoryType::create($data);
    }

    public function update(Request $request, $id)
    {
        $type = InventoryType::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|unique:inventory_types,name,' . $id,
            'accessory' => 'required|boolean'
        ]);

        $type->update($data);

        return response()->json($type);
    }
}
