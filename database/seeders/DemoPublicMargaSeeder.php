<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Marga;
use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Publish a demo set of marga so the public Daftar Marga has content, and give
 * each of them an approved story and event for the detail dialog.
 *
 * Run with: php artisan db:seed --class=DemoPublicMargaSeeder
 */
class DemoPublicMargaSeeder extends Seeder
{
    /** @var array<int, string> */
    private const DEMO_MARGA_NAMES = [
        'Ambarita',
        'Aritonang',
        'Aruan',
        'Bakara',
        'Limbong',
        'Sihombing',
        'Silaban',
        'Simanjuntak',
        'Sitorus',
        'Situmorang',
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $author = User::query()->where('role', 'admin')->orderBy('id')->first();

            Marga::query()
                ->whereNotNull('identity_person_id')
                ->update(['is_public' => true]);

            Marga::query()
                ->whereIn('name', self::DEMO_MARGA_NAMES)
                ->update(['is_public' => true]);

            $margas = Marga::query()->where('is_public', true)->orderBy('name')->get();

            foreach ($margas as $marga) {
                $story = Story::updateOrCreate(
                    ['title' => 'Asal Usul Marga '.$marga->name],
                    [
                        'created_by' => $author?->id,
                        'marga_id' => $marga->id,
                        'classification' => Story::CLASSIFICATION_MARGA,
                        'description' => 'Legenda tentang Ompu '.$marga->name.' dan permulaan keturunannya di Tanah Batak.',
                        'published' => true,
                        'status' => Story::STATUS_APPROVED,
                        'reviewed_by' => $author?->id,
                        'reviewed_at' => now(),
                    ],
                );
                $story->relatedMargas()->syncWithoutDetaching([$marga->id]);

                $event = Event::updateOrCreate(
                    ['title' => 'Punguan Marga '.$marga->name],
                    [
                        'created_by' => $author?->id,
                        'marga_id' => $marga->id,
                        'description' => 'Pertemuan tahunan punguan marga '.$marga->name.' beserta boru dan bere.',
                        'location' => 'Balige, Toba',
                        'date' => now()->addMonth(),
                        'published' => true,
                        'status' => Event::STATUS_APPROVED,
                        'reviewed_by' => $author?->id,
                        'reviewed_at' => now(),
                    ],
                );
                $event->relatedMargas()->syncWithoutDetaching([$marga->id]);
            }

            $this->command?->info($margas->count().' marga ditandai Public beserta cerita dan kegiatan demo.');
        });
    }
}
