<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\Firebase\FirebaseRefusal;
use App\Services\Firebase\FirebaseSession;
use App\Services\Firebase\FirebaseUserSession;
use App\Services\Firebase\IdentityToolkit;
use App\Support\Currencies;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Registration, which now happens in Firebase first.
 *
 * The account is created there and mirrored here, so there is exactly one
 * credential store and a new sign-up is indistinguishable from an account that
 * came over from the mobile app. The local row never holds the password.
 *
 * Firebase is also the thing that knows whether an address is already taken, so
 * `EMAIL_EXISTS` is translated back into an ordinary validation error on the
 * email field rather than surfacing as a failure.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private readonly IdentityToolkit $firebase,
        private readonly FirebaseUserSession $firebaseSession,
    ) {}

    /**
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $session = $this->createInFirebase($input['email'], $input['password']);

        $user = new User([
            'name' => $input['name'],
            'email' => $input['email'],
            'currency_code' => Currencies::DEFAULT,
        ]);

        $user->forceFill([
            'firebase_uid' => $session->uid,
            // The password lives in Firebase. Writing it here as well would
            // give the app a second credential to keep in step, and it has no
            // way to keep it in step.
            'password' => null,
        ])->save();

        $this->firebaseSession->remember($session);

        // Firebase sends the verification mail and hosts the page it lands on.
        // A failure here is not worth losing the account over — they can ask
        // again from the verify-email screen.
        rescue(fn () => $this->firebase->sendVerificationEmail($session->idToken), report: true);

        return $user;
    }

    private function createInFirebase(string $email, string $password): FirebaseSession
    {
        try {
            return $this->firebase->signUp($email, $password);
        } catch (FirebaseRefusal $refusal) {
            throw ValidationException::withMessages([
                'email' => match (true) {
                    $refusal->errorCode === 'EMAIL_EXISTS' => __('An account already exists for this email.'),
                    $refusal->errorCode === 'INVALID_EMAIL' => __('That does not look like a valid email address.'),
                    $refusal->errorCode === 'WEAK_PASSWORD' => __('That password is too weak.'),
                    $refusal->isOutage() => __('We could not reach the sign-up service. Please try again in a moment.'),
                    default => __('We could not create your account. Please try again.'),
                },
            ]);
        }
    }
}
