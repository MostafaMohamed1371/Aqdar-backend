<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Enums\ContentBlockType;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Catalog\Models\ContentBlock;
use App\Modules\Catalog\Services\MasterDataService;
use App\Modules\Category\Models\Category;
use App\Modules\Dashboard\Http\Requests\SaveCatalogRecordRequest;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Notification\Services\Push\FcmConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentController extends BaseController
{
    public function __construct(
        private MasterDataService $masterData,
        private NotificationService $notifications,
        private FcmConfiguration $fcm,
    ) {}

    public function index(Request $request): View
    {
        $defaultTab = $request->user()?->can('manage_catalog') ? 'templates' : 'schedule';

        return view('dashboard::content.index', [
            'tab' => (string) $request->query('tab', $defaultTab),
            'banners' => ContentBlock::query()->where('type', ContentBlockType::Banner)->orderBy('sort_order')->get(),
            'faqs' => ContentBlock::query()->where('type', ContentBlockType::Faq)->orderBy('sort_order')->get(),
            'support' => ContentBlock::query()->where('type', ContentBlockType::Support)->orderBy('sort_order')->get(),
            'onboarding' => ContentBlock::query()->where('type', ContentBlockType::Onboarding)->orderBy('sort_order')->get(),
            'sellerGuide' => ContentBlock::query()->where('type', ContentBlockType::SellerGuide)->orderBy('sort_order')->get(),
            'templates' => $this->notifications->templates(),
            'broadcasts' => $this->notifications->broadcasts(),
            'deliveries' => $this->notifications->deliveries(),
            'categories' => Category::query()->orderBy('sort_order')->orderBy('id')->get(),
            'fcmStatus' => $this->fcm->status(),
        ]);
    }

    public function store(SaveCatalogRecordRequest $request): RedirectResponse
    {
        $this->masterData->createContent(
            $request->safe()->except(['image']),
            $request->file('image'),
        );

        return redirect()->route('dashboard.content.index')->with('status', __('aqdar.catalog.saved'));
    }

    public function update(SaveCatalogRecordRequest $request, ContentBlock $contentBlock): RedirectResponse
    {
        $this->masterData->updateContent(
            $contentBlock,
            $request->safe()->except(['image']),
            $request->file('image'),
        );

        return redirect()->route('dashboard.content.index')->with('status', __('aqdar.catalog.saved'));
    }

    public function destroy(ContentBlock $contentBlock): RedirectResponse
    {
        $this->masterData->deleteContent($contentBlock);

        return redirect()->route('dashboard.content.index')->with('status', __('aqdar.catalog.deactivated'));
    }
}
