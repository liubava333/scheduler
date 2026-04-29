<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Events extends Model
{
    protected $keyType = 'string';

    protected $fillable = [
    'name',
    'phone',
    'start',
    'end',
    'note',
    'color'
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        // When an event is being deleted
        static::deleting(function ($event) {
            // Delete all related event_cells
            $event->eventCells()->delete();
        });
    }

// Define the relationship in Events model
    public function eventCells()
    {
        return $this->hasMany(EventCells::class, 'event_id');
    }
}
