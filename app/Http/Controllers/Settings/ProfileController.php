<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\Firebase\FirebaseRefusal;
use App\Services\Firebase\FirebaseUserSession;
use App\Services\Firebase\IdentityToolkit;
use App\Support\Currencies;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'currencies' => Currencies::options(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(
        ProfileDeleteRequest $request,
        IdentityToolkit $firebase,
        FirebaseUserSession $firebaseSession,
    ): RedirectResponse {
        $user = $request->user();

        // Delete the credential first. If the local row went first and this
        // failed, the account would still exist in Firebase and signing in
        // would silently recreate the profile they just asked to be rid of.
        //
        // Confirming their password a moment ago is what produced this token,
        // so its absence means something is wrong rather than that they are
        // simply unverified — either way, stopping is better than half-deleting.
        $idToken = $firebaseSession->idToken();

        if ($idToken === null) {
            return back()->withErrors([
                'password' => __('We could not delete your account just now. Please sign in again and retry.'),
            ]);
        }

        try {
            $firebase->deleteAccount($idToken);
        } catch (FirebaseRefusal) {
            return back()->withErrors([
                'password' => __('We could not delete your account just now. Please try again in a moment.'),
            ]);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
