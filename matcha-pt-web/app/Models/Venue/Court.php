<?php

namespace App\Models\Venue;

use Illuminate\Database\Eloquent\Model;

class Court extends Model
{
    protected $guarded = [];

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }
}
