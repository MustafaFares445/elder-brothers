<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_qr_codes', function (Blueprint $table): void {
            $table->text('code_encrypted')->nullable()->after('code_hash');
        });

        DB::table('course_videos')->update([
            'is_preview' => false,
            'is_downloadable' => true,
        ]);

        DB::table('course_files')->update([
            'is_downloadable' => true,
        ]);

        DB::table('course_subscriptions')->update([
            'source' => 'qr',
        ]);

        DB::table('subscription_qr_codes')
            ->orderBy('id')
            ->chunkById(100, function ($codes): void {
                foreach ($codes as $code) {
                    $createdAt = $code->created_at
                        ? Carbon::parse($code->created_at)
                        : now();

                    $status = $code->status;

                    if ((int) $code->redemptions_count >= 1) {
                        $status = 'exhausted';
                    } elseif ($createdAt->copy()->addDays(2)->isPast() && $status === 'active') {
                        $status = 'expired';
                    }

                    DB::table('subscription_qr_codes')
                        ->where('id', $code->id)
                        ->update([
                            'starts_at' => $code->starts_at ?: $createdAt,
                            'expires_at' => $createdAt->copy()->addDays(2),
                            'max_redemptions' => 1,
                            'status' => $status,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('subscription_qr_codes', function (Blueprint $table): void {
            $table->dropColumn('code_encrypted');
        });
    }
};
