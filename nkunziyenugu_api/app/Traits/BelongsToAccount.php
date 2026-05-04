<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Multi-tenant safety net.
 *
 * Adds a global scope so that any query on this model is automatically
 * filtered to the current request's account_id. Also auto-fills account_id
 * on creation when it's not explicitly provided.
 *
 * Behaviour:
 *  - HTTP context (request header X-Account-ID set + middleware passed):
 *      → all queries WHERE account_id = <header value>
 *      → new rows get account_id from the header if not set
 *  - Non-HTTP context (artisan, queue, observers, tests):
 *      → no scope applied (current_account_id() returns null)
 *      → queries see all rows; explicit ->where('account_id', X) still works
 *
 * To bypass the scope inside HTTP context (e.g. super-admin tooling, cross-
 * account aggregates), use ->withoutGlobalScope('account') on the query.
 */
trait BelongsToAccount
{
    protected static function bootBelongsToAccount(): void
    {
        static::addGlobalScope('account', function (Builder $q): void {
            $accountId = current_account_id();
            if ($accountId === null) return;

            $q->where($q->getModel()->getTable() . '.account_id', $accountId);
        });

        static::creating(function ($model): void {
            if (!empty($model->account_id)) return;
            $accountId = current_account_id();
            if ($accountId !== null) {
                $model->account_id = $accountId;
            }
        });
    }
}
