@component('mail::message')
# Confirm Account Deletion

We received a request to delete your {{ $siteSettings->site_name ?? config('app.name') }} account and all of its data.

@component('mail::button', ['url' => $url])
Review and Confirm Deletion
@endcomponent

This link expires in 60 minutes. After you confirm, your account is scheduled for permanent deletion in 30 days. You can cancel during that time by signing in and opening **Privacy & Data**.

If you did not make this request, ignore this email. Your account will not be changed.

Thanks,<br>
{{ $siteSettings->site_name ?? config('app.name') }}
@endcomponent
