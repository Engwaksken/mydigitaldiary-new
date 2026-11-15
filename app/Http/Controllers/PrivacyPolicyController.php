<?php

namespace App\Http\Controllers;

class PrivacyPolicyController extends Controller
{
    /**
     * Public - no auth required. Linked from the registration consent
     * checkbox, so it must be reachable by people who don't have an
     * account yet.
     */
    public function show()
    {
        return view('privacy-policy');
    }
}
