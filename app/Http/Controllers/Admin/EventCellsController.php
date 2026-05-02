<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\EventCells;
use Illuminate\Http\Request;

class EventCellsController extends Controller
{
    public function getAll()
    {
        $eventCells = EventCells::all();
        return response()->json([
            'eventCells' => $eventCells
        ]);
    }

    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|exists:events,id',
            'cells' => 'required|array|min:1',
            'cells.*' => 'required|date',
        ]);

        $data = collect($validated['cells'])->map(function ($cell) use ($validated) {
            return [
                'event_id'   => $validated['event_id'],
                'start'      => $cell,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        // Один запрос к базе данных вместо десятка ВМЕСТО ЦИКЛА!!
        EventCells::insert($data);

        return redirect()->back();
    }

    public function destroy($eventId)
    {
        try {
            EventCells::where('event_id', $eventId)->delete();

            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'error' => $e,
            ]);
        }
    }
}
