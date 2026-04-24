<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Hour extends Model
{
    protected $fillable = [
        'id',
        'weekday_start',
        'weekday_end',
        'weekend_start',
        'weekend_end',
    ];

    /**
     * Перетворення типів (Casting).
     * Це важливо, щоб Carbon міг працювати з часом як з об'єктом.
     */
    protected $casts = [
        'weekday_start' => 'datetime:H:i',
        'weekday_end' => 'datetime:H:i',
        'weekend_start' => 'datetime:H:i',
        'weekend_end' => 'datetime:H:i',
    ];
}
