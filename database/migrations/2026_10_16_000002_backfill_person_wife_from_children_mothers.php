<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Attach every mother recorded on a child to the child's father as a wife.
     * Earlier saves could detach such a wife, so the father's own spouse list
     * showed fewer wives than his children's "Pasangan Ayah".
     */
    public function up(): void
    {
        $missing = DB::table('people as child')
            ->whereNotNull('child.father_id')
            ->whereNotNull('child.mother_id')
            ->whereColumn('child.father_id', '!=', 'child.mother_id')
            ->whereNotExists(fn ($query) => $query
                ->from('person_wife')
                ->whereColumn('person_wife.husband_id', 'child.father_id')
                ->whereColumn('person_wife.wife_id', 'child.mother_id'))
            ->distinct()
            ->orderBy('child.father_id')
            ->orderBy('child.mother_id')
            ->get(['child.father_id', 'child.mother_id']);

        $positions = DB::table('person_wife')
            ->whereIn('husband_id', $missing->pluck('father_id')->unique())
            ->groupBy('husband_id')
            ->pluck(DB::raw('max(position)'), 'husband_id');

        foreach ($missing as $pair) {
            $position = ($positions[$pair->father_id] ?? 0) + 1;
            $positions[$pair->father_id] = $position;

            DB::table('person_wife')->insertOrIgnore([
                'husband_id' => $pair->father_id,
                'wife_id' => $pair->mother_id,
                'position' => $position,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
