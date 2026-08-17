<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Currencies;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;

/**
 * Imports the accounts from the mobile app's Firebase project into the `users`
 * table.
 *
 * Produce the input file with the Firebase CLI:
 *
 *     firebase auth:export firebase-users.json --format=json --project <project-id>
 *
 * Passwords are deliberately not imported. Firebase hashes them with its own
 * modified scrypt, which Laravel cannot verify, so an imported account signs in
 * one of two ways:
 *
 *   - it had a `google.com` provider  → "Continue with Google" just works
 *   - it was email/password only      → the person uses "Forgot your password?"
 *
 * The command is idempotent: running it twice updates rather than duplicates.
 */
class ImportFirebaseUsersCommand extends Command
{
    protected $signature = 'pairpay:import-firebase-users
                            {file : Path to the JSON produced by `firebase auth:export`}
                            {--dry-run : Report what would change without writing anything}';

    protected $description = 'Import user accounts from a Firebase Auth export';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->components->error("No such file: {$path}");

            return self::FAILURE;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (! is_array($payload) || ! isset($payload['users']) || ! is_array($payload['users'])) {
            $this->components->error('That file does not look like a `firebase auth:export` result (no "users" key).');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $needPasswordReset = 0;

        foreach ($payload['users'] as $record) {
            $email = $record['email'] ?? null;

            if (! is_string($email) || trim($email) === '') {
                // Anonymous or phone-only accounts have nothing to match on.
                $skipped++;

                continue;
            }

            $uid = (string) ($record['localId'] ?? '');
            $googleId = $this->googleIdFrom($record);

            $user = User::query()
                ->when($uid !== '', fn ($query) => $query->where('firebase_uid', $uid))
                ->first()
                ?? User::query()->where('email', $email)->first();

            $isNew = $user === null;

            if ($googleId === null) {
                $needPasswordReset++;
            }

            if ($dryRun) {
                $isNew ? $created++ : $updated++;

                continue;
            }

            $user ??= new User;

            $user->fill([
                'name' => $record['displayName'] ?? (string) str($email)->before('@'),
                'email' => $email,
            ]);

            $user->forceFill(array_filter([
                'firebase_uid' => $uid !== '' ? $uid : null,
                'google_id' => $googleId,
                'avatar' => $record['photoUrl'] ?? null,
            ], fn ($value) => $value !== null));

            if ($isNew) {
                $user->forceFill([
                    'currency_code' => Currencies::DEFAULT,
                    'password' => null,
                    'created_at' => $this->timestampFrom($record['createdAt'] ?? null),
                ]);
            }

            if (($record['emailVerified'] ?? false) === true && $user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()]);
            }

            $user->save();

            $isNew ? $created++ : $updated++;
        }

        $this->newLine();
        $this->components->twoColumnDetail('Created', (string) $created);
        $this->components->twoColumnDetail('Updated', (string) $updated);
        $this->components->twoColumnDetail('Skipped (no email)', (string) $skipped);
        $this->components->twoColumnDetail('Must reset their password', (string) $needPasswordReset);

        if ($dryRun) {
            $this->newLine();
            $this->components->warn('Dry run — nothing was written.');
        }

        if ($needPasswordReset > 0 && ! $dryRun) {
            $this->newLine();
            $this->components->info(
                "{$needPasswordReset} account(s) had no Google provider. They will need to use ".
                '"Forgot your password?" on first sign-in — this command does not email anyone.',
            );
        }

        return self::SUCCESS;
    }

    /**
     * The Google account id, if this Firebase user had Google linked.
     *
     * @param  array<string, mixed>  $record
     */
    private function googleIdFrom(array $record): ?string
    {
        foreach ($record['providerUserInfo'] ?? [] as $provider) {
            if (($provider['providerId'] ?? null) === 'google.com' && isset($provider['rawId'])) {
                return (string) $provider['rawId'];
            }
        }

        return null;
    }

    /**
     * Firebase writes `createdAt` as milliseconds since the epoch, as a string.
     */
    private function timestampFrom(mixed $value): CarbonInterface
    {
        if (! is_numeric($value)) {
            return now();
        }

        return CarbonImmutable::createFromTimestampMs((int) $value);
    }
}
