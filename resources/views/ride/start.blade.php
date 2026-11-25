@extends('layouts.ride')

@section('content')
    {{-- Pass PHP data to JS cleanly --}}
    <script>
        const packages = @json($packages->pluck('price', 'duration_minutes'));
        const unlockFee = {{ $unlockFee }};
    </script>

    {{-- Scooter identity --}}
    <div class="text-center mb-8">
        <div class="text-6xl mb-4">🛴</div>
        <h1 class="text-2xl font-semibold">{{ $scooter->name }}</h1>
    </div>

    {{-- Unlock fee notice --}}
    <p class="text-center text-gray-400 text-sm mb-6">
        {{ __('ride.unlock_fee') }}: <span class="text-white font-medium">{{ $unlockFee }}₾</span>
    </p>

    {{-- Package selection --}}
    <h2 class="text-lg font-medium mb-4">{{ __('ride.select_package') }}</h2>

    {{--
        This form POSTs to PaymentController::initiate().
        When user clicks "Start Ride", we submit the selected package_id.
        Laravel creates a Payment record, calls Flitt, redirects user to Flitt payment page.
    --}}
    <div x-data="{ selected: null, selectedId: null }">
        <form method="POST" action="{{ route('payment.initiate', ['imei' => $scooter->imei]) }}">
            @csrf

            {{-- Hidden input that holds the selected package_id for form submission --}}
            <input type="hidden" name="package_id" :value="selectedId">

            <div class="space-y-3">

                @foreach ($packages as $package)
                    {{--
                    On click: store duration_minutes for total price calculation (JS),
                    and store package id for form submission (hidden input).
                --}}
                    <button type="button"
                        @click="selected = {{ $package->duration_minutes }}; selectedId = {{ $package->id }}"
                        :class="selected === {{ $package->duration_minutes }} ? 'border-green-400 bg-green-400/10' :
                            'border-gray-700 bg-gray-900'"
                        class="w-full flex justify-between items-center p-4 rounded-xl border cursor-pointer transition-colors">
                        <span class="text-lg font-medium">
                            @if ($package->duration_minutes === 60)
                                1 {{ __('ride.hour') }}
                            @else
                                {{ $package->duration_minutes }} {{ __('ride.minutes') }}
                            @endif
                        </span>
                        <span class="text-lg font-semibold text-green-400">{{ $package->price }}₾</span>
                    </button>
                @endforeach

                {{-- Total --}}
                <div class="flex justify-between items-center pt-4 border-t border-gray-800 text-gray-300">
                    <span>{{ __('ride.total') }}</span>
                    <span x-text="selected ? (unlockFee + packages[selected]).toFixed(2) + '₾' : '—'"
                        class="font-semibold text-white">
                    </span>
                </div>

                {{-- Submit button - only visible when a package is selected --}}
                <button type="submit" x-show="selected !== null" x-cloak
                    class="w-full mt-4 bg-green-500 hover:bg-green-400 text-black font-bold py-4 rounded-xl text-lg transition-colors cursor-pointer">
                    {{ __('ride.start_ride') }}
                </button>

            </div>
        </form>

        {{-- VISA/MC logos - required by Flitt --}}
        <div class="flex justify-center items-center gap-6 mt-8">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 750 471" class="h-8 opacity-80">
                <rect width="750" height="471" rx="40" fill="#1A1F71" />
                <text x="375" y="300" text-anchor="middle" font-family="Arial" font-weight="bold" font-size="240"
                    fill="white" letter-spacing="-10">VISA</text>
            </svg>

            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 152 95" class="h-8 opacity-80">
                <circle cx="52" cy="47.5" r="47.5" fill="#EB001B" />
                <circle cx="100" cy="47.5" r="47.5" fill="#F79E1B" />
                <path d="M76 15.5a47.4 47.4 0 0 1 0 64 47.4 47.4 0 0 1 0-64z" fill="#FF5F00" />
            </svg>
        </div>
    </div>

    {{-- Legal links - required by Flitt to be visible before payment --}}
    <div class="flex flex-col items-center gap-3 mt-8 text-sm text-gray-500">
        <a href="{{ route('pages.terms') }}" class="hover:text-gray-300 transition-colors">
            {{ __('pages.footer_terms') }}
        </a>
        <a href="{{ route('pages.privacy') }}" class="hover:text-gray-300 transition-colors">
            {{ __('pages.footer_privacy') }}
        </a>
        <a href="{{ route('pages.contact') }}" class="hover:text-gray-300 transition-colors">
            {{ __('pages.footer_contact') }}
        </a>
    </div>
@endsection
