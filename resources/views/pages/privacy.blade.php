@extends('layouts.ride')

@section('content')
    <a href="javascript:history.back()"
        class="inline-flex items-center gap-2 text-gray-400 hover:text-white text-sm mb-6 transition-colors">
        ← {{ __('pages.back') }}
    </a>

    <div class="py-8">
        <h1 class="text-2xl font-semibold mb-8">{{ __('pages.privacy_title') }}</h1>

        <p class="text-gray-300 mb-8">{{ __('pages.privacy_intro') }}</p>

        <h2 class="text-lg font-medium mb-2">{{ __('pages.privacy_collect') }}</h2>
        <p class="text-gray-300 mb-8">{{ __('pages.privacy_collect_body') }}</p>

        <h2 class="text-lg font-medium mb-2">{{ __('pages.privacy_use') }}</h2>
        <p class="text-gray-300 mb-8">{{ __('pages.privacy_use_body') }}</p>

        <h2 class="text-lg font-medium mb-2">{{ __('pages.privacy_retention') }}</h2>
        <p class="text-gray-300 mb-8">{{ __('pages.privacy_retention_body') }}</p>
    </div>
@endsection
