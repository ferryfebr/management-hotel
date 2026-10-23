<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyRevenue extends Model
{
    protected $table = 'monthly_revenue';

    protected $fillable = [
        'month',
        'revenue',
        'check_outs',
        'room_nights',
    ];

    protected function casts(): array
    {
        return [
            'revenue' => 'decimal:2',
            'check_outs' => 'integer',
            'room_nights' => 'integer',
        ];
    }
}
