<?php

namespace App\Models\Venue;

use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    protected $guarded = [];

    protected $casts = [
        'facilities' => 'array',
        'operating_hours' => 'array',
        'unavailability_slots' => 'array',
    ];

    public function courts()
    {
        return $this->hasMany(Court::class);
    }
}
