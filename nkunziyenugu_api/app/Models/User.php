<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Notifications\ResetPasswordNotification;


class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasFactory;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'surname',
        'email',
        'phone',
        'password_hash',
        'deleted_flag'
    ];

    protected $hidden = [
        'password_hash'
    ];

    /** Per-request memoization for permission lookups. */
    protected array $_permsCache = [];

    //Get password attribute
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    //User assignments relationship
    public function accounts()
    {
        return $this->belongsToMany(Account::class, 'account_users')
                    ->withPivot('route_access', 'action_access')
                    ->wherePivot('deleted_flag', 0);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Resolve the effective account id for a permission check — fall back to
     * the X-Account-ID request header when caller didn't pass one explicitly.
     */
    protected function resolveAccountId(?int $accountId): ?int
    {
        if ($accountId) return $accountId;
        $hdr = request()?->header('X-Account-ID');
        return $hdr ? (int) $hdr : null;
    }

    /**
     * Load the route_access + action_access + is_owner flag for the given
     * account, memoized for the life of the request.
     * Returns ['routes' => [...], 'actions' => [...], 'is_owner' => bool]
     */
    protected function permissionsForAccount(int $accountId): array
    {
        if (isset($this->_permsCache[$accountId])) return $this->_permsCache[$accountId];

        $pivot = \DB::table('account_users')
            ->where('user_id', $this->id)
            ->where('account_id', $accountId)
            ->where('deleted_flag', 0)
            ->first(['route_access', 'action_access', 'is_owner']);

        $routes  = $pivot ? (json_decode($pivot->route_access  ?? '[]', true) ?: []) : [];
        $actions = $pivot ? (json_decode($pivot->action_access ?? '[]', true) ?: []) : [];
        $isOwner = $pivot ? (bool) $pivot->is_owner : false;

        return $this->_permsCache[$accountId] = compact('routes', 'actions', 'isOwner');
    }

    /**
     * Check if this user can access a given named route in the given account.
     * Super admins and Owners bypass — Owners are immune to renames in
     * permissions_registry because their is_owner flag is canonical.
     */
    public function canAccessRoute(string $route, ?int $accountId = null): bool
    {
        if ($this->is_super_admin) return true;
        $accountId = $this->resolveAccountId($accountId);
        if (!$accountId) return false;
        $perms = $this->permissionsForAccount($accountId);
        if ($perms['isOwner']) return true;
        return in_array($route, $perms['routes'], true);
    }

    /**
     * Check if this user can perform a given action in the given account.
     * Super admins and Owners bypass.
     */
    public function hasAction(string $action, ?int $accountId = null): bool
    {
        if ($this->is_super_admin) return true;
        $accountId = $this->resolveAccountId($accountId);
        if (!$accountId) return false;
        $perms = $this->permissionsForAccount($accountId);
        if ($perms['isOwner']) return true;
        return in_array($action, $perms['actions'], true);
    }

    /**
     * Combined check: the user must both be allowed on the route AND be
     * allowed to perform the action. Used by the CheckPermission middleware.
     */
    public function canDo(string $route, string $action, ?int $accountId = null): bool
    {
        return $this->canAccessRoute($route, $accountId) && $this->hasAction($action, $accountId);
    }

    /** Clear the per-request cache (e.g. after saving new pivot rows). */
    public function clearPermsCache(): void
    {
        $this->_permsCache = [];
    }
}
