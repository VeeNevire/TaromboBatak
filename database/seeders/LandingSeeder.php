<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Story;
use Illuminate\Database\Seeder;

class LandingSeeder extends Seeder
{
    /**
     * Seed landing content (stories & events).
     */
    public function run(): void
    {
        Story::insert([
            [
                'title' => 'Asal Usul Marga Sitorus',
                'description' => 'Legenda tentang Ompu Sitorus dan permulaan keturunannya di Tanah Batak.',
                'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDvxKrNEUUzGkHRdPPRefpFd2nx2aji_7DCjpPgj_B3GXNjx112a-pr5A4HPfXOtnk_KQ1Bv3bNDiy4REOFLHMNEkl6sOZYjNVUnuo3OQB2as_A8qWSJrMq1ylc-7dOs7t32J2jdha2lwZnzjrZvJ0Ujnti3jKarNC2onQXNi3qoUhp8MWXBEnIwoUfmImM_k9ajcibnvfGaq6BQdjdPZLQNAlwdG55pO3b37KTlg3PuxJGXsJ7l58',
                'published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Makna Gorga dan Simbol Batak',
                'description' => 'Memahami arti di balik setiap ukiran dan motif dalam budaya Batak.',
                'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCmmQd3_QG70w7ItlibgZCh2ZBdnTYCbZoHOL-1MuSxYIL97h81V6coS5gyYM6j4Xwmk2Bbi87whN-RhJyoH7XpUPF8ekbjWQEnmoWnq77cqcaGid30OR7VBSvfrcbFKDyx_ztqpj-YsD7_vn5O1rd4V2jhKPC0eaFFssJyvRBAS5Wj8MF_n7tkoYbIS1WZmMj1z8eoxBRU3Duq8p_xvymVvO3IwRWcH4WSNVYZJeWo1gpShlhIooc',
                'published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Dalihan Na Tolu',
                'description' => 'Filosofi kehidupan masyarakat Batak yang menjadi pedoman dalam keseharian.',
                'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuC7FB0juiMPN2M1-s8FWXpY0Q26Ek_9OK3C6xInToQKxmImWxvh5jL9DYiE6GNiJXrKLeqCosaNEd83WFNst6-Ugk-2L7ljUx30xn5_1HbOrDKQTscm6xlvm5ANGES0Q7REmiG0_TlWxtczn_whN6PhZ31cqsx47KAZnlPGvHeuHs_miWovPkQbujPHq7Ul7-YGjyyXJbpUmANMfpvBlVCBzTu3T63xfReWfuh21kijtyPgdgX1q84',
                'published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Event::insert([
            [
                'title' => 'Pertemuan Marga Hutasoit Jabodetabek',
                'description' => 'Silaturahmi keluarga besar Hutasoit.',
                'location' => 'Jakarta Selatan',
                'date' => now()->addDays(10)->format('Y-m-d'),
                'published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Seminar Budaya Batak untuk Generasi Muda',
                'description' => 'Mengenal akar budaya untuk masa depan.',
                'location' => 'Medan',
                'date' => now()->addDays(17)->format('Y-m-d'),
                'published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Pesta Bona Taon Sitorus',
                'description' => 'Perayaan ulang tahun marga Sitorus.',
                'location' => 'Balige, Toba',
                'date' => now()->addDays(30)->format('Y-m-d'),
                'published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
