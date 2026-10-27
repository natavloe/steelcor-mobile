<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Запись истории статусов. Только для чтения. */
class StatusHistory extends Model
{
    protected $table = 'status_history';
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
