<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user',
        'internal_ref',
        'sup_amount',
        'inf_amount',
        'bill_charges',
        'sup_currency',
        'inf_currency',
        'market_rate',
        'applied_rate',
        'forex_offset',
        'sup_forex_charges',
        'inf_forex_charges',
        'bank_tran_ref',
        'mpesa_tran_ref',
        'status'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];
}
