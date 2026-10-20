<?php

namespace App\Console\Commands;

use App\Models\MenuItem;
use App\Services\Menu\MenuImageOptimizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class OptimizeMenuImagesCommand extends Command
{
    protected $signature = 'app:optimize-menu-images';

    protected $description = 'Convert the menu photos uploaded before automatic optimization to light WebP images';

    public function handle(MenuImageOptimizer $optimizer): int
    {
        $disk = Storage::disk(MenuItem::IMAGE_DISK);
        $optimized = 0;
        $savedBytes = 0;

        $items = MenuItem::query()->whereNotNull('image_path')->where('image_path', 'not like', '%.webp')->get();

        foreach ($items as $item) {
            if (! $disk->exists($item->image_path)) {
                $this->warn("Foto mancante per «{$item->name}»: {$item->image_path}");

                continue;
            }

            $originalBytes = $disk->size($item->image_path);
            $newPath = $optimizer->optimize(MenuItem::IMAGE_DISK, $item->image_path);

            if ($newPath === $item->image_path) {
                $this->warn("Foto non convertibile per «{$item->name}»: lasciata invariata.");

                continue;
            }

            $item->forceFill(['image_path' => $newPath])->saveQuietly();
            $optimized++;
            $savedBytes += $originalBytes - $disk->size($newPath);
            $this->line("«{$item->name}»: ".round($originalBytes / 1024).' KB → '.round($disk->size($newPath) / 1024).' KB');
        }

        $this->info("Foto ottimizzate: {$optimized}. Spazio risparmiato: ".round($savedBytes / 1024 / 1024, 2).' MB.');

        return self::SUCCESS;
    }
}
