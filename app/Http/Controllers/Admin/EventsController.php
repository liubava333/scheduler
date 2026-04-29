<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\Events;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class EventsController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'note' => 'nullable|string',
            'color' => 'nullable|string',
        ]);

        $res = Events::create($validated);

        return redirect()->back()->with([
            'success' => 'Подія успішно додана!',
            'eventId' => $res->id
        ]);
    }
}
