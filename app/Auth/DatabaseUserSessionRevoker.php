<?php

namespace App\Auth;

use App\Models\User;
use Closure;
use Illuminate\Database\ConnectionInterface;
use LogicException;

class DatabaseUserSessionRevoker
{
    /**
     * Change the password and revoke its sessions atomically. Failing before
     * either write is safer than silently accepting an unsupported driver.
     */
    public function revokeWithinPasswordChange(User $user, Closure $changePassword): void
    {
        $connection = $this->connectionFor($user);
        $table = config('session.table');

        if (! is_string($table) || $table === '') {
            throw new LogicException('The database session table is not configured.');
        }

        $connection->transaction(function () use ($changePassword, $connection, $table, $user): void {
            $changePassword();

            $connection->table($table)
                ->where('user_id', $user->getAuthIdentifier())
                ->delete();
        });
    }

    private function connectionFor(User $user): ConnectionInterface
    {
        if (config('session.driver') !== 'database') {
            throw new LogicException('Password-reset session revocation requires the database session driver.');
        }

        $userConnection = $user->getConnection();
        $sessionConnection = config('session.connection') ?: config('database.default');

        if ($sessionConnection !== $userConnection->getName()) {
            throw new LogicException('Password-reset session revocation requires users and sessions on the same database connection.');
        }

        return $userConnection;
    }
}
