<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin review queue for user-submitted feedback - status changes and
 * admin-only notes. Reading feedback message content is the one
 * intentional exception to this app's "admins never see personal tracked
 * data" boundary (see AdminUserController's boundary note) - feedback is
 * explicitly submitted TO the platform's operators, not personal data
 * being tracked about the user's own life, so it's a different category.
 */
class AdminFeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $items = Feedback::with('user')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.feedback.index', compact('items', 'status'));
    }

    public function update(Request $request, Feedback $feedback): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:new,reviewed,resolved'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $feedback->update($data);

        return back()->with('success', 'Feedback updated.');
    }

    public function destroy(Feedback $feedback): RedirectResponse
    {
        $feedback->delete();

        return back()->with('success', 'Feedback removed.');
    }
}
