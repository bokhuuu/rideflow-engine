<?php

namespace App\Forms\Components;

use Filament\Forms\Components\Field;

/**
 * Custom Filament form field that renders an interactive Leaflet.js map
 * with polygon drawing tools for defining zone boundaries.
 */
class LeafletPolygonField extends Field
{
    protected float $centerLat;
    protected float $centerLng;
    protected int $zoom;

    /**
     * Set default map center from config so it's deployment-configurable via .env.
     * Called automatically by Filament when the field is instantiated.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->view('forms.components.leaflet-polygon');

        $this->centerLat = (float) config('scooter.map_center_lat', 41.9969);
        $this->centerLng = (float) config('scooter.map_center_lng', 41.7781);
        $this->zoom = (int) config('scooter.map_zoom', 13);
    }

    public function centerLat(float $lat): static
    {
        $this->centerLat = $lat;
        return $this;
    }

    public function centerLng(float $lng): static
    {
        $this->centerLng = $lng;
        return $this;
    }

    public function zoom(int $zoom): static
    {
        $this->zoom = $zoom;
        return $this;
    }

    /**
     * Pass centerLat, centerLng and zoom into the blade view as variables.
     * Filament 5 calls this automatically - blade accesses them as $centerLat etc.
     */
    public function getViewData(): array
    {
        return [
            'centerLat' => $this->centerLat,
            'centerLng' => $this->centerLng,
            'zoom' => $this->zoom,
        ];
    }
}
