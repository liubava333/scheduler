<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class EventCells extends Model
{
    protected $fillable = [
        'event_id',
        'start',
    ];
}
