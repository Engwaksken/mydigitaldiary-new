<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;

/**
 * User-facing feedback submission. Deliberately does NOT include 'status'
 * or 'admin_notes' in $fields - those are admin-only (see
 * Admin\AdminFeedbackController) and CrudController's generic form always
 * renders whatever is in $fields as an editable control, so keeping them
 * out entirely is what actually prevents a user from setting their own
 * feedback to "resolved". Users see their overall status breakdown via the
 * stats cards (New/Reviewed/Resolved counts) rather than a per-row column,
 * since there's no "read-only column" concept in the shared CRUD table.
 */
class FeedbackController extends CrudController
{
    protected string $model = Feedback::class;
    protected string $routeName = 'feedback';
    protected string $title = 'Feedback';
    protected string $icon = 'fa-solid fa-comment-dots';
    protected string $accent = 'slate';

    protected array $fields = [
        ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'required' => true, 'options' => [
            'bug' => 'Bug Report', 'feature_request' => 'Feature Request', 'general' => 'General',
            'complaint' => 'Complaint', 'compliment' => 'Compliment',
        ]],
        ['name' => 'subject', 'label' => 'Subject', 'type' => 'text', 'required' => true, 'placeholder' => 'A short summary'],
        ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true],
        ['name' => 'rating', 'label' => 'Rating (1-5, optional)', 'type' => 'number'],
    ];

    protected array $rules = [
        'category' => 'required|in:bug,feature_request,general,complaint,compliment',
        'subject' => 'required|string|max:255',
        'message' => 'required|string',
        'rating' => 'nullable|integer|min:1|max:5',
    ];

    protected function stats(Request $request): array
    {
        $userId = $request->user()->id;
        $base = Feedback::where('user_id', $userId);

        return [
            ['label' => 'New', 'value' => (string) (clone $base)->where('status', 'new')->count(), 'icon' => 'fa-solid fa-inbox', 'color' => 'slate'],
            ['label' => 'Reviewed', 'value' => (string) (clone $base)->where('status', 'reviewed')->count(), 'icon' => 'fa-solid fa-eye', 'color' => 'amber'],
            ['label' => 'Resolved', 'value' => (string) (clone $base)->where('status', 'resolved')->count(), 'icon' => 'fa-solid fa-circle-check', 'color' => 'emerald'],
        ];
    }

    protected function chart(Request $request): ?array
    {
        $userId = $request->user()->id;
        $counts = Feedback::where('user_id', $userId)
            ->selectRaw('category, COUNT(*) as total')->groupBy('category')->pluck('total', 'category');

        if ($counts->isEmpty()) {
            return null;
        }

        return [
            'type' => 'doughnut',
            'title' => 'Your Feedback by Category',
            'labels' => $counts->keys()->map(fn ($l) => ucwords(str_replace('_', ' ', $l)))->all(),
            'datasets' => [['data' => $counts->values()->all()]],
        ];
    }
}
