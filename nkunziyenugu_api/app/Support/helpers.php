<?php

/**
 * Project-wide helper functions.
 *
 * Autoloaded via composer.json's autoload.files entry.
 */

if (!function_exists('current_account_id')) {
    /**
     * The active account_id for the current HTTP request, or null when
     * we're not in HTTP context (artisan, queue, observers, tests).
     *
     * Reads the X-Account-ID header — set by the frontend after the user
     * picks an account, validated by the EnsureAccountAccess middleware.
     * Never trust this for *write* authorisation in a controller — that's
     * what ResolvesAccount::resolveAccountId() is for. This helper is
     * specifically for the BelongsToAccount global scope to filter reads.
     */
    function current_account_id(): ?int
    {
        if (!app()->bound('request')) return null;

        $req = request();
        if (!$req) return null;

        // Prefer the header. The middleware also merges it as $req->account_id
        // but the header is the canonical, authenticated value.
        $hdr = $req->header('X-Account-ID');
        if ($hdr === null || $hdr === '') return null;

        $id = (int) $hdr;
        return $id > 0 ? $id : null;
    }
}
