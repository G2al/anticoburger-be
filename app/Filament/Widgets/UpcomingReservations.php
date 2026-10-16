<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Reservations\ReservationResource;
use App\Models\Reservation;
use App\Support\BookingTime;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingReservations extends TableWidget
{
    private const LIMIT = 15;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Prossime prenotazioni';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Reservation::query()
                ->confirmed()
                ->with('restaurantTable')
                ->where('starts_at', '>=', CarbonImmutable::now('UTC'))
                ->orderBy('starts_at')
                ->limit(self::LIMIT))
            ->paginated(false)
            ->recordUrl(fn (Reservation $record) => ReservationResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Nessuna prenotazione in arrivo')
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Data e ora')
                    ->dateTime('D d/m H:i', BookingTime::timezone()),
                TextColumn::make('last_name')
                    ->label('Cliente')
                    ->formatStateUsing(fn (Reservation $record) => $record->fullName()),
                TextColumn::make('party_size')
                    ->label('Persone')
                    ->alignCenter(),
                TextColumn::make('restaurantTable.name')
                    ->label('Tavolo'),
                TextColumn::make('phone')
                    ->label('Telefono'),
                TextColumn::make('notes')
                    ->label('Note')
                    ->limit(40)
                    ->placeholder('—'),
            ]);
    }
}
