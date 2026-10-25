<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LevelGift extends Model
{
    use HasFactory;

    protected $table = 'levels_gift';

    protected $fillable = [
        'name',
        'points_required',
        'percentage',
    ];
}
