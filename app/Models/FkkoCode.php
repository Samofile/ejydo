<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FkkoCode extends Model
{
    protected $fillable = [
        'code',
        'name',
        'hazard_class',
        'unit',
        'category',
        'is_active',
        'origin',
        'aggregate_state',
        'chemical_composition',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function formatCode($code)
    {
        $clean = str_replace(' ', '', (string)$code);
        if (strlen($clean) === 11) {
            return substr($clean, 0, 1) . ' ' .
                   substr($clean, 1, 2) . ' ' .
                   substr($clean, 3, 3) . ' ' .
                   substr($clean, 6, 2) . ' ' .
                   substr($clean, 8, 2) . ' ' .
                   substr($clean, 10, 1);
        }
        return $code;
    }
}
