<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Enums\SupportAuthorType;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Crm\Models\SupportThread;
use App\Modules\Crm\Services\SupportService;
use App\Modules\Dashboard\Http\Requests\StoreSupportReplyRequest;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportDeskController extends BaseController
{
    public function __construct(private SupportService $support) {}

    public function index(Request $request): View
    {
        /** @var StaffUser $staff */
        $staff = $request->user();

        return view('dashboard::support.index', [
            'threads' => $this->support->deskIndex($staff, [
                'status' => (string) $request->query('status', 'all'),
            ]),
            'status' => (string) $request->query('status', 'all'),
        ]);
    }

    public function show(Request $request, SupportThread $supportThread): View
    {
        /** @var StaffUser $staff */
        $staff = $request->user();

        return view('dashboard::support.show', [
            'thread' => $this->support->deskShow($staff, $supportThread),
        ]);
    }

    public function poll(Request $request, SupportThread $supportThread): JsonResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $thread = $this->support->deskShow($staff, $supportThread);
        $afterId = max(0, (int) $request->query('after_id', 0));

        $messages = $thread->messages
            ->filter(fn ($message): bool => (int) $message->id > $afterId)
            ->values()
            ->map(fn ($message): array => [
                'id' => $message->id,
                'author' => $message->author_type === SupportAuthorType::Staff ? 'sales' : 'user',
                'body' => $message->body,
                'created_at' => optional($message->created_at)?->toIso8601String(),
            ]);

        return response()->json([
            'data' => [
                'thread_id' => $thread->id,
                'status' => $thread->status?->value,
                'messages' => $messages,
            ],
        ]);
    }

    public function pollIndex(Request $request): JsonResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $threads = $this->support->deskIndex($staff, [
            'status' => (string) $request->query('status', 'open'),
        ]);

        return response()->json([
            'data' => collect($threads->items())->map(fn (SupportThread $thread): array => [
                'id' => $thread->id,
                'public_code' => $thread->public_code,
                'user_id' => $thread->user_id,
                'assigned_to' => $thread->assigned_to,
                'status' => $thread->status?->value,
            ])->values(),
        ]);
    }

    public function reply(StoreSupportReplyRequest $request, SupportThread $supportThread): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->support->reply($staff, $supportThread, (string) $request->validated('body'));

        return back()->with('status', __('aqdar.support.replied'));
    }

    public function close(Request $request, SupportThread $supportThread): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->support->close($staff, $supportThread);

        return redirect()->route('dashboard.support.index')->with('status', __('aqdar.support.closed_ok'));
    }
}
