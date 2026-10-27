<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use App\Support\Actor;

/** Пишет в activity_log, кто и какие поля изменил. */
trait RecordsActivity
{
    public static function bootRecordsActivity(): void
    {
        static::created(fn ($model) => $model->logActivity('created', $model->getAttributes()));
        static::updated(function ($model) {
            $changes = [];
            foreach ($model->getChanges() as $field => $new) {
                if ($field === 'updated_at') {
                    continue;
                }
                $changes[$field] = ['old' => $model->getOriginal($field), 'new' => $new];
            }
            if ($changes !== []) {
                $model->logActivity('updated', $changes);
            }
        });
        static::deleted(fn ($model) => $model->logActivity('deleted', null));
    }

    protected function logActivity(string $event, ?array $changes): void
    {
        $actor = Actor::current();
        ActivityLog::create([
            'subject_type' => static::class,
            'subject_id' => $this->getKey(),
            'event' => $event,
            'changes' => $changes,
            'actor_type' => $actor->type->value,
            'actor_id' => $actor->id,
        ]);
    }
}
