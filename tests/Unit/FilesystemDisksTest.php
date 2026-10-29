<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FilesystemDisksTest extends TestCase
{
    #[Test]
    public function private_documents_and_public_media_disks_are_configured(): void
    {
        $this->assertSame(storage_path('app/private/documents'), config('filesystems.disks.private_documents.root'));
        $this->assertSame('private', config('filesystems.disks.private_documents.visibility'));
        $this->assertSame(storage_path('app/public/media'), config('filesystems.disks.public_media.root'));
        $this->assertSame('public', config('filesystems.disks.public_media.visibility'));
    }
}
