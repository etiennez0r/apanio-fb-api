<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    var $fillable = [
        'name',
        'price',
        'stock',
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }
}
