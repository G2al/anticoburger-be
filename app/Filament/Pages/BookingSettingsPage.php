<?php

namespace App\Filament\Pages;

use App\Filament\NavigationGroup;
use App\Filament\Support\BookingFeedback;
use App\Models\BookingSettings;
use App\Services\Reservations\BookingSettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Edits the booking_settings singleton row. There is no way to create further rows.
 *
 * @property-read Schema $form
 */
class BookingSettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Impostazioni';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::RESERVATIONS;

    protected static ?string $title = 'Impostazioni prenotazioni';

    protected static ?string $slug = 'impostazioni-prenotazioni';

    protected static ?int $navigationSort = 3;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(BookingSettings::current()->only([
            'min_lead_minutes',
            'max_advance_days',
            'max_reservations_per_phone_per_day',
            'prevent_duplicate_bookings',
        ]));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Finestra di prenotazione')
                    ->description('Limiti applicati alle prenotazioni online dei clienti.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('min_lead_minutes')
                            ->label('Anticipo minimo')
                            ->helperText("Minuti minimi tra l'istante attuale e l'orario prenotato. 0 = nessun anticipo minimo.")
                            ->suffix('minuti')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(BookingSettingsService::MAX_LEAD_MINUTES),
                        TextInput::make('max_advance_days')
                            ->label('Anticipo massimo')
                            ->helperText('Quanti giorni di calendario futuri sono prenotabili. 0 = nessun limite.')
                            ->suffix('giorni')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(BookingSettingsService::MAX_ADVANCE_DAYS),
                    ]),
                Section::make('Protezioni anti-abuso')
                    ->description('Le prenotazioni annullate non vengono conteggiate. I limiti non si applicano alle prenotazioni inserite dal gestionale.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('max_reservations_per_phone_per_day')
                            ->label('Prenotazioni massime per telefono al giorno')
                            ->helperText('Prenotazioni confermate con lo stesso numero nella stessa giornata. 0 = nessun limite.')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(BookingSettingsService::MAX_RESERVATIONS_PER_PHONE_PER_DAY),
                        Toggle::make('prevent_duplicate_bookings')
                            ->label('Protezione prenotazioni duplicate')
                            ->helperText('Blocca una seconda prenotazione con stesso telefono, data, ora e numero di persone.')
                            ->inline(false),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Salva impostazioni')
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        BookingFeedback::run(fn () => app(BookingSettingsService::class)->update($data), 'data');

        Notification::make()
            ->title('Impostazioni salvate')
            ->success()
            ->send();
    }
}
