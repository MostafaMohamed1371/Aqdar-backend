<?php

namespace App\Modules\Listing\Exceptions;

use App\Core\Enums\DocumentType;
use Illuminate\Validation\ValidationException;

final class RequiredDocumentsException extends ValidationException
{
    /** @var list<DocumentType> */
    public array $missingTypes = [];

    public string $reason = 'not_approved';

    public ?int $listingId = null;

    /**
     * @param  list<DocumentType>  $missing
     */
    public static function missing(array $missing, ?int $listingId = null): self
    {
        return self::make('missing', $missing, $listingId, __('aqdar.listings.documents_required', [
            'types' => DocumentType::labeledList($missing),
        ]));
    }

    /**
     * @param  list<DocumentType>  $missing
     */
    public static function notApproved(array $missing, ?int $listingId = null): self
    {
        return self::make('not_approved', $missing, $listingId, __('aqdar.listings.documents_not_approved', [
            'types' => DocumentType::labeledList($missing),
        ]));
    }

    /**
     * @param  list<DocumentType>  $missing
     */
    private static function make(string $reason, array $missing, ?int $listingId, string $message): self
    {
        /** @var self $exception */
        $exception = self::withMessages([
            'documents' => [$message],
        ]);
        $exception->reason = $reason;
        $exception->missingTypes = array_values($missing);
        $exception->listingId = $listingId;

        return $exception;
    }

    /** @return list<string> */
    public function labels(): array
    {
        return array_map(
            static fn (DocumentType $type): string => $type->label(),
            $this->missingTypes,
        );
    }
}
