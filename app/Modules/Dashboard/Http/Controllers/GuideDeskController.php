<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Dashboard\Services\StaffGuideService;
use Illuminate\Http\Response;
use Illuminate\View\View;

class GuideDeskController extends BaseController
{
    public function __construct(private StaffGuideService $guide) {}

    public function index(): View
    {
        return view('dashboard::guide.index', [
            'sections' => $this->guide->sections(),
            'locale' => app()->getLocale() === 'ar' ? 'ar' : 'en',
        ]);
    }

    public function pdf(): Response
    {
        $binary = $this->guide->pdfBinary();
        $filename = 'aqdar-staff-desk-guide-'.now()->toDateString().'.pdf';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($binary),
        ]);
    }
}
