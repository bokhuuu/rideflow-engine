<?php

namespace App\Filament\Resources\Zones\Schemas;

use App\Forms\Components\LeafletPolygonField;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Zone Information')
                    ->description('Basic details about this operating zone.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Zone Name')
                            ->placeholder('e.g. Ureki Operating Zone')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Select::make('type')
                            ->label('Zone Type')
                            ->options([
                                'operating' => 'Operating Zone (scooters must stay inside)',
                            ])
                            ->default('operating')
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(3),

                Section::make('Zone Boundary')
                    ->description('Draw the polygon that defines this zone on the map.')
                    ->schema([
                        LeafletPolygonField::make('coordinates')
                            ->label('Draw Zone Boundary')
                            ->required(),
                    ]),

                Section::make('Display & Status')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Only active zones are enforced during rides.')
                            ->default(true)
                            ->columnSpan(1),
                    ])
                    ->columns(2),
            ]);
    }
}
