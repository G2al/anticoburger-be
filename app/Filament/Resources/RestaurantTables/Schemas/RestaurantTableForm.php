<?php

namespace App\Filament\Resources\RestaurantTables\Schemas;

use App\Models\RestaurantTable;
use App\Models\TableSchedule;
use App\Services\Reservations\RestaurantTableService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * The weekly schedule is edited as one repeater per day ("schedule_1" ... "schedule_7").
 * The pages convert it from/to the flat "schedules" list expected by RestaurantTableService.
 */
class RestaurantTableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Dati del tavolo')
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(2),
                        Toggle::make('is_active')
                            ->label('Attivo')
                            ->helperText('Un tavolo disattivato non accetta nuove prenotazioni; quelle esistenti restano.')
                            ->default(true)
                            ->inline(false),
                        TextInput::make('duration_minutes')
                            ->label('Durata prenotazione')
                            ->suffix('minuti')
                            ->numeric()
                            ->required()
                            ->minValue(RestaurantTableService::MIN_DURATION_MINUTES)
                            ->maxValue(RestaurantTableService::MAX_DURATION_MINUTES),
                        TextInput::make('slot_interval_minutes')
                            ->label('Intervallo tra gli orari')
                            ->helperText('Ogni quanti minuti viene proposto un orario di inizio.')
                            ->suffix('minuti')
                            ->numeric()
                            ->required()
                            ->minValue(RestaurantTableService::MIN_SLOT_INTERVAL_MINUTES)
                            ->maxValue(RestaurantTableService::MAX_DURATION_MINUTES),
                        TextInput::make('turnaround_minutes')
                            ->label('Tempo di riassetto')
                            ->helperText('Minuti dopo la fine in cui il tavolo resta occupato.')
                            ->suffix('minuti')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(RestaurantTableService::MAX_TURNAROUND_MINUTES),
                        TextInput::make('assignment_priority')
                            ->label('Priorità di assegnazione')
                            ->helperText('A parità di capienza viene scelto prima il tavolo con il valore più basso.')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(RestaurantTableService::MAX_ASSIGNMENT_PRIORITY),
                    ]),
                Section::make('Capienze ammesse')
                    ->description('Numeri di persone accettati dal tavolo. Non devono essere consecutivi (es. 2, 4, 6).')
                    ->schema([
                        CheckboxList::make('party_sizes')
                            ->hiddenLabel()
                            ->options(self::partySizeOptions())
                            ->columns(10)
                            ->gridDirection('row')
                            ->requiredIf('is_active', true)
                            ->validationMessages(['required_if' => 'Un tavolo attivo deve avere almeno una capienza ammessa.']),
                    ]),
                Section::make('Programmazione settimanale')
                    ->description("Per ogni giorno indica una o più fasce. L'ultimo orario è l'ultimo orario di INIZIO prenotazione (incluso). Un giorno senza fasce non è prenotabile.")
                    ->schema([
                        Tabs::make('weekly_schedule')
                            ->tabs(array_map(
                                fn (int $day) => Tab::make(TableSchedule::DAY_NAMES[$day])
                                    ->badge(fn (Get $get) => count($get('schedule_'.$day) ?? []) ?: null)
                                    ->schema([self::dayRepeater($day)]),
                                array_keys(TableSchedule::DAY_NAMES),
                            )),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toServiceData(array $data): array
    {
        $schedules = [];

        foreach (array_keys(TableSchedule::DAY_NAMES) as $day) {
            foreach ($data['schedule_'.$day] ?? [] as $range) {
                $schedules[] = [
                    'day_of_week' => $day,
                    'first_booking_time' => $range['first_booking_time'] ?? null,
                    'last_booking_time' => $range['last_booking_time'] ?? null,
                ];
            }

            unset($data['schedule_'.$day]);
        }

        $data['schedules'] = $schedules;
        $data['party_sizes'] = array_map('intval', $data['party_sizes'] ?? []);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillFromRecord(RestaurantTable $table, array $data): array
    {
        $table->loadMissing(['allowedPartySizes', 'schedules']);

        $data['party_sizes'] = array_map('strval', $table->partySizes());

        foreach (array_keys(TableSchedule::DAY_NAMES) as $day) {
            $data['schedule_'.$day] = $table->schedules
                ->where('day_of_week', $day)
                ->sortBy('first_booking_time')
                ->map(fn (TableSchedule $schedule) => [
                    'first_booking_time' => substr($schedule->first_booking_time, 0, 5),
                    'last_booking_time' => substr($schedule->last_booking_time, 0, 5),
                ])
                ->values()
                ->all();
        }

        return $data;
    }

    private static function dayRepeater(int $day): Repeater
    {
        return Repeater::make('schedule_'.$day)
            ->hiddenLabel()
            ->table([
                TableColumn::make('Primo orario di inizio'),
                TableColumn::make('Ultimo orario di inizio'),
            ])
            ->schema([
                TimePicker::make('first_booking_time')
                    ->seconds(false)
                    ->required(),
                TimePicker::make('last_booking_time')
                    ->seconds(false)
                    ->required(),
            ])
            ->defaultItems(0)
            ->reorderable(false)
            ->addActionLabel('Aggiungi fascia');
    }

    /**
     * @return array<string, string>
     */
    private static function partySizeOptions(): array
    {
        $sizes = range(1, (int) config('booking.max_party_size'));

        return array_combine(array_map('strval', $sizes), array_map('strval', $sizes));
    }
}
