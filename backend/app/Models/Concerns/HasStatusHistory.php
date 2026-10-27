<?php

namespace App\Models\Concerns;

use App\Models\StatusHistory;
use App\Support\Actor;
use BackedEnum;

/**
 * Смена статусов только через setStatus(): каждая смена пишется в status_history.
 */
trait HasStatusHistory
{
    abstract public function historyEntityType(): string;

    public function setStatus(string $field, BackedEnum $to, Actor $actor, ?string $reason = null): void
    {
        $from = $this->getAttribute($field);
        $fromValue = $from instanceof BackedEnum ? $from->value : $from;
        if ($fromValue === $to->value) {
            return;
        }

        $this->setAttribute($field, $to);
        $this->save();

        StatusHistory::create([
            'entity_type' => $this->historyEntityType(),
            'entity_id' => $this->getKey(),
            'field' => $field,
            'from_value' => $fromValue,
            'to_value' => $to->value,
            'actor_type' => $actor->type->value,
            'actor_id' => $actor->id,
            'reason' => $reason,
        ]);
    }

    public function statusHistory()
    {
        return StatusHistory::query()
            ->where('entity_type', $this->historyEntityType())
            ->where('entity_id', $this->getKey())
            ->orderBy('id');
    }
}
