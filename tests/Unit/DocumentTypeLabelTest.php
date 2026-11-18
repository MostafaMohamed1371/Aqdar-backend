<?php

namespace Tests\Unit;

use App\Core\Enums\DocumentType;
use App\Core\Http\ApiErrorResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocumentTypeLabelTest extends TestCase
{
    public function test_labeled_list_uses_arabic_names_not_machine_keys(): void
    {
        $list = DocumentType::labeledList([
            DocumentType::NationalId,
            DocumentType::Contract,
            DocumentType::PaymentProof,
        ], 'ar');

        $this->assertSame('بطاقة الرقم القومي، عقد المطور، إيصالات السداد', $list);
        $this->assertStringNotContainsString('national_id', $list);
        $this->assertStringNotContainsString('payment_proof', $list);
        $this->assertStringNotContainsString('documents.', $list);
    }

    public function test_documents_required_api_payload_hides_machine_keys(): void
    {
        app()->setLocale('ar');

        $message = __('aqdar.listings.documents_required', [
            'types' => DocumentType::labeledList([
                DocumentType::NationalId,
                DocumentType::Contract,
                DocumentType::PaymentProof,
            ]),
        ]);

        $payload = ApiErrorResponse::from(
            ValidationException::withMessages(['documents' => [$message]])
        )->getData(true);

        $this->assertSame('validation', $payload['code']);
        $this->assertSame($message, $payload['message']);
        $this->assertStringContainsString('بطاقة الرقم القومي', $payload['message']);
        $this->assertStringContainsString('عقد المطور', $payload['message']);
        $this->assertStringContainsString('إيصالات السداد', $payload['message']);
        $this->assertStringNotContainsString('national_id', json_encode($payload));
        $this->assertStringNotContainsString('payment_proof', json_encode($payload));
        $this->assertStringNotContainsString('documents.national_id', json_encode($payload));
    }
}
