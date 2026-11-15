<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Static FAQ covering the app's main modules, plus a pointer to the
 * Feedback module for anything not answered here. Kept as plain PHP
 * data rather than a database table - there's no admin need to edit
 * this on the fly, and a hardcoded array is one less thing that can
 * silently end up empty on a fresh install.
 */
class HelpController extends Controller
{
    public function show(): View
    {
        return view('help.show', ['faqs' => $this->faqs()]);
    }

    private function faqs(): array
    {
        return [
            'Getting Started' => [
                'How do I get started?' => 'After creating your account and verifying your email, you land on your dashboard. Use the sidebar (or the menu on mobile) to reach any module - Expenses, Income, Meetings, and so on.',
                'Is my data private?' => 'Yes - everything you track is scoped to your own account. Nobody else, including other users on a Family/Team or Enterprise plan, can see your personal tracking data. See the Privacy Policy for full details.',
            ],
            'Tracking & Modules' => [
                'How do reminders work?' => "Set a reminder with a schedule (once, daily, weekly, etc.) and choose how you're notified - email, in-app, or both. Reminders always show up in your Notifications list regardless of which delivery method you choose.",
                'What is the AI Planner?' => 'It generates a personalized plan based on your existing tracked data. You can generate a new one anytime, download any past plan as a PDF, or delete ones you no longer need.',
                'How do I sign a document?' => 'Upload a PDF or image from the Signatures module, place your saved signature wherever needed, and download the signed result. On mobile, you can view and download signed documents, but the full signing editor is web-only.',
            ],
            'Subscription & Billing' => [
                'What plans are available?' => 'Individual plans for one person, Family & Small Team for a handful of people, and Enterprise for larger organizations - Enterprise pricing is handled through a quick form rather than self-serve checkout, since it varies by team size and needs.',
                'How do I manage my team?' => "If you're on a Family & Team or Enterprise plan, use the Organization page to invite, activate, deactivate, or remove members, right from your account.",
                'What happens if someone leaves my organization?' => 'Their seat is freed immediately, and their access to organization-owned resources is revoked. Their own personal tracking data stays with them - they can verify a personal email and continue using the app under a Free or Individual plan.',
            ],
        ];
    }
}
