<?php

namespace App\Domain\Companies\Models;

use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $table = 'companies';

    protected $fillable = [
        'denumire',
        'tip_firma',
        'cui',
        'nr_reg_comertului',
        'platitor_tva',
        'adresa',
        'localitate',
        'judet',
        'tara',
        'email',
        'telefon',
        'tip_companie',
        'activ',
    ];

    protected $casts = [
        'platitor_tva' => 'boolean',
        'activ' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
