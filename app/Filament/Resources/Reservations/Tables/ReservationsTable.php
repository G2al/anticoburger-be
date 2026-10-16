<?php

namespace App\Filament\Resources\Reservations\Tables;

use App\Enums\ReservationChannel;
use App\Enums\ReservationStatus;
use App\Filament\Resources\Reservations\ReservationActions;
use App\Models\AvailabilityBlock;
use App\Models\Reservation;
use App\Services\Reservations\AvailabilityBlockService;
use App\Support\BookingTime;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReservationsTable
{
    public static function configure(Table $table): Table
    {
        $timezone = BookingTime::timezone();

        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('public_reference')
                    ->label('Riferimento')
                    ->limit(8)
                    ->tooltip(fn (Reservation $record) => $record->public_reference)
                    ->copyable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('last_name')
                    ->label('Cliente')
                    ->formatStateUsing(fn (Reservation $record) => $record->fullName())
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['last_name', 'first_name']),
                TextColumn::make('phone')
                    ->label('Telefono')
                    ->searchable(['phone', 'phone_normalized']),
                TextColumn::make('party_size')
                    ->label('Persone')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('restaurantTable.name')
                    ->label('Tavolo')
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label('Data')
                    ->date('d/m/Y', $timezone)
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Ora')
                    ->state(fn (Reservation $record) => $record->localStartsAt()->format('H:i')),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge(),
                TextColumn::make('created_via')
                    ->label('Origine')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->label('Creata il')
                    ->dateTime('d/m/Y H:i', $timezone)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('date')
                    ->label('Data')
                    ->schema([
                        DatePicker::make('from')->label('Dal')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label('Al')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data) => self::filterByLocalDates($query, $data['from'] ?? null, $data['until'] ?? null))
                    ->indicateUsing(fn (array $data) => self::dateIndicator($data['from'] ?? null, $data['until'] ?? null)),
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options(ReservationStatus::class),
                SelectFilter::make('restaurant_table_id')
                    ->label('Tavolo')
                    ->relationship('restaurantTable', 'name'),
                SelectFilter::make('created_via')
                    ->label('Origine')
                    ->options(ReservationChannel::class),
                Filter::make('block')
                    ->label('Coinvolte in un blocco')
                    ->schema([
                        Select::make('availability_block_id')
                            ->label('Coinvolte nel blocco')
                            ->options(fn () => AvailabilityBlock::query()->latest('starts_on')->pluck('title', 'id')->all())
                            ->searchable(),
                    ])
                    ->query(fn (Builder $query, array $data) => self::filterByBlock($query, $data['availability_block_id'] ?? null))
                    ->indicateUsing(fn (array $data) => filled($data['availability_block_id'] ?? null)
                        ? 'Blocco: '.AvailabilityBlock::query()->find($data['availability_block_id'])?->title
                        : null),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    ReservationActions::moveToTable(),
                    ReservationActions::cancel(),
                    ReservationActions::delete(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ReservationActions::deleteSelected(),
                ]),
            ]);
    }

    private static function filterByLocalDates(Builder $query, ?string $from, ?string $until): Builder
    {
        return $query
            ->when($from, fn (Builder $query) => $query->where('starts_at', '>=', BookingTime::localDayStart(BookingTime::date(substr($from, 0, 10)))))
            ->when($until, fn (Builder $query) => $query->where('starts_at', '<', BookingTime::localDayStart(BookingTime::date(substr($until, 0, 10))->addDay())));
    }

    private static function dateIndicator(?string $from, ?string $until): ?string
    {
        $format = fn (string $date) => BookingTime::date(substr($date, 0, 10))->format('d/m/Y');

        return match (true) {
            filled($from) && filled($until) => 'Dal '.$format($from).' al '.$format($until),
            filled($from) => 'Dal '.$format($from),
            filled($until) => 'Fino al '.$format($until),
            default => null,
        };
    }

    private static function filterByBlock(Builder $query, mixed $blockId): Builder
    {
        $block = filled($blockId) ? AvailabilityBlock::query()->find($blockId) : null;

        if ($block === null) {
            return $query;
        }

        return $query->whereKey(app(AvailabilityBlockService::class)->affectedReservations($block)->modelKeys());
    }
}
