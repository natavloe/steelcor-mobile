<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Журнал изменений. Только для чтения. */
class ActivityLog extends Model
{
    protected $table = 'activity_log';
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
