<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Mobile equivalent of ProfileController::updateTheme() plus the new
 * font family/size fields (which don't exist on web either — see
 * User::FONT_FAMILIES for the full reasoning on why that list is
 * fixed rather than free text).
 */
class ProfileController extends Controller
{
    /**
     * Mobile equivalent of the web app's ProfileController::update()
     * — same rules, same email-changes-clear-verification behavior.
     */
    public function updateProfile(Request $request): JsonResponse
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

        return response()->json(['data' => ['name' => $user->name, 'email' => $user->email]]);
    }

    /**
     * Mobile equivalent of the web app's ProfileController::updatePassword().
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Rules\Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);

        // A password/security change invalidates every previously trusted
        // biometric device. The user can enrol a device again after the next
        // successful password + OTP login.
        BiometricDevice::where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Password updated.']);
    }

    /**
     * Mobile equivalent of the web app's ProfileController::updateAvatar().
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'avatar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'avatar.mimes' => 'Profile picture must be a JPG, JPEG, PNG, or WebP image.',
            'avatar.max' => 'Profile picture must not be larger than 5 MB.',
        ]);

        $user = $request->user();
        $avatar = $data['avatar'];

        // Store the new file first. If storage fails, the user's current avatar
        // remains untouched instead of being deleted before a failed upload.
        $path = $avatar->store('avatars', 'public');

        if (!$path) {
            return response()->json(['message' => 'Profile picture could not be stored. Please try again.'], 500);
        }

        $oldPath = $user->avatar_path;
        $user->forceFill(['avatar_path' => $path])->save();

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        $user->refresh();

        return response()->json([
            'message' => 'Profile picture updated.',
            'data' => [
                'avatar_url' => $user->avatarUrl(),
                'avatar_path' => $user->avatar_path,
            ],
        ]);
    }

    /**
     * Stream the signed-in user's avatar through Sanctum instead of requiring
     * the public /storage symlink to be web-accessible.
     */
    public function avatarImage(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        $path = $user->avatar_path;

        abort_unless($path, 404, 'Profile picture not found.');

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404, 'Profile picture not found.');

        return response()->file($disk->path($path), [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Type' => $disk->mimeType($path) ?: 'image/jpeg',
        ]);
    }

    public function updateAppearance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'theme_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_color_secondary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'font_family' => ['nullable', 'string', 'in:' . implode(',', User::FONT_FAMILIES)],
            'font_size' => ['nullable', 'integer', 'min:80', 'max:130'],
        ]);

        $user = $request->user();
        // Only fields actually present in the request get touched —
        // mobile may send just one changed setting at a time (e.g.
        // only font_size), and a blanket "default absent fields to
        // null" here would incorrectly reset every OTHER appearance
        // setting back to default in that case.
        $updates = [];
        foreach (['theme_color', 'theme_color_secondary', 'font_family', 'font_size'] as $field) {
            if ($request->has($field)) {
                $updates[$field] = $data[$field] ?? null;
            }
        }
        $user->update($updates);

        return response()->json(['data' => [
            'theme_color' => $user->themeColor(),
            'theme_color_secondary' => $user->themeColorLight(),
            'font_family' => $user->fontFamily(),
            'font_size' => $user->fontSize(),
        ]]);
    }
}
