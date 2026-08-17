<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportFirebaseUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_google_and_password_accounts(): void
    {
        $file = $this->exportFile([
            [
                'localId' => 'uid-google',
                'email' => 'ada@example.com',
                'displayName' => 'Ada Lovelace',
                'photoUrl' => 'https://example.com/ada.jpg',
                'emailVerified' => true,
                'createdAt' => '1700000000000',
                'providerUserInfo' => [
                    ['providerId' => 'google.com', 'rawId' => 'google-999'],
                ],
            ],
            [
                'localId' => 'uid-password',
                'email' => 'grace@example.com',
                'displayName' => 'Grace Hopper',
                'emailVerified' => false,
                'passwordHash' => 'scrypt-hash-we-cannot-use',
                'providerUserInfo' => [
                    ['providerId' => 'password', 'rawId' => 'grace@example.com'],
                ],
            ],
            // Anonymous account — nothing to match on.
            ['localId' => 'uid-anon'],
        ]);

        $this->artisan('pairpay:import-firebase-users', ['file' => $file])
            ->assertSuccessful();

        $this->assertSame(2, User::query()->count());

        $ada = User::query()->where('email', 'ada@example.com')->sole();
        $this->assertSame('uid-google', $ada->firebase_uid);
        $this->assertSame('google-999', $ada->google_id);
        $this->assertSame('Ada Lovelace', $ada->name);
        $this->assertSame('https://example.com/ada.jpg', $ada->avatar);
        $this->assertNotNull($ada->email_verified_at);
        $this->assertSame('2023-11-14', $ada->created_at->toDateString());

        $grace = User::query()->where('email', 'grace@example.com')->sole();
        $this->assertNull($grace->google_id);
        $this->assertNull($grace->password, 'Firebase password hashes are never imported.');
        $this->assertNull($grace->email_verified_at);
    }

    public function test_running_it_twice_updates_rather_than_duplicates(): void
    {
        $file = $this->exportFile([
            [
                'localId' => 'uid-google',
                'email' => 'ada@example.com',
                'displayName' => 'Ada Lovelace',
                'emailVerified' => true,
                'providerUserInfo' => [
                    ['providerId' => 'google.com', 'rawId' => 'google-999'],
                ],
            ],
        ]);

        $this->artisan('pairpay:import-firebase-users', ['file' => $file])->assertSuccessful();
        $this->artisan('pairpay:import-firebase-users', ['file' => $file])->assertSuccessful();

        $this->assertSame(1, User::query()->count());
    }

    public function test_it_links_onto_an_account_that_already_registered_on_the_web(): void
    {
        $existing = User::factory()->create(['email' => 'ada@example.com', 'name' => 'Ada']);

        $file = $this->exportFile([
            [
                'localId' => 'uid-google',
                'email' => 'ada@example.com',
                'displayName' => 'Ada Lovelace',
                'emailVerified' => true,
                'providerUserInfo' => [
                    ['providerId' => 'google.com', 'rawId' => 'google-999'],
                ],
            ],
        ]);

        $this->artisan('pairpay:import-firebase-users', ['file' => $file])->assertSuccessful();

        $existing->refresh();

        $this->assertSame(1, User::query()->count());
        $this->assertSame('google-999', $existing->google_id);
        $this->assertSame('uid-google', $existing->firebase_uid);
        $this->assertNotNull($existing->password, 'An existing web password is left alone.');
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $file = $this->exportFile([
            [
                'localId' => 'uid-google',
                'email' => 'ada@example.com',
                'emailVerified' => true,
            ],
        ]);

        $this->artisan('pairpay:import-firebase-users', ['file' => $file, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(0, User::query()->count());
    }

    public function test_it_rejects_a_file_that_is_not_a_firebase_export(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'fb').'.json';
        file_put_contents($path, json_encode(['nope' => true]));

        $this->artisan('pairpay:import-firebase-users', ['file' => $path])->assertFailed();

        @unlink($path);
    }

    /**
     * @param  array<int, array<string, mixed>>  $users
     */
    private function exportFile(array $users): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fb').'.json';

        file_put_contents($path, json_encode(['users' => $users]));

        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }
}
