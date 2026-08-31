<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telegram_auth_sessions')) {
            return;
        }

        Schema::table('telegram_auth_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('telegram_auth_sessions', 'qr_svg')) {
                $table->longText('qr_svg')->nullable()->after('status');
            }

            if (! Schema::hasColumn('telegram_auth_sessions', 'qr_expires_at')) {
                $table->timestamp('qr_expires_at')->nullable()->after('qr_svg');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('telegram_auth_sessions')) {
            return;
        }

        Schema::table('telegram_auth_sessions', function (Blueprint $table): void {
            $columns = collect(['qr_svg', 'qr_expires_at'])
                ->filter(fn (string $column): bool => Schema::hasColumn('telegram_auth_sessions', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
