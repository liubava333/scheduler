<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\Events;
use Illuminate\Http\Request;
use App\Models\Admin\Hour;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class HoursController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard', [
            'hours' => Hour::all(),
            'events' => Events::all()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'weekdayStart' => 'nullable|date_format:H:i',
            'weekdayEnd' => 'nullable|date_format:H:i',
            'weekendStart' => 'nullable|date_format:H:i',
            'weekendEnd' => 'nullable|date_format:H:i',
        ]);
        Hour::updateOrCreate(
            ['id' => 1], // Search criteria
            ['weekday_start' => $request->input('weekdayStart'),
                'weekday_end' => $request->input('weekdayEnd'),
                'weekend_start' => $request->input('weekendStart'),
                'weekend_end' => $request->input('weekendEnd'),
            ]);

        return redirect()->back()->with('success', 'Години роботи успішно оновлено!');
    }
}
