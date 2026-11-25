@extends('layouts.ride')

@section('content')
    <div class="flex flex-col items-center justify-center text-center" style="min-height: calc(100vh - 200px);">
        <div class="text-7xl mb-8">🛴</div>
        <h1 class="text-2xl font-semibold mb-6">{{ __('pages.home_title') }}</h1>
        <p id="subtitle" class="font-medium text-lg">{{ __('pages.home_subtitle') }}</p>
    </div>

    <style>
        #subtitle {
            animation: colorCycle 3s ease-in-out infinite;
        }

        @keyframes colorCycle {
            0% {
                color: #14532d;
            }

            25% {
                color: #16a34a;
            }

            50% {
                color: #4ade80;
            }

            100% {
                color: #14532d;
            }
        }
    </style>
@endsection
