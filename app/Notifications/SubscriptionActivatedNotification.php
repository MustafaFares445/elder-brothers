<?php
namespace App\Notifications;
use App\Models\CourseSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
class SubscriptionActivatedNotification extends Notification {
 use Queueable;
 public function __construct(private CourseSubscription $subscription){}
 public function via(object $notifiable): array { return ['database']; }
 public function toArray(object $notifiable): array {
  $course=$this->subscription->course;
  return ['type'=>'subscription_activated','title_ar'=>'تم تفعيل الاشتراك','title_en'=>'Subscription activated','body_ar'=>'تم تفعيل اشتراكك في '.$course->translated('title'),'body_en'=>'Your subscription to '.$course->translated('title').' is active.','action_type'=>'course','action_id'=>$course->id];
 }
}
