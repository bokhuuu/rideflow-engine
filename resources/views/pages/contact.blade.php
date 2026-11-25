@extends('layouts.ride')

@section('content')
    <a href="javascript:history.back()"
        class="inline-flex items-center gap-2 text-gray-400 hover:text-white text-sm mb-6 transition-colors">
        ← {{ __('pages.back') }}
    </a>

    <div class="py-8">
        <h1 class="text-2xl font-semibold mb-4">{{ __('pages.contact_title') }}</h1>
        <p class="text-gray-300 mb-8">{{ __('pages.contact_intro') }}</p>

        <div class="space-y-6">
            <div>
                <p class="text-gray-400 text-sm mb-1">{{ __('pages.contact_phone') }}</p>
                <p class="text-white font-medium">
                    {{ \App\Models\Setting::get('support_phone', __('pages.contact_phone_value')) }}
                </p>
            </div>
            <div>
                <p class="text-gray-400 text-sm mb-1">{{ __('pages.contact_email') }}</p>
                <p class="text-white font-medium">
                    {{ \App\Models\Setting::get('support_email', __('pages.contact_email_value')) }}
                </p>
            </div>
            <div>
                <p class="text-gray-400 text-sm mb-1">{{ __('pages.contact_address') }}</p>
                <p class="text-white font-medium">{{ __('pages.contact_address_value') }}</p>
            </div>
            <div>
                <p class="text-gray-400 text-sm mb-1">{{ __('pages.contact_hours') }}</p>
                <p class="text-white font-medium">{{ __('pages.contact_hours_value') }}</p>
            </div>
        </div>
    </div>
@endsection
