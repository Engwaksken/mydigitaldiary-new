<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * Drop-in replacement for Breeze's default ProfileController - same
 * update()/destroy() shape it ships with, plus updateAvatar(). Written
 * with inline validation (rather than relying on Breeze's own
 * ProfileUpdateRequest class) so this file has no dependency on Breeze's
 * exact generated shape beyond the route names.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill($data);

        if ($user->isDirty('email') && $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail) {
            $user->email_verified_at = null;
        }

        $user->save();

        return redirect()->route('profile.edit')->with('profile_status', 'profile-updated');
    }


    public function updateCurrency(Request $request): RedirectResponse
    {
        $settings = \App\Models\SiteSetting::current();
        $allowed = collect($settings->currencyOptions())->pluck('code')->map(fn ($c) => strtoupper($c))->all();
        $data = $request->validate([
            'preferred_currency_code' => ['required', 'string', Rule::in($allowed)],
        ]);
        $request->user()->update(['preferred_currency_code' => strtoupper($data['preferred_currency_code'])]);
        return back()->with('profile_status', 'currency-updated');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Rules\Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('profile_status', 'password-updated');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);

        return back()->with('profile_status', 'avatar-updated');
    }

    public function removeAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return back()->with('profile_status', 'avatar-removed');
    }

    /**
     * Personal accent colors - a user can independently set BOTH
     * gradient colors now; see User::themeColorLight() for how the
     * second one falls back to an auto-derived lighter version of the
     * first when left blank. An empty submission for either resets that
     * one specifically to its default/derived behavior (stores null,
     * not '').
     */
    public function updateTheme(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_color_secondary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $request->user()->update([
            'theme_color' => $data['theme_color'] ?: null,
            'theme_color_secondary' => $data['theme_color_secondary'] ?: null,
        ]);

        return back()->with('profile_status', 'theme-updated');
    }

    /**
     * Kept for compatibility with Breeze's default 'profile.destroy' route
     * name and behavior. The recommended, more complete path for users is
     * the Privacy & Data page (/privacy) - same cascading deletion, plus
     * data export and consent info in one place.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
    public function updatePersonalisation(Request $request): RedirectResponse
    {
        $allowedAi = ['planning','finance','goals','health','wellbeing','spiritual','notes','meetings','network','education','relationships'];
        $allowedFocus = ['money','day','goals','health','work','growth','everything'];
        $data = $request->validate([
            'ai_data_permissions' => ['nullable','array'],
            'ai_data_permissions.*' => ['string', Rule::in($allowedAi)],
            'onboarding_focuses' => ['nullable','array','max:7'],
            'onboarding_focuses.*' => ['string', Rule::in($allowedFocus)],
            'engagement_notification_preferences' => ['nullable','array'],
            'engagement_notification_preferences.*' => ['nullable','boolean'],
        ]);

        $request->user()->update([
            'ai_data_permissions' => array_values(array_unique($data['ai_data_permissions'] ?? [])),
            'onboarding_focuses' => array_values(array_unique($data['onboarding_focuses'] ?? [])),
            'onboarding_completed_at' => now(),
            'engagement_notification_preferences' => collect($data['engagement_notification_preferences'] ?? [])->map(fn ($v) => (bool) $v)->all(),
        ]);

        return back()->with('profile_status', 'personalisation-updated');
    }

}
