@extends('layouts.ride')

@section('content')
    <a href="javascript:history.back()"
        class="inline-flex items-center gap-2 text-gray-400 hover:text-white text-sm mb-6 transition-colors">
        ← {{ __('pages.back') }}
    </a>

    <div class="py-8">
        <h1 class="text-2xl font-semibold mb-2">{{ __('pages.terms_title') }}</h1>
        <p class="text-gray-400 text-sm mb-1">{{ __('pages.terms_company') }}</p>
        <p class="text-gray-400 text-sm mb-8">{{ __('pages.terms_id') }}</p>

        <p class="text-gray-300 mb-8">{{ __('pages.terms_intro') }}</p>

        <h2 class="text-lg font-medium mb-2">{{ __('pages.terms_usage') }}</h2>
        <p class="text-gray-300 mb-8">{{ __('pages.terms_usage_body') }}</p>

        <h2 class="text-lg font-medium mb-2">{{ __('pages.terms_payment') }}</h2>
        <p class="text-gray-300 mb-8">{{ __('pages.terms_payment_body') }}</p>

        <h2 class="text-lg font-medium mb-2">{{ __('pages.terms_liability') }}</h2>
        <p class="text-gray-300 mb-8">{{ __('pages.terms_liability_body') }}</p>
    </div>
@endsection
