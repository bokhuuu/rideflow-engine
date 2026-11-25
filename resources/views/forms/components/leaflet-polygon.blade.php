@php
    $state = $getState();
    $statePath = $getStatePath();
    $mapId = 'leaflet-map-' . str_replace('.', '-', $getId());
@endphp

@once
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" crossorigin="" />
@endonce

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div wire:ignore x-data="{
        map: null,
        drawnItems: null,
        init() {
            if (this.map) {
                this.map.remove();
                this.map = null;
                this.drawnItems = null;
            }
            this.map = L.map('{{ $mapId }}').setView([{{ $centerLat }}, {{ $centerLng }}], {{ $zoom }});
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: 'OpenStreetMap',
                maxZoom: 19,
            }).addTo(this.map);
            this.drawnItems = new L.FeatureGroup();
            this.map.addLayer(this.drawnItems);
            const drawControl = new L.Control.Draw({
                edit: { featureGroup: this.drawnItems, remove: true },
                draw: {
                    polygon: { allowIntersection: false, showArea: true, shapeOptions: { color: '#3B82F6', fillOpacity: 0.2 } },
                    rectangle: false,
                    circle: false,
                    circlemarker: false,
                    marker: false,
                    polyline: false,
                },
            });
            this.map.addControl(drawControl);
            const existingCoords = {{ json_encode($state ?? []) }};
            if (existingCoords && existingCoords.length >= 3) {
                const polygon = L.polygon(existingCoords, { color: '#3B82F6', fillOpacity: 0.2 });
                this.drawnItems.addLayer(polygon);
                this.map.fitBounds(polygon.getBounds(), { padding: [20, 20] });
            }
            this.map.on(L.Draw.Event.CREATED, (event) => {
                this.drawnItems.clearLayers();
                this.drawnItems.addLayer(event.layer);
                this.saveCoordinates();
            });
            this.map.on(L.Draw.Event.EDITED, () => { this.saveCoordinates(); });
            this.map.on(L.Draw.Event.DELETED, () => { this.$wire.set('{{ $statePath }}', null); });
        },
        saveCoordinates() {
            const coords = [];
            this.drawnItems.eachLayer((layer) => {
                if (layer instanceof L.Polygon) {
                    layer.getLatLngs()[0].forEach((latlng) => { coords.push([latlng.lat, latlng.lng]); });
                }
            });
            this.$wire.set('{{ $statePath }}', coords);
        },
    }" x-init="init()">
        <div id="{{ $mapId }}"
            style="height: 450px; width: 100%; border-radius: 0.5rem; border: 1px solid #e5e7eb;"></div>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            Click the polygon tool in the top-left toolbar to start drawing.
            Click each corner of the zone, then click the first point to close the shape.
        </p>
    </div>
</x-dynamic-component>

@once
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js" crossorigin=""></script>
@endonce
