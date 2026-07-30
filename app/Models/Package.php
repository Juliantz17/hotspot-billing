<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'duration_minutes',
        'price',
        'is_active',
        'speed_limit',
        'fup_enabled',
        'fup_threshold_bytes',
        'fup_speed_limit',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'fup_enabled' => 'boolean',
            'fup_threshold_bytes' => 'integer',
        ];
    }
}
