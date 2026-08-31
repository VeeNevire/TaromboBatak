<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marga_user', function (Blueprint $table) {
            $table->foreignId('marga_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['marga_id', 'user_id']);
        });

        DB::table('users')
            ->whereIn('role', ['contributor_main', 'contributor_member'])
            ->whereNotNull('marga_id')
            ->get(['id', 'marga_id'])
            ->each(fn (object $user) => DB::table('marga_user')->insert([
                'marga_id' => $user->marga_id,
                'user_id' => $user->id,
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('marga_user');
    }
};
