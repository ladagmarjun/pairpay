<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Firebase\FirebaseRefusal;
use App\Services\Firebase\IdentityToolkit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Forgot your password?", handed to Firebase.
 *
 * Firebase sends the mail and hosts the page the link lands on, so the new
 * password is set entirely on their side and this app never sees it — which is
 * the point, since this app has nowhere to put it. There is no
 * `/reset-password/{token}` route here any more for the same reason.
 */
class PasswordResetController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        try {
            app(IdentityToolkit::class)->sendPasswordResetEmail($validated['email']);
        } catch (FirebaseRefusal $refusal) {
            // An outage is worth admitting to; anything else is Firebase saying
            // it has never heard of the address, which is not something to tell
            // whoever typed it. Confirming that would turn this form into a way
            // to discover who has an account.
            if ($refusal->isOutage()) {
                return back()->withErrors([
                    'email' => __('We could not send that just now. Please try again in a moment.'),
                ]);
            }
        }

        return back()->with('status', __('If that email address has an account, a reset link is on its way.'));
    }
}
