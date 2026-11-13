<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Core\Audit\Models\AccessLog;
use App\Core\Enums\DocumentReviewStatus;
use App\Core\Http\Controllers\BaseController;
use App\Core\Services\QueueQuery;
use App\Modules\Dashboard\Http\Requests\ReviewDocumentRequest;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingDocument;
use App\Modules\Listing\Services\DocumentWatermarker;
use App\Modules\Listing\Services\ListingFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDeskController extends BaseController
{
    public function __construct(
        private ListingFileService $files,
        private DocumentWatermarker $watermarker,
        private AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $tab = (string) $request->query('status', 'pending');
        $allowed = ['all', 'pending', 'approved', 'rejected'];
        if (! in_array($tab, $allowed, true)) {
            $tab = 'pending';
        }
        $search = trim((string) $request->query('q', ''));

        $query = ListingDocument::query()
            ->with(['listing.owner'])
            ->latest();

        if ($tab === 'pending') {
            $query->whereIn('review_status', [DocumentReviewStatus::Pending, DocumentReviewStatus::ReplacementRequested]);
        } elseif ($tab === 'approved') {
            $query->where('review_status', DocumentReviewStatus::Approved);
        } elseif ($tab === 'rejected') {
            $query->where('review_status', DocumentReviewStatus::Rejected);
        }

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('original_name', 'like', $like)
                    ->orWhereHas('listing', fn ($listing) => $listing->where('public_code', 'like', $like)
                        ->orWhereHas('owner', fn ($owner) => $owner->where('name', 'like', $like)));
            });
        }

        QueueQuery::apply($query, QueueQuery::filtersFrom($request), [
            'type' => 'type',
            'added' => 'created_at',
            'status' => 'review_status',
        ]);

        $counts = [
            'all' => ListingDocument::query()->count(),
            'pending' => ListingDocument::query()->whereIn('review_status', [DocumentReviewStatus::Pending, DocumentReviewStatus::ReplacementRequested])->count(),
            'approved' => ListingDocument::query()->where('review_status', DocumentReviewStatus::Approved)->count(),
            'rejected' => ListingDocument::query()->where('review_status', DocumentReviewStatus::Rejected)->count(),
        ];

        return view('dashboard::documents.index', [
            'documents' => $query->paginate(20)->withQueryString(),
            'tab' => $tab,
            'search' => $search,
            'counts' => $counts,
        ]);
    }

    public function show(Listing $listing): View
    {
        $listing->load(['documents.reviewer', 'owner']);
        $history = $listing->documents->groupBy(fn ($doc) => $doc->type?->value);
        $latest = $history->map->first();

        $logs = AccessLog::query()
            ->with('actor')
            ->where('subject_type', (new ListingDocument)->getMorphClass())
            ->whereIn('subject_id', $listing->documents->pluck('id'))
            ->latest('created_at')
            ->limit(30)
            ->get();

        return view('dashboard::documents.show', [
            'listing' => $listing,
            'latest' => $latest,
            'history' => $history,
            'logs' => $logs,
            'missing' => $this->files->missingRequiredTypes($listing),
        ]);
    }

    public function approve(ReviewDocumentRequest $request, ListingDocument $document): RedirectResponse
    {
        $this->files->reviewDocument($document, $request->user(), DocumentReviewStatus::Approved, (string) $request->validated('reason'));

        return back()->with('status', __('aqdar.listings.docs.approved'));
    }

    public function reject(ReviewDocumentRequest $request, ListingDocument $document): RedirectResponse
    {
        $this->files->reviewDocument($document, $request->user(), DocumentReviewStatus::Rejected, (string) $request->validated('reason'));

        return back()->with('status', __('aqdar.listings.docs.rejected'));
    }

    public function requestReplacement(ReviewDocumentRequest $request, ListingDocument $document): RedirectResponse
    {
        $this->files->reviewDocument($document, $request->user(), DocumentReviewStatus::ReplacementRequested, (string) $request->validated('reason'));

        return back()->with('status', __('aqdar.listings.docs.replacement_requested'));
    }

    public function download(Request $request, ListingDocument $document): StreamedResponse
    {
        $staff = $request->user();
        $stamp = 'AQDAR staff-'.$staff->id.' '.now()->format('Y-m-d H:i');
        $label = $stamp.' '.$staff->name;
        $bytes = $this->watermarker->stamp($document->contents(), (string) $document->mime, $stamp);

        $this->audit->access('document.opened', $staff, $document, [
            'listing_id' => $document->listing_id,
            'watermark' => $label,
        ]);

        $document->loadMissing('listing');
        $filename = ($document->listing?->public_code ?? 'listing').'_'.($document->type?->value ?? 'doc').'_v'.$document->version.'.'.pathinfo($document->original_name, PATHINFO_EXTENSION);

        return response()->streamDownload(function () use ($bytes): void {
            echo $bytes;
        }, $filename, [
            'Content-Type' => $document->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }
}
