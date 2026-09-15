<?php

namespace Tests\Unit;

use App\Services\SupabaseStorageService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AvatarStorageTest extends TestCase
{
    public function test_rejects_invalid_file_extension(): void
    {
        $service = new SupabaseStorageService;
        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $result = $service->uploadAvatar($file);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Format foto profil tidak valid', $result['message']);
    }

    public function test_rejects_file_exceeding_2mb(): void
    {
        $service = new SupabaseStorageService;
        $file = UploadedFile::fake()->image('big_avatar.jpg')->size(3000); // 3 MB

        $result = $service->uploadAvatar($file);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('maksimal 2 MB', $result['message']);
    }

    public function test_avatar_bucket_configured_as_avatars(): void
    {
        $service = new SupabaseStorageService;
        $this->assertEquals('avatars', $service->getAvatarBucket());
    }
}
