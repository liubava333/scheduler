<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\AdditionalCells;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;

class AdditionalCellsController extends Controller
{
    public function getAll()
    {
        $additionalCells = AdditionalCells::select('start', 'is_enabled')->get();

        return response()->json([
            'additionalCells' => $additionalCells
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cell' => 'required|string',
            'is_enabled' => 'required|boolean',
        ]);

        AdditionalCells::updateOrCreate(
            ['start' => $validated['cell']], // По какому полю искать
            ['is_enabled' => $validated['is_enabled']] // Что обновить/записать
        );

        return redirect()->back();
    }

    public function destroy($val)
    {
        try {
            AdditionalCells::where('start', $val)->delete();
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'error' => 'Помилка при видаленні!',
            ]);
        }
    }
}
