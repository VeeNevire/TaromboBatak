<?php

namespace Database\Seeders;

use App\Models\MargaNewsTopic;
use Illuminate\Database\Seeder;

/** Starting keywords for the news agent; admins manage the rest on the Topik Berita page. */
class MargaNewsTopicSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'pesta bona taon marga',
            'parsadaan pomparan',
            'punguan marga batak',
            'peresmian tugu marga batak',
            'bona pasogit marga',
        ] as $keyword) {
            MargaNewsTopic::query()->firstOrCreate(['keyword' => $keyword], ['is_active' => true]);
        }
    }
}
