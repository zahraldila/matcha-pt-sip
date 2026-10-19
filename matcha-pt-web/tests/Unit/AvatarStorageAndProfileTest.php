<?php

namespace Tests\Unit;

use App\Models\Player;
use App\Models\User;
use App\Services\SupabaseStorageService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AvatarStorageAndProfileTest extends TestCase
{
    public function test_user_and_player_models_have_foto_in_fillable()
    {
        $user = new User;
        $this->assertContains('foto', $user->getFillable());

        $player = new Player;
        $this->assertContains('foto', $player->getFillable());
    }

    public function test_supabase_storage_service_configures_avatar_bucket_correctly()
    {
        $service = new SupabaseStorageService;
        $this->assertEquals('avatars', $service->getAvatarBucket());
    }

    public function test_upload_avatar_rejects_invalid_file_extension()
    {
        $service = new SupabaseStorageService;
        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $result = $service->uploadAvatar($file);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Format foto tidak valid', $result['message']);
    }

    public function test_upload_avatar_rejects_oversized_file()
    {
        $service = new SupabaseStorageService;
        $file = UploadedFile::fake()->create('large_image.jpg', 3000, 'image/jpeg'); // 3MB

        $result = $service->uploadAvatar($file);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('maksimal 2 MB', $result['message']);
    }

    public function test_upload_avatar_succeeds_with_local_fallback()
    {
        config(['services.supabase.service_key' => null, 'services.supabase.key' => null]);

        $service = new SupabaseStorageService;
        $file = UploadedFile::fake()->image('my_avatar.png', 200, 200);

        $result = $service->uploadAvatar($file);
        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['url']);
        $this->assertNotEmpty($result['filename']);
        $this->assertStringEndsWith('.webp', $result['filename']);

        $storedImage = getimagesize(public_path('uploads/avatars/'.$result['filename']));
        $this->assertSame('image/webp', $storedImage['mime'] ?? null);

        // Clean up test upload
        if (! empty($result['filename'])) {
            $service->deleteAvatar($result['filename']);
        }
    }

    public function test_upload_avatar_converts_jpeg_to_webp_with_local_fallback()
    {
        config(['services.supabase.service_key' => null, 'services.supabase.key' => null]);

        $service = new SupabaseStorageService;
        $file = UploadedFile::fake()->image('my_avatar.jpg', 200, 200);

        $result = $service->uploadAvatar($file);

        $this->assertTrue($result['success']);
        $this->assertStringEndsWith('.webp', $result['filename']);
        $storedImage = getimagesize(public_path('uploads/avatars/'.$result['filename']));
        $this->assertSame('image/webp', $storedImage['mime'] ?? null);

        $service->deleteAvatar($result['filename']);
    }
}
