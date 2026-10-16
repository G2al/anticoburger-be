<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Models\Reservation;
use App\Support\BookingTime;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReservationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $timezone = BookingTime::timezone();

        return $schema
            ->columns(1)
            ->components([
                Section::make('Prenotazione')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('starts_at')
                            ->label('Data e ora')
                            ->dateTime('d/m/Y H:i', $timezone),
                        TextEntry::make('ends_at')
                            ->label('Fine prevista')
                            ->dateTime('d/m/Y H:i', $timezone),
                        TextEntry::make('occupied_until')
                            ->label('Tavolo occupato fino a')
                            ->dateTime('d/m/Y H:i', $timezone)
                            ->helperText('Include il tempo di riassetto.'),
                        TextEntry::make('party_size')
                            ->label('Persone'),
                        TextEntry::make('restaurantTable.name')
                            ->label('Tavolo'),
                        TextEntry::make('status')
                            ->label('Stato')
                            ->badge(),
                        TextEntry::make('created_via')
                            ->label('Origine')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('cancelled_at')
                            ->label('Annullata il')
                            ->dateTime('d/m/Y H:i', $timezone)
                            ->visible(fn (Reservation $record) => $record->isCancelled()),
                    ]),
                Section::make('Cliente')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('first_name')->label('Nome'),
                        TextEntry::make('last_name')->label('Cognome'),
                        TextEntry::make('phone')
                            ->label('Telefono')
                            ->helperText(fn (Reservation $record) => 'Normalizzato: '.$record->phone_normalized),
                        TextEntry::make('email')->label('Email')->placeholder('—'),
                        TextEntry::make('notes')->label('Note')->placeholder('—')->columnSpan(2),
                    ]),
                Section::make('Riferimenti')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('public_reference')->label('Riferimento pubblico')->copyable(),
                        TextEntry::make('created_at')->label('Creata il')->dateTime('d/m/Y H:i', $timezone),
                    ]),
            ]);
    }
}
