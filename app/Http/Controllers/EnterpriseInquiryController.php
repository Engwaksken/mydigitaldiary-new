<?php

namespace App\Http\Controllers;

use App\Mail\EnterpriseInquiryReceivedMail;
use App\Models\EnterpriseInquiry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * "Contact Sales" for the Enterprise tier - a sales-assisted lead, not a
 * self-serve purchase. No payment happens here; an admin follows up
 * manually and, if the deal closes, assigns the customer to one of the
 * existing 'organization'-category plans themselves (see Admin ->
 * Subscription Plans) - those plans still exist for that purpose.
 */
class EnterpriseInquiryController extends Controller
{
    public function show(Request $request): View
    {
        return view('enterprise.contact', [
            'user' => $request->user(),
            'countries' => config('countries'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'about' => ['required', 'string', 'max:2000'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'country' => ['required', 'string', 'in:' . implode(',', config('countries'))],
            'employee_count' => ['required', 'string', 'max:50'],
        ]);

        $inquiry = EnterpriseInquiry::create([
            'user_id' => $request->user()?->id,
            'about' => $data['about'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'country' => $data['country'],
            'employee_count' => $data['employee_count'],
        ]);

        foreach (User::where('role', 'admin')->get() as $admin) {
            Mail::to($admin->email)->send(new EnterpriseInquiryReceivedMail($inquiry));
        }

        return redirect()->route('enterprise.contact')->with('success', "Thanks - we've received your details and someone from our team will be in touch shortly.");
    }
}
