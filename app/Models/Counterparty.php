<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Counterparty extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'inn',
        'kpp',
        'ogrn',
        'legal_address',
        'phone',
        'license_number',
        'license_perpetual',
        'license_valid_until',
    ];

    protected $casts = [
        'license_valid_until' => 'date',
        'license_perpetual'   => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(UserCompany::class, 'company_id');
    }

    /**
     * Валидация контрольного числа ИНН (10 или 12 цифр).
     */
    public static function validateInn(string $inn): bool
    {
        return strlen($inn) > 9 && strlen($inn) < 13;
    }

    /**
     * Возвращает «снимок» реквизитов для сохранения в акте.
     */
    public function toSnapshot(): array
    {
        return [
            'name'                => $this->name,
            'inn'                 => $this->inn,
            'kpp'                 => $this->kpp,
            'ogrn'                => $this->ogrn,
            'legal_address'       => $this->legal_address,
            'phone'               => $this->phone,
            'license_number'      => $this->license_number,
            'license_perpetual'   => $this->license_perpetual,
            'license_valid_until' => $this->license_perpetual
                ? 'бессрочная'
                : $this->license_valid_until?->format('d.m.Y'),
        ];
    }
}
