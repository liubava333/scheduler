<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\AdditionalCells;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AdditionalCellsController extends Controller
{
    public function getAll()
    {
        $additionalCells = AdditionalCells::pluck('start');
        return response()->json([
            'additionalCells' => $additionalCells
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cell' => 'required|string',
        ]);
        AdditionalCells::create([
            'start' => $validated['cell']
        ]);
        return redirect()->back();
    }
}
