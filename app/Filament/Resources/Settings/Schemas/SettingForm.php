<?php

namespace App\Filament\Resources\Settings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->disabled(),
                TextInput::make('key')
                    ->disabled(),
                Select::make('type')
                    ->options([
                        'string' => 'String',
                        'float' => 'Float',
                        'integer' => 'Integer',
                        'boolean' => 'Boolean',
                    ])
                    ->disabled(),
                TextInput::make('value')
                    ->required(),
            ]);
    }
}
