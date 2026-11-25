<?php

namespace App\Filament\Resources\Scooters;

use App\Filament\Resources\Scooters\Pages\CreateScooter;
use App\Filament\Resources\Scooters\Pages\EditScooter;
use App\Filament\Resources\Scooters\Pages\ListScooters;
use App\Filament\Resources\Scooters\Pages\ViewScooter;
use App\Filament\Resources\Scooters\Schemas\ScooterForm;
use App\Filament\Resources\Scooters\Schemas\ScooterInfolist;
use App\Filament\Resources\Scooters\Tables\ScootersTable;
use App\Models\Scooter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ScooterResource extends Resource
{
    protected static ?string $model = Scooter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ScooterForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ScooterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ScootersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScooters::route('/'),
            'create' => CreateScooter::route('/create'),
            'view' => ViewScooter::route('/{record}'),
            'edit' => EditScooter::route('/{record}/edit'),
        ];
    }
}
