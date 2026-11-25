<?php

namespace App\Modules\Dashboard\Services;

use App\Core\Enums\CaseStage;
use App\Core\Enums\CaseStatus;
use App\Core\Enums\CommissionStatus;
use App\Core\Enums\DeliveryStatus;
use App\Core\Enums\DocumentReviewStatus;
use App\Core\Enums\LeadStatus;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\MediaModerationStatus;
use App\Core\Enums\NotificationChannel;
use App\Modules\Catalog\Models\Developer;
use App\Modules\Catalog\Models\Governorate;
use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Crm\Models\Inquiry;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Finance\Models\Commission;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingDocument;
use App\Modules\Listing\Models\ListingMedia;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    /**
     * @return array{from: string, to: string, preset: string, chart_months: int, kpis: array<string, int|float>, deltas: array<string, array{pct: float, down: bool}|null>, needs_action: array<string, int>, funnel: list<array{key: string, count: int, pct: int}>, charts: array<string, mixed>}
     */
    public function overview(?string $from = null, ?string $to = null, ?string $preset = null, int $chartMonths = 6): array
    {
        [$start, $end, $resolved] = $this->resolveWindow($from, $to, $preset, 'year');
        if (! in_array($chartMonths, [3, 6, 12], true)) {
            $chartMonths = 6;
        }

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'preset' => $resolved,
            'chart_months' => $chartMonths,
            'kpis' => $this->kpis($start, $end),
            'deltas' => $this->deltas($start, $end),
            'needs_action' => $this->needsAction(),
            'funnel' => $this->funnel($start, $end),
            'charts' => $this->charts($end, $chartMonths),
        ];
    }

    /** @return array<string, mixed> */
    public function stats(): array
    {
        return $this->overview()['kpis'];
    }

    public static function compactEgp(float $amount): string
    {
        $abs = abs($amount);
        if ($abs >= 1_000_000) {
            return self::trimCompact($amount / 1_000_000).'M';
        }
        if ($abs >= 1_000) {
            return self::trimCompact($amount / 1_000).'K';
        }

        return number_format($amount, 0);
    }

    private static function trimCompact(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /** @return Collection<int, User> */
    /**
     * Pending work counts shown as sidebar badges.
     *
     * @return array{ADM-02: int, ADM-03: int}
     */
    public function queueBadges(): array
    {
        return [
            'ADM-02' => Listing::query()->whereIn('status', [
                ListingStatus::Submitted,
                ListingStatus::InReview,
                ListingStatus::Resubmitted,
            ])->count(),
            'ADM-03' => ListingDocument::query()->whereIn('review_status', [
                DocumentReviewStatus::Pending,
                DocumentReviewStatus::ReplacementRequested,
            ])->count(),
        ];
    }

    public function recentUsers(int $limit = 5): Collection
    {
        return User::query()
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface, 2: string}
     */
    public function resolveWindow(?string $from, ?string $to, ?string $preset, string $defaultPreset = 'year'): array
    {
        $from = $from !== null && $from !== '' ? $from : null;
        $to = $to !== null && $to !== '' ? $to : null;
        if ($from !== null && $to !== null) {
            [$start, $end] = $this->range($from, $to);

            return [$start, $end, $preset ?: 'custom'];
        }

        $preset = in_array((string) $preset, ['year', 'month', '30d', '6m'], true) ? (string) $preset : $defaultPreset;
        [$start, $end] = match ($preset) {
            'month' => [now()->startOfMonth(), now()->endOfDay()],
            '30d' => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            '6m' => [now()->subMonthsNoOverflow(5)->startOfMonth(), now()->endOfDay()],
            default => [now()->startOfYear(), now()->endOfDay()],
        };

        return [$start, $end, $preset];
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function range(?string $from, ?string $to): array
    {
        $start = $from ? Carbon::parse($from)->startOfDay() : now()->startOfYear();
        $end = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }

    /** @return array<string, int|float> */
    private function kpis(CarbonInterface $start, CarbonInterface $end): array
    {
        $listingStatuses = [
            ListingStatus::Submitted,
            ListingStatus::InReview,
            ListingStatus::Resubmitted,
        ];

        return [
            'total_users' => User::query()->count(),
            'verified_users' => User::query()->whereNotNull('email_verified_at')->count(),
            'new_users_today' => User::query()->whereDate('created_at', today())->count(),
            'new_users_this_week' => User::query()->where('created_at', '>=', now()->startOfWeek())->count(),
            'new_users_in_range' => User::query()->whereBetween('created_at', [$start, $end])->count(),
            'total_listings' => Listing::query()->count(),
            'listings_in_review' => Listing::query()->whereIn('status', $listingStatuses)->whereBetween('created_at', [$start, $end])->count(),
            'listings_approved' => Listing::query()->where('status', ListingStatus::Approved)->whereBetween('created_at', [$start, $end])->count(),
            'listings_published' => Listing::query()->whereIn('status', ListingStatus::publicCases())->count(),
            'listings_rejected' => Listing::query()->where('status', ListingStatus::Rejected)->whereBetween('created_at', [$start, $end])->count(),
            'approved_developers' => Developer::query()->where('is_active', true)->where('is_verified', true)->where('is_other', false)->count(),
            'inquiries_this_month' => Inquiry::query()->where('created_at', '>=', now()->startOfMonth())->count(),
            'inquiries_in_range' => Inquiry::query()->whereBetween('created_at', [$start, $end])->count(),
            'custom_buy_requests' => BuyerRequest::query()->whereBetween('created_at', [$start, $end])->count(),
            'active_transfers' => TransferCase::query()
                ->where('status', CaseStatus::Open->value)
                ->where(function ($query) use ($start, $end): void {
                    $query->whereBetween('created_at', [$start, $end])
                        ->orWhereBetween('last_transition_at', [$start, $end]);
                })
                ->count(),
            'overdue_followups' => Lead::query()->overdueFollowUp()->whereBetween('created_at', [$start, $end])->count(),
            'recorded_commissions' => (float) Commission::query()
                ->whereIn('status', [
                    CommissionStatus::Earned->value,
                    CommissionStatus::Due->value,
                    CommissionStatus::Paid->value,
                ])
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount'),
            'failed_jobs' => $this->failedJobCount(),
            'failed_pushes' => NotificationDelivery::query()
                ->where('channel', NotificationChannel::Push)
                ->where('status', DeliveryStatus::Failed)
                ->count(),
        ];
    }

    /**
     * @return array<string, array{pct: float, down: bool}|null>
     */
    private function deltas(CarbonInterface $start, CarbonInterface $end): array
    {
        $seconds = max(1, $end->getTimestamp() - $start->getTimestamp());
        $prevEnd = $start->copy()->subSecond();
        $prevStart = $prevEnd->copy()->subSeconds($seconds - 1);
        $current = $this->activity($start, $end);
        $previous = $this->activity($prevStart, $prevEnd);
        $out = [];
        foreach ($current as $key => $value) {
            $before = (float) ($previous[$key] ?? 0);
            $now = (float) $value;
            if ($before <= 0 && $now <= 0) {
                $out[$key] = null;

                continue;
            }
            $pct = $before <= 0 ? 100.0 : round((($now - $before) / $before) * 100, 1);
            $out[$key] = [
                'pct' => abs($pct),
                'down' => $now < $before,
            ];
        }

        return $out;
    }

    /** @return array<string, float|int> */
    private function activity(CarbonInterface $start, CarbonInterface $end): array
    {
        $review = [
            ListingStatus::Submitted,
            ListingStatus::InReview,
            ListingStatus::Resubmitted,
        ];

        return [
            'total_listings' => Listing::query()->whereBetween('created_at', [$start, $end])->count(),
            'listings_in_review' => Listing::query()->whereIn('status', $review)->whereBetween('created_at', [$start, $end])->count(),
            'listings_approved' => Listing::query()->whereIn('status', [
                ListingStatus::Approved, ListingStatus::Published,
            ])->whereBetween('created_at', [$start, $end])->count(),
            'listings_rejected' => Listing::query()->where('status', ListingStatus::Rejected)
                ->whereBetween('created_at', [$start, $end])->count(),
            'approved_developers' => Developer::query()->where('is_other', false)
                ->whereBetween('created_at', [$start, $end])->count(),
            'inquiries_in_range' => Inquiry::query()->whereBetween('created_at', [$start, $end])->count(),
            'custom_buy_requests' => BuyerRequest::query()->whereBetween('created_at', [$start, $end])->count(),
            'recorded_commissions' => (float) Commission::query()
                ->whereIn('status', [
                    CommissionStatus::Earned->value,
                    CommissionStatus::Due->value,
                    CommissionStatus::Paid->value,
                ])
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount'),
        ];
    }

    /** @return array<string, int> */
    private function needsAction(): array
    {
        return [
            'listings_in_review' => Listing::query()->whereIn('status', [
                ListingStatus::Submitted,
                ListingStatus::InReview,
                ListingStatus::Resubmitted,
            ])->count(),
            'documents_pending' => ListingDocument::query()
                ->whereIn('review_status', [DocumentReviewStatus::Pending, DocumentReviewStatus::ReplacementRequested])
                ->count(),
            'media_pending' => ListingMedia::query()
                ->whereIn('moderation_status', [
                    MediaModerationStatus::Pending,
                    MediaModerationStatus::Held,
                ])
                ->count(),
            'new_buyer_requests' => BuyerRequest::query()->where('status', 'new')->count(),
            'awaiting_developer' => TransferCase::query()
                ->where('status', CaseStatus::Open->value)
                ->where('internal_stage', CaseStage::DeveloperApproval->value)
                ->count(),
            'unassigned_leads' => Lead::query()
                ->whereNull('assigned_to')
                ->whereNotIn('status', [LeadStatus::Converted->value, LeadStatus::Lost->value])
                ->count(),
            'overdue_followups' => Lead::query()->overdueFollowUp()->count(),
            'contact_flagged' => Listing::query()
                ->where('contact_flagged', true)
                ->whereIn('status', [
                    ListingStatus::Submitted,
                    ListingStatus::InReview,
                    ListingStatus::Resubmitted,
                    ListingStatus::NeedsChanges,
                ])
                ->count(),
        ];
    }

    /** @return list<array{key: string, count: int, pct: int}> */
    private function funnel(CarbonInterface $start, CarbonInterface $end): array
    {
        $created = Lead::query()->whereBetween('created_at', [$start, $end]);
        $total = (clone $created)->count();
        $steps = [
            'new' => (clone $created)->count(),
            'contacted' => (clone $created)->whereNotIn('status', [LeadStatus::New->value])->count(),
            'qualified' => (clone $created)->whereIn('status', [
                LeadStatus::Qualified->value,
                LeadStatus::VisitScheduled->value,
                LeadStatus::OfferSent->value,
                LeadStatus::Converted->value,
            ])->count(),
            'visit_scheduled' => (clone $created)->whereIn('status', [
                LeadStatus::VisitScheduled->value,
                LeadStatus::OfferSent->value,
                LeadStatus::Converted->value,
            ])->count(),
            'converted' => (clone $created)->where('status', LeadStatus::Converted->value)->count(),
        ];

        $out = [];
        foreach ($steps as $key => $count) {
            $out[] = [
                'key' => $key,
                'count' => $count,
                'pct' => $total > 0 ? (int) round($count * 100 / $total) : 0,
            ];
        }

        return $out;
    }

    /** @return array{months: list<array<string, mixed>>, donut: list<array{key: string, count: int}>, egypt: list<array{key: string, count: int, x: int, y: int}>} */
    private function charts(CarbonInterface $end, int $chartMonths = 6): array
    {
        $months = [];
        for ($i = $chartMonths - 1; $i >= 0; $i--) {
            $month = $end->copy()->startOfMonth()->subMonths($i);
            $from = $month->copy()->startOfMonth();
            $to = $month->copy()->endOfMonth();
            $months[] = [
                'key' => $month->format('Y-m'),
                'label' => $month->isoFormat('MMM'),
                'approved' => Listing::query()->where('status', ListingStatus::Approved)
                    ->whereBetween('updated_at', [$from, $to])->count()
                    + Listing::query()->where('status', ListingStatus::Published)
                        ->whereBetween('published_at', [$from, $to])->count(),
                'in_review' => Listing::query()->whereIn('status', [
                    ListingStatus::Submitted,
                    ListingStatus::InReview,
                    ListingStatus::Resubmitted,
                ])->whereBetween('created_at', [$from, $to])->count(),
                'rejected' => Listing::query()->where('status', ListingStatus::Rejected)
                    ->whereBetween('updated_at', [$from, $to])->count(),
            ];
        }

        return [
            'months' => $months,
            'donut' => [
                ['key' => 'approved', 'count' => Listing::query()->whereIn('status', [
                    ListingStatus::Approved, ListingStatus::Published, ListingStatus::UnderOffer,
                ])->count()],
                ['key' => 'in_review', 'count' => Listing::query()->whereIn('status', [
                    ListingStatus::Submitted, ListingStatus::InReview, ListingStatus::Resubmitted,
                ])->count()],
                ['key' => 'rejected', 'count' => Listing::query()->where('status', ListingStatus::Rejected)->count()],
                ['key' => 'hold', 'count' => Listing::query()->whereIn('status', [
                    ListingStatus::Hold, ListingStatus::Closed,
                ])->count()],
                ['key' => 'other', 'count' => Listing::query()->whereIn('status', [
                    ListingStatus::Draft, ListingStatus::NeedsChanges, ListingStatus::Archived,
                ])->count()],
            ],
            'egypt' => $this->egyptDistribution(),
        ];
    }

    /** @return list<array{key: string, count: int, x: int, y: int}> */
    private function egyptDistribution(): array
    {
        $byGovernorate = Listing::query()
            ->whereNotNull('governorate_id')
            ->get(['governorate_id'])
            ->countBy('governorate_id');
        $slugs = Governorate::query()
            ->whereIn('id', $byGovernorate->keys()->filter()->all())
            ->pluck('slug', 'id');
        $counts = [];
        foreach ($byGovernorate as $id => $count) {
            $slug = $slugs[$id] ?? null;
            if ($slug === null) {
                continue;
            }
            $counts[$slug] = ($counts[$slug] ?? 0) + (int) $count;
        }

        $cairo = (int) ($counts['cairo'] ?? 0);
        $giza = (int) ($counts['giza'] ?? 0);
        $coast = (int) ($counts['alexandria'] ?? 0)
            + (int) ($counts['matrouh'] ?? 0)
            + (int) ($counts['beheira'] ?? 0);
        $total = (int) array_sum($counts);

        return [
            ['key' => 'cairo', 'count' => $cairo, 'x' => 160, 'y' => 18],
            ['key' => 'giza', 'count' => $giza, 'x' => 40, 'y' => 60],
            ['key' => 'north_coast', 'count' => $coast, 'x' => 30, 'y' => 150],
            ['key' => 'other', 'count' => max(0, $total - $cairo - $giza - $coast), 'x' => 200, 'y' => 190],
        ];
    }

    private function failedJobCount(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return (int) DB::table('failed_jobs')->count();
    }
}
