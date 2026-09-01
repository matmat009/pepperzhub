<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Grandfathers every account that predates email verification being enforced.
 *
 * App\Models\User now implements MustVerifyEmail, which is what finally makes
 * the `verified` middleware on every admin.* route do anything — until now
 * EnsureEmailIsVerified saw a user that did not implement the contract and let
 * it straight through.
 *
 * This has to land in the same deploy as that change, not after it. The only
 * account that exists was provisioned by hand rather than through
 * registration — which is disabled — so no Registered event ever fired and no
 * verification email was ever sent. Turning the check on without this backfill
 * would bounce the operator to the verification notice on their very next
 * request, with no link anywhere to click through.
 *
 * A migration rather than a one-off tinker command, matching how
 * confirmation_token was backfilled in 2026_08_30_000006: it runs wherever
 * migrations run, on the app's own connection, instead of depending on someone
 * remembering to run something by hand against whichever environment is live.
 *
 * Scoped to null rows only. An account that has genuinely verified keeps the
 * timestamp it earned — this must never rewrite a real verification date.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    /**
     * Deliberately empty.
     *
     * There is no record of which rows were null beforehand, so a down()
     * cannot distinguish the accounts this backfilled from those that verified
     * on their own. Nulling either group would be worse than leaving them
     * verified: it would lock people out on a rollback.
     */
    public function down(): void
    {
        //
    }
};
