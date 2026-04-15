<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

/**
 * BrynexUserProvider
 *
 * Custom provider that handles legacy sessions where the auth identifier
 * was stored as 'cedula' (a string) instead of the numeric primary 'id'.
 *
 * This occurs when old sessions used Auth::attempt(['cedula' => ...])
 * which stores the credential value as the session identifier.
 * Now that we use Auth::login($user) directly, the ID is always stored,
 * but old cookies/sessions may still carry the cédula value.
 */
class BrynexUserProvider extends EloquentUserProvider
{
    /**
     * Retrieve a user by their unique identifier.
     * If the identifier looks like a cédula (non-numeric or too large for an ID),
     * we gracefully return null instead of running a broken query.
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        // If identifier is not a valid positive integer, it's a stale session
        // that stored the cédula value. Return null so Laravel logs the user out.
        if (!ctype_digit((string) $identifier) || (int) $identifier <= 0) {
            Log::info('BrynexUserProvider: sesión con identifier inválido descartada.', [
                'identifier' => $identifier,
            ]);
            return null;
        }

        return parent::retrieveById($identifier);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     * Same guard: if identifier is not a valid ID, discard the token.
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        if (!ctype_digit((string) $identifier) || (int) $identifier <= 0) {
            Log::info('BrynexUserProvider: token remember-me con identifier inválido descartado.', [
                'identifier' => $identifier,
            ]);
            return null;
        }

        return parent::retrieveByToken($identifier, $token);
    }
}
