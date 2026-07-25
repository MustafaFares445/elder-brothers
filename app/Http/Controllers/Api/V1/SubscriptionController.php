<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\QrPreviewRequest;
use App\Http\Requests\Subscription\QrRedeemRequest;
use App\Http\Resources\CourseSummaryResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\CourseSubscription;
use App\Services\QrSubscriptionService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private readonly QrSubscriptionService $qrService)
    {
    }

    public function courses(Request $request): JsonResponse
    {
        $query = $this->subscriptionQuery($request);
        $paginator = $query->paginate(min(50, max(1, $request->integer('per_page', 15))));

        return ApiResponse::success(
            $this->collection($paginator, SubscriptionResource::class, $request),
            meta: $this->paginationMeta($paginator),
        );
    }

    public function subscriptions(Request $request): JsonResponse
    {
        return $this->courses($request);
    }

    public function show(Request $request, CourseSubscription $subscription): JsonResponse
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);

        $subscription->load(['course', 'redemption']);

        return ApiResponse::success([
            ...(new SubscriptionResource($subscription))->toArray($request),
            'activated_by' => $subscription->source,
            'redemption' => $subscription->redemption ? [
                'id' => $subscription->redemption->id,
                'redeemed_at' => $subscription->redemption->redeemed_at?->toISOString(),
            ] : null,
            'created_at' => $subscription->created_at?->toISOString(),
            'updated_at' => $subscription->updated_at?->toISOString(),
        ]);
    }

    public function preview(QrPreviewRequest $request): JsonResponse
    {
        $qr = $this->qrService->findByRawCode($request->string('code')->toString());
        $this->qrService->ensureRedeemable($qr);

        $existing = $qr->course->activeSubscriptionFor($request->user());
        $expiry = $this->qrService->calculateExpiry($qr, $existing);

        return ApiResponse::success([
            'valid' => true,
            'course' => (new CourseSummaryResource($qr->course))->toArray($request),
            'subscription_duration_days' => $qr->subscription_duration_days,
            'code_expires_at' => $qr->expires_at?->toISOString(),
            'subscription_expires_at' => $expiry?->toISOString(),
            'already_subscribed' => $existing !== null,
            'existing_subscription' => $existing
                ? (new SubscriptionResource($existing->load('course')))->toArray($request)
                : null,
            'redemption_policy' => $existing ? 'extend' : 'create',
            'confirmation_required' => true,
        ], 'api.qr_valid', 'QR_VALID');
    }

    public function redeem(QrRedeemRequest $request): JsonResponse
    {
        $result = $this->qrService->redeem(
            $request->user(),
            $request->string('code')->toString(),
            $request->input('device_id'),
            $request->ip(),
        );

        $subscription = $result['subscription']->load('course');

        return ApiResponse::success([
            'subscription' => (new SubscriptionResource($subscription))->toArray($request),
            'course' => (new CourseSummaryResource($subscription->course))->toArray($request),
            'redemption_id' => $result['redemption_id'],
            'was_extended' => $result['was_extended'],
            'previous_expires_at' => $result['previous_expires_at']?->toISOString(),
            'new_expires_at' => $result['new_expires_at']?->toISOString(),
        ], 'api.qr_redeemed', 'QR_REDEEMED', $result['was_extended'] ? 200 : 201);
    }

    private function subscriptionQuery(Request $request): Builder
    {
        $query = CourseSubscription::query()
            ->where('user_id', $request->user()->id)
            ->with('course')
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('source'), fn (Builder $q) => $q->where('source', $request->string('source')))
            ->when($request->filled('q'), function (Builder $q) use ($request): void {
                $term = $request->string('q')->toString();
                $q->whereHas('course', fn (Builder $course) => $course
                    ->where('title->ar', 'like', "%{$term}%")
                    ->orWhere('title->en', 'like', "%{$term}%"));
            });

        return match ($request->string('sort', 'recent')->toString()) {
            'expiry' => $query->orderBy('expires_at'),
            'progress' => $query->latest('updated_at'),
            default => $query->latest(),
        };
    }
}
