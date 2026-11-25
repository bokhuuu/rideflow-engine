@extends('layouts.ride')

@section('content')
    <div x-data="rideTimer({{ $ride->package_minutes }})" x-init="start()">

        {{-- Geofence violation banner --}}
        <div x-show="zoneViolated" x-transition
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-90 p-6">
            <div class="text-center">
                <div class="text-6xl mb-6">🚫</div>
                <h2 class="text-2xl font-bold text-red-400 mb-3">
                    {{ __('ride.left_riding_area') }}
                </h2>
                <p class="text-gray-300 text-base">
                    {{ __('ride.scooter_stopped') }}
                </p>
            </div>
        </div>

        {{-- Header --}}
        <div class="text-center mb-10">
            <div class="text-6xl mb-4">🛴</div>
            <h1 class="text-xl font-semibold text-gray-300">{{ __('ride.ride_in_progress') }}</h1>
        </div>

        {{-- Countdown timer --}}
        <div class="text-center mb-10">
            <div x-text="display" class="text-7xl font-bold tabular-nums tracking-tight"></div>
            <p class="text-gray-400 mt-2 text-sm">{{ __('ride.time_remaining') }}</p>
        </div>

        {{-- Cost --}}
        <div class="flex justify-between items-center bg-gray-900 rounded-xl p-4 mb-8">
            <span class="text-gray-400">{{ __('ride.cost') }}</span>
            <span class="text-xl font-bold text-green-400">{{ $ride->cost }}₾</span>
        </div>

        {{-- Actions --}}
        <div class="space-y-3">

            {{-- Beep --}}
            <div>
                <button @click="beep()" :disabled="beeping" :class="beeping ? 'opacity-50 cursor-not-allowed' : ''"
                    class="w-full bg-gray-800 hover:bg-gray-700 py-4 rounded-xl font-medium transition-colors flex items-center justify-center gap-2">
                    {{-- Spinner — only visible while request is in flight --}}
                    <svg x-show="beeping" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>

                    {{-- Label changes while beeping --}}
                    <span x-text="beeping ? '{{ __('ride.beeping') }}' : '📍 {{ __('ride.find_scooter') }}'"></span>
                </button>

                {{-- Success flash --}}
                <p x-show="beepResult === 'success'" x-transition class="text-center text-green-400 text-sm mt-2">
                    ✓ {{ __('ride.beep_sent') }}
                </p>

                {{-- Error flash --}}
                <p x-show="beepResult === 'error'" x-transition class="text-center text-red-400 text-sm mt-2">
                    ✗ {{ __('ride.beep_failed') }}
                </p>
            </div>

            {{-- End ride --}}
            <button @click="endRide()"
                class="w-full bg-red-600 hover:bg-red-500 py-4 rounded-xl font-bold text-lg transition-colors">
                🔒 {{ __('ride.end_ride') }}
            </button>

        </div>

    </div>

    <script>
        function rideTimer(packageMinutes) {
            return {
                seconds: packageMinutes * 60,
                display: '',
                beeping: false,
                beepResult: null,
                zoneViolated: false,
                pollInterval: null,

                start() {
                    // Restore timer from localStorage if page was reopened
                    const saved = JSON.parse(localStorage.getItem('rideflow_active') || '{}');
                    if (saved.started_at) {
                        const elapsed = Math.floor((Date.now() - new Date(saved.started_at).getTime()) / 1000);
                        this.seconds = Math.max(0, (packageMinutes * 60) - elapsed);
                    }

                    this.tick();

                    // Countdown timer - ticks every second
                    const interval = setInterval(() => {
                        if (this.seconds <= 0) {
                            clearInterval(interval);
                            this.endRide();
                            return;
                        }
                        this.seconds--;
                        this.tick();
                    }, 1000);

                    // Status polling - checks Laravel every 5 seconds
                    // If zone_violated_at is set → show violation banner
                    this.pollInterval = setInterval(() => {
                        this.checkStatus();
                    }, 5000);
                },

                checkStatus() {
                    // Poll the active ride endpoint every 5 seconds
                    // zone_violated_at being set means scooter crossed the boundary
                    axios.get('/api/ride/{{ $scooter->imei }}/active')
                        .then(response => {
                            if (response.data.zone_violated_at) {
                                // Violation confirmed - show banner, stop polling
                                this.zoneViolated = true;
                                clearInterval(this.pollInterval);
                            }
                        })
                        .catch(() => {
                            // Ride ended or network error - stop polling
                            clearInterval(this.pollInterval);
                        });
                },

                tick() {
                    const m = Math.floor(this.seconds / 60).toString().padStart(2, '0');
                    const s = (this.seconds % 60).toString().padStart(2, '0');
                    this.display = `${m}:${s}`;
                },

                async beep() {
                    if (this.beeping) return;

                    this.beeping = true;
                    this.beepResult = null;

                    try {
                        await axios.post('/api/ride/{{ $scooter->imei }}/beep');

                        this.beepResult = 'success';

                    } catch (e) {
                        this.beepResult = 'error';

                    } finally {
                        this.beeping = false;

                        setTimeout(() => {
                            this.beepResult = null;
                        }, 3000);
                    }
                },

                endRide() {
                    axios.post('/api/ride/{{ $scooter->imei }}/end')
                        .then(() => {
                            localStorage.removeItem('rideflow_active');
                            clearInterval(this.pollInterval);
                            window.location.href = '/ride/{{ $scooter->imei }}/summary';
                        });
                },
            }
        }
    </script>
@endsection
