<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\SubscriptionQrCode;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CreateSubscriptionQrCode extends Command
{
    protected $signature = 'subscription-code:create
        {course : Course ID}
        {--days=365 : Subscription duration in days}
        {--max=1 : Maximum redemptions}
        {--label= : Administrative label}
        {--expires= : QR expiration date (Y-m-d)}';

    protected $description = 'Generate a secure course subscription code and print its raw value once.';

    public function handle(): int
    {
        $course = Course::findOrFail((int) $this->argument('course'));
        $raw = 'EB-'.Str::upper(Str::random(8)).'-'.Str::upper(Str::random(8));

        SubscriptionQrCode::create([
            'course_id' => $course->id,
            'code_hash' => hash('sha256', $raw),
            'code_hint' => Str::mask($raw, '*', 6, -4),
            'label' => $this->option('label') ?: 'Generated for '.$course->localizedTitle('en'),
            'starts_at' => now(),
            'expires_at' => $this->option('expires') ? Carbon::parse($this->option('expires'))->endOfDay() : null,
            'max_redemptions' => (int) $this->option('max'),
            'subscription_duration_days' => (int) $this->option('days'),
            'status' => 'active',
            'created_by' => auth()->id(),
        ]);

        $this->newLine();
        $this->warn('Store this raw code securely. It cannot be recovered later.');
        $this->line($raw);

        return self::SUCCESS;
    }
}
