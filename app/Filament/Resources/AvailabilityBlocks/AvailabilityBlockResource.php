<?php

namespace App\Filament\Resources\AvailabilityBlocks;

use App\Filament\Resources\AvailabilityBlocks\Pages\CreateAvailabilityBlock;
use App\Filament\Resources\AvailabilityBlocks\Pages\EditAvailabilityBlock;
use App\Filament\Resources\AvailabilityBlocks\Pages\ListAvailabilityBlocks;
use App\Filament\Resources\AvailabilityBlocks\Schemas\AvailabilityBlockForm;
use App\Filament\Resources\AvailabilityBlocks\Tables\AvailabilityBlocksTable;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Models\AvailabilityBlock;
use App\Services\Reservations\AvailabilityBlockService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AvailabilityBlockResource extends Resource
{
    protected static ?string $model = AvailabilityBlock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?string $modelLabel = 'blocco disponibilità';

    protected static ?string $pluralModelLabel = 'blocchi disponibilità';

    protected static ?string $navigationLabel = 'Blocchi disponibilità';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'blocchi-disponibilita';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return AvailabilityBlockForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AvailabilityBlocksTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('restaurantTable');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAvailabilityBlocks::route('/'),
            'create' => CreateAvailabilityBlock::route('/create'),
            'edit' => EditAvailabilityBlock::route('/{record}/edit'),
        ];
    }

    public static function affectedReservationsUrl(AvailabilityBlock $block): string
    {
        return ReservationResource::getUrl('index', [
            'filters' => ['block' => ['availability_block_id' => $block->id]],
        ]);
    }

    /**
     * Existing reservations are never changed by a block: the admin is warned instead.
     */
    public static function warnAboutAffectedReservations(AvailabilityBlock $block): void
    {
        $affectedCount = app(AvailabilityBlockService::class)->affectedReservations($block)->count();

        if ($affectedCount === 0 || ! $block->is_active) {
            return;
        }

        Notification::make()
            ->title('Prenotazioni già esistenti nel periodo bloccato')
            ->body($affectedCount === 1
                ? "C'è 1 prenotazione confermata che si sovrappone a questo blocco. Non è stata modificata né annullata."
                : "Ci sono {$affectedCount} prenotazioni confermate che si sovrappongono a questo blocco. Non sono state modificate né annullate.")
            ->warning()
            ->persistent()
            ->actions([
                Action::make('viewAffected')
                    ->label('Visualizza prenotazioni')
                    ->button()
                    ->url(self::affectedReservationsUrl($block)),
            ])
            ->send();
    }
}
