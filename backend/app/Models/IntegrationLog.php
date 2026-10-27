<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Журнал обращений к внешним сервисам. */
class IntegrationLog extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'request' => 'array',
            'response' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
