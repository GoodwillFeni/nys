<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Soft-delete via a flag column instead of Laravel's `deleted_at` timestamp.
 *
 * Most domain tables in this codebase use a `deleted` (or `deleted_flag`)
 * boolean column instead of Laravel's SoftDeletes trait. This trait gives
 * those models a consistent, working soft-delete API:
 *
 *   $model->softDelete();          // sets deleted = 1, persists
 *   $model->restoreFromDelete();   // sets deleted = 0
 *   Model::alive()->get();         // adds where('deleted', '!=', 1)
 *
 * IMPORTANT: For the column to actually update, it MUST be in $fillable.
 * `FarmAnimalEvent` previously had `deleted` missing from $fillable, which
 * silently ate every soft-delete attempt — that's the bug this trait
 * (combined with the fillable audit) closes.
 *
 * Override the column name with `protected $softDeleteColumn = 'deleted_flag';`
 * when needed (e.g. accounts / users / account_users use deleted_flag).
 */
trait SoftDeletesViaFlag
{
    /** Set the soft-delete flag and persist. Returns true on success. */
    public function softDelete(): bool
    {
        $col = $this->softDeleteColumn ?? 'deleted';
        $this->{$col} = 1;
        return $this->save();
    }

    /** Reverse a soft-delete. */
    public function restoreFromDelete(): bool
    {
        $col = $this->softDeleteColumn ?? 'deleted';
        $this->{$col} = 0;
        return $this->save();
    }

    /** Query scope: only non-deleted rows. Use as Model::alive()->... */
    public function scopeAlive(Builder $q): Builder
    {
        $col = $this->softDeleteColumn ?? 'deleted';
        return $q->where($q->getModel()->getTable() . '.' . $col, '!=', 1);
    }
}
