<?php

namespace Tests\Unit;

use App\Core\Enums\DocumentType;
use App\Core\Http\ApiErrorResponse;
use App\Modules\Listing\Exceptions\RequiredDocumentsException;
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

    public function test_documents_not_approved_api_payload_is_actionable_and_hides_machine_keys(): void
    {
        app()->setLocale('en');

        $payload = ApiErrorResponse::from(
            RequiredDocumentsException::notApproved([
                DocumentType::NationalId,
                DocumentType::Contract,
                DocumentType::PaymentProof,
            ], 19)
        )->getData(true);

        $this->assertSame('documents_not_approved', $payload['code']);
        $this->assertSame(__('aqdar.listings.documents_block_title', locale: 'en'), $payload['title']);
        $this->assertSame(__('aqdar.listings.documents_block_hint', locale: 'en'), $payload['hint']);
        $this->assertSame([
            'National ID',
            'Developer contract',
            'Payment receipts',
        ], $payload['details']);
        $this->assertStringContainsString('National ID', $payload['message']);
        $this->assertStringNotContainsString('national_id', json_encode($payload));
        $this->assertStringNotContainsString('payment_proof', json_encode($payload));
        $this->assertStringNotContainsString('documents.national_id', json_encode($payload));
    }
}
