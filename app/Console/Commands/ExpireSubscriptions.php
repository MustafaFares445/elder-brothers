<?php
namespace App\Console\Commands;
use App\Models\CourseSubscription;
use Illuminate\Console\Command;
class ExpireSubscriptions extends Command {
 protected $signature='subscriptions:expire'; protected $description='Expire elapsed course subscriptions';
 public function handle(): int { $count=CourseSubscription::where('status','active')->whereNotNull('expires_at')->where('expires_at','<=',now())->update(['status'=>'expired']);$this->info("Expired {$count} subscriptions.");return self::SUCCESS; }
}
