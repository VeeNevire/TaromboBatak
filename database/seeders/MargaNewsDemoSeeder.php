<?php

namespace Database\Seeders;

use App\Models\MargaNewsSource;
use App\Models\MargaNewsTopic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MargaNewsDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $activeTopic = MargaNewsTopic::query()->firstOrCreate(
                ['keyword' => 'kegiatan punguan marga Batak'],
                [
                    'is_active' => true,
                    'notes' => 'Cari laporan kegiatan punguan atau parsadaan marga Batak.',
                ],
            );

            foreach ([
                'pesta bona taon marga Batak',
                'peresmian tugu marga Batak',
            ] as $keyword) {
                MargaNewsTopic::query()->firstOrCreate(
                    ['keyword' => $keyword],
                    ['is_active' => false],
                );
            }

            foreach ([
                [
                    'name' => 'Harian SIB',
                    'website_url' => 'https://www.hariansib.com/',
                    'domain' => 'hariansib.com',
                ],
                [
                    'name' => 'ANTARA Sumatera Utara',
                    'website_url' => 'https://sumut.antaranews.com/',
                    'domain' => 'sumut.antaranews.com',
                ],
                [
                    'name' => 'detikSumut',
                    'website_url' => 'https://www.detik.com/sumut/',
                    'domain' => 'detik.com',
                ],
            ] as $values) {
                $source = MargaNewsSource::query()->firstOrCreate(
                    ['domain' => $values['domain']],
                    [
                        ...$values,
                        'is_active' => true,
                        'applies_to_all_topics' => false,
                    ],
                );

                $source->topics()->syncWithoutDetaching([$activeTopic->id]);
            }
        });
    }
}
