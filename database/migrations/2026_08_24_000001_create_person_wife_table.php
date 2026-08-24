<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('person_wife', function (Blueprint $table) {
            $table->foreignId('husband_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('wife_id')->constrained('people')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');

            $table->primary(['husband_id', 'wife_id']);
            $table->index(['wife_id', 'husband_id']);
        });

        $relationships = [];
        $seen = [];
        $positions = [];

        DB::table('people')
            ->whereNotNull('father_id')
            ->whereNotNull('mother_id')
            ->orderBy('father_id')
            ->orderBy('birth_order')
            ->orderBy('id')
            ->select(['father_id', 'mother_id'])
            ->each(function (object $person) use (&$relationships, &$seen, &$positions) {
                $key = $person->father_id.':'.$person->mother_id;

                if (isset($seen[$key])) {
                    return;
                }

                $seen[$key] = true;
                $positions[$person->father_id] = ($positions[$person->father_id] ?? 0) + 1;
                $relationships[] = [
                    'husband_id' => $person->father_id,
                    'wife_id' => $person->mother_id,
                    'position' => $positions[$person->father_id],
                ];
            });

        foreach (array_chunk($relationships, 500) as $chunk) {
            DB::table('person_wife')->insert($chunk);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_wife');
    }
};
