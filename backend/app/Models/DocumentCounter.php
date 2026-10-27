<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Счётчик номеров документов. */
class DocumentCounter extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
}
