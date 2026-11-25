@extends('layouts.ride')

@section('content')
    <div class="text-center">

        {{-- Success icon --}}
        <div class="text-7xl mb-6">✅</div>

        <h1 class="text-2xl font-bold mb-2">{{ __('ride.ride_ended') }}</h1>
        <p class="text-gray-400 mb-10">{{ __('ride.thank_you') }}</p>

        {{-- Ride summary --}}
        <div class="bg-gray-900 rounded-2xl p-6 text-left space-y-4">

            <div class="flex justify-between items-center">
                <span class="text-gray-400">{{ __('ride.total_duration') }}</span>
                <span class="font-semibold">{{ $ride->duration_minutes }} {{ __('ride.minutes') }}</span>
            </div>

            <div class="flex justify-between items-center border-t border-gray-800 pt-4">
                <span class="text-gray-400">{{ __('ride.total_cost') }}</span>
                <span class="text-xl font-bold text-green-400">{{ $ride->cost }}₾</span>
            </div>

        </div>

    </div>
@endsection
