<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Services\Reservations\AvailabilityService;
use App\Services\Reservations\PhoneNumberNormalizer;
use App\Support\BookingTime;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Throwable;

/**
 * Form data uses local "date" and "time" fields; ReservationService converts them to UTC.
 */
class ReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Prenotazione')
                    ->description('Il tavolo viene assegnato automaticamente al più adatto; puoi sceglierne uno compatibile manualmente. Ogni modifica di data, ora, persone o tavolo ricontrolla la disponibilità.')
                    ->columns(4)
                    ->disabled(fn (?Reservation $record) => $record?->isCancelled() ?? false)
                    ->schema([
                        DatePicker::make('date')
                            ->label('Data')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->format('Y-m-d')
                            ->live(),
                        TimePicker::make('time')
                            ->label('Ora')
                            ->required()
                            ->seconds(false)
                            ->format('H:i')
                            ->live(onBlur: true),
                        TextInput::make('party_size')
                            ->label('Persone')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue((int) config('booking.max_party_size'))
                            ->live(onBlur: true),
                        Select::make('restaurant_table_id')
                            ->label('Tavolo')
                            ->placeholder('Assegnazione automatica')
                            ->options(fn (Get $get, ?Reservation $record) => self::tableOptions($get, $record)),
                        Text::make(fn (Get $get, ?Reservation $record) => self::freeTablesHint($get, $record))
                            ->columnSpanFull(),
                    ]),
                Section::make('Cliente')
                    ->columns(2)
                    ->schema([
                        TextInput::make('first_name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('last_name')
                            ->label('Cognome')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('phone')
                            ->label('Telefono')
                            ->tel()
                            ->required()
                            ->maxLength(30)
                            ->rule(fn (): Closure => self::validPhoneNumber()),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(190),
                        Textarea::make('notes')
                            ->label('Note')
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillFromRecord(Reservation $reservation, array $data): array
    {
        $startsAt = $reservation->localStartsAt();

        return [
            ...$data,
            'date' => $startsAt->toDateString(),
            'time' => $startsAt->format('H:i'),
        ];
    }

    /**
     * Active tables accepting the party size, plus the current table of the record.
     *
     * @return array<int, string>
     */
    private static function tableOptions(Get $get, ?Reservation $record): array
    {
        $partySize = (int) $get('party_size');

        return RestaurantTable::query()
            ->where(fn ($query) => $query
                ->where(fn ($compatible) => $compatible
                    ->active()
                    ->when($partySize > 0, fn ($sized) => $sized->acceptingPartySize($partySize)))
                ->when($record !== null, fn ($current) => $current->orWhere('id', $record->restaurant_table_id)))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function freeTablesHint(Get $get, ?Reservation $record): string
    {
        $date = $get('date');
        $time = $get('time');
        $partySize = (int) $get('party_size');

        if (blank($date) || blank($time) || $partySize < 1) {
            return 'Indica data, ora e persone per vedere i tavoli liberi.';
        }

        try {
            $startsAt = BookingTime::at(BookingTime::date(substr((string) $date, 0, 10)), (string) $time);
        } catch (Throwable) {
            return '';
        }

        $freeTables = app(AvailabilityService::class)->freeTablesAt($startsAt, $partySize, $record);

        if ($freeTables->isEmpty()) {
            return 'Nessun tavolo compatibile è libero e prenotabile in questo orario (verifica fasce, blocchi e prenotazioni).';
        }

        return 'Tavoli liberi (dal più adatto): '.$freeTables->pluck('name')->join(', ').'.';
    }

    private static function validPhoneNumber(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || app(PhoneNumberNormalizer::class)->normalize($value) === null) {
                $fail('Il numero di telefono non è valido.');
            }
        };
    }
}
