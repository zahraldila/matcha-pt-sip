<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupabaseStorageService
{
    protected string $bucket;

    protected string $communityBucket;

    protected string $avatarBucket;

    protected ?string $url;

    protected ?string $apiKey;

    protected string|bool $caBundle;

    public function __construct()
    {
        $this->url = config('services.supabase.url') ? rtrim(config('services.supabase.url', 'https://xkyneehswdqkdgzodwdc.supabase.co'), '/') : 'https://xkyneehswdqkdgzodwdc.supabase.co';
        $this->apiKey = config('services.supabase.key');
        $this->bucket = config('services.supabase.bucket', 'venues');
        $this->communityBucket = config('services.supabase.community_bucket', 'community-logos');
        $this->avatarBucket = config('services.supabase.avatar_bucket', 'avatars');
        $this->caBundle = $this->resolveCaBundle();
    }

    protected function resolveCaBundle(): string|bool
    {
        $candidates = [
            ini_get('curl.cainfo'),
            ini_get('openssl.cafile'),
            getenv('CURL_CA_BUNDLE'),
            'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt',
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return true;
    }

    /**
     * Cek apakah konfigurasi Supabase Storage sudah terpasang
     */
    public function isConfigured(): bool
    {
        $cleanKey = trim($this->apiKey ?? '');

        return ! empty($this->url) && ! empty($cleanKey) && strlen($cleanKey) >= 20;
    }

    /**
     * Upload an uploaded file directly to Supabase Storage Bucket.
     *
     * @return string|null Returns the public URL of the uploaded image, or null on failure.
     */
    public function upload(UploadedFile $file, string $folder = 'venues'): ?string
    {
        $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
        $cleanFolder = trim($folder, '/');
        $path = ($cleanFolder !== '' && $cleanFolder !== $this->bucket) ? ($cleanFolder.'/'.$filename) : $filename;

        // 1. Coba upload ke Supabase Storage jika terkonfigurasi
        if ($this->isConfigured()) {
            try {
                $endpoint = "{$this->url}/storage/v1/object/{$this->bucket}/{$path}";
                $mimeType = $file->getMimeType() ?: 'application/octet-stream';

                $response = Http::timeout(5)
                    ->connectTimeout(2)
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$this->apiKey,
                        'apikey' => $this->apiKey,
                        'Content-Type' => $mimeType,
                    ])
                    ->withOptions(['verify' => $this->caBundle])
                    ->withBody(file_get_contents($file->getRealPath()), $mimeType)
                    ->post($endpoint);

                if ($response->successful()) {
                    return "{$this->url}/storage/v1/object/public/{$this->bucket}/{$path}";
                }

                Log::warning('Supabase upload status ('.$response->status().'): '.$response->body().'. Beralih ke fallback lokal.');
            } catch (\Throwable $e) {
                Log::warning('Supabase upload exception: '.$e->getMessage().'. Beralih ke fallback lokal.');
            }
        }

        // 2. Fallback ke direktori lokal
        try {
            $localDir = public_path('uploads/'.$cleanFolder);
            if (! file_exists($localDir)) {
                mkdir($localDir, 0755, true);
            }

            $file->move($localDir, $filename);

            return asset('uploads/'.$cleanFolder.'/'.$filename);
        } catch (\Throwable $localEx) {
            Log::error('Local upload fallback failed: '.$localEx->getMessage());

            return null;
        }
    }

    /**
     * Upload logo komunitas ke Supabase Storage bucket community-logos (dengan fallback lokal otomatis)
     *
     * @return array ['success' => bool, 'url' => ?string, 'filename' => ?string, 'message' => ?string]
     */
    public function uploadLogo(UploadedFile $file): array
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, $allowedExtensions)) {
            return [
                'success' => false,
                'message' => 'Format logo tidak valid. Hanya JPG, JPEG, PNG, atau WEBP yang diterima.',
            ];
        }

        $maxBytes = 2 * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            return [
                'success' => false,
                'message' => 'Ukuran logo maksimal 2 MB.',
            ];
        }

        $cleanRandom = Str::random(12);
        $fileName = "logo_{$cleanRandom}_".time().".{$ext}";

        // 1. Coba upload ke Supabase Storage jika terkonfigurasi
        if ($this->isConfigured()) {
            $endpoint = "{$this->url}/storage/v1/object/{$this->communityBucket}/{$fileName}";

            try {
                $mimeType = $file->getMimeType() ?: ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                $fileContent = file_get_contents($file->getRealPath());

                $response = Http::timeout(6)
                    ->connectTimeout(3)
                    ->withHeaders([
                        'apikey' => $this->apiKey,
                        'Authorization' => 'Bearer '.$this->apiKey,
                        'Content-Type' => $mimeType,
                    ])
                    ->withOptions(['verify' => $this->caBundle])
                    ->withBody($fileContent, $mimeType)
                    ->post($endpoint);

                if ($response->successful()) {
                    $publicUrl = "{$this->url}/storage/v1/object/public/{$this->communityBucket}/{$fileName}";

                    return [
                        'success' => true,
                        'url' => $publicUrl,
                        'filename' => $fileName,
                    ];
                }

                $status = $response->status();
                Log::warning("Supabase Storage logo upload status ({$status}): ".$response->body().'. Beralih ke fallback penyimpanan lokal.');
            } catch (\Throwable $e) {
                Log::warning('Supabase Storage logo exception: '.$e->getMessage().'. Beralih ke fallback penyimpanan lokal.');
            }
        }

        // 2. Fallback: Simpan ke direktori publik lokal jika Supabase offline/unreachable/invalid key
        try {
            $localDir = public_path('uploads/community-logos');
            if (! file_exists($localDir)) {
                mkdir($localDir, 0755, true);
            }

            $file->move($localDir, $fileName);
            $localUrl = asset('uploads/community-logos/'.$fileName);

            return [
                'success' => true,
                'url' => $localUrl,
                'filename' => $fileName,
            ];
        } catch (\Throwable $localEx) {
            Log::error('Local community logo upload fallback failed: '.$localEx->getMessage());

            return [
                'success' => false,
                'message' => 'Gagal menyimpan logo komunitas: '.$localEx->getMessage(),
            ];
        }
    }

    /**
     * Hapus file logo dari bucket Supabase Storage atau penyimpanan lokal
     */
    public function deleteLogo(string $fileNameOrUrl): bool
    {
        if (empty($fileNameOrUrl)) {
            return false;
        }

        $fileName = basename(parse_url($fileNameOrUrl, PHP_URL_PATH));

        // 1. Cek & hapus dari lokal jika ada
        $localPath = public_path('uploads/community-logos/'.$fileName);
        if (file_exists($localPath)) {
            @unlink($localPath);
        }

        // 2. Hapus dari Supabase Storage jika terkonfigurasi
        if (! $this->isConfigured()) {
            return true;
        }

        try {
            $endpoint = "{$this->url}/storage/v1/object/{$this->communityBucket}/{$fileName}";
            $response = Http::timeout(6)
                ->connectTimeout(3)
                ->withHeaders([
                    'apikey' => $this->apiKey,
                    'Authorization' => 'Bearer '.$this->apiKey,
                ])
                ->withOptions(['verify' => $this->caBundle])
                ->delete($endpoint);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Supabase Storage logo delete exception: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Upload foto profil user / pemain ke Supabase Storage bucket avatars
     *
     * @return array ['success' => bool, 'url' => ?string, 'filename' => ?string, 'message' => ?string]
     */
    public function uploadAvatar(UploadedFile $file): array
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, $allowedExtensions)) {
            return [
                'success' => false,
                'message' => 'Format foto tidak valid. Hanya JPG, JPEG, PNG, atau WEBP yang diperbolehkan.',
            ];
        }

        $maxBytes = 2 * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            return [
                'success' => false,
                'message' => 'Ukuran foto profil maksimal 2 MB.',
            ];
        }

        $cleanRandom = Str::random(12);
        $fileName = "avatar_{$cleanRandom}_".time().".{$ext}";

        // 1. Coba upload ke Supabase Storage jika terkonfigurasi
        if ($this->isConfigured()) {
            $endpoint = "{$this->url}/storage/v1/object/{$this->avatarBucket}/{$fileName}";

            try {
                $mimeType = $file->getMimeType() ?: ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                $fileContent = file_get_contents($file->getRealPath());

                $response = Http::timeout(6)
                    ->connectTimeout(3)
                    ->withHeaders([
                        'apikey' => $this->apiKey,
                        'Authorization' => 'Bearer '.$this->apiKey,
                        'Content-Type' => $mimeType,
                    ])
                    ->withOptions(['verify' => $this->caBundle])
                    ->withBody($fileContent, $mimeType)
                    ->post($endpoint);

                if ($response->successful()) {
                    $publicUrl = "{$this->url}/storage/v1/object/public/{$this->avatarBucket}/{$fileName}";

                    return [
                        'success' => true,
                        'url' => $publicUrl,
                        'filename' => $fileName,
                    ];
                }

                $status = $response->status();
                Log::warning("Supabase Storage avatar upload status ({$status}): ".$response->body().'. Beralih ke fallback penyimpanan lokal.');
            } catch (\Throwable $e) {
                Log::warning('Supabase Storage avatar upload exception: '.$e->getMessage().'. Beralih ke fallback penyimpanan lokal.');
            }
        }

        // 2. Fallback: Simpan ke direktori publik lokal jika Supabase offline/unreachable
        try {
            $localDir = public_path('uploads/avatars');
            if (! file_exists($localDir)) {
                mkdir($localDir, 0755, true);
            }

            $file->move($localDir, $fileName);
            $localUrl = asset('uploads/avatars/'.$fileName);

            return [
                'success' => true,
                'url' => $localUrl,
                'filename' => $fileName,
            ];
        } catch (\Throwable $localEx) {
            Log::error('Local avatar upload fallback failed: '.$localEx->getMessage());

            return [
                'success' => false,
                'message' => 'Gagal menyimpan foto profil: '.$localEx->getMessage(),
            ];
        }
    }

    /**
     * Hapus file avatar dari bucket Supabase Storage atau penyimpanan lokal
     */
    public function deleteAvatar(string $fileNameOrUrl): bool
    {
        if (empty($fileNameOrUrl)) {
            return false;
        }

        // Ambil nama file murni jika yang dioper adalah full URL
        $fileName = basename(parse_url($fileNameOrUrl, PHP_URL_PATH));

        // 1. Cek & hapus dari lokal jika ada
        $localPath = public_path('uploads/avatars/'.$fileName);
        if (file_exists($localPath)) {
            @unlink($localPath);
        }

        // 2. Hapus dari Supabase Storage jika terkonfigurasi
        if (! $this->isConfigured()) {
            return true;
        }

        try {
            $endpoint = "{$this->url}/storage/v1/object/{$this->avatarBucket}/{$fileName}";
            $response = Http::timeout(6)
                ->connectTimeout(3)
                ->withHeaders([
                    'apikey' => $this->apiKey,
                    'Authorization' => 'Bearer '.$this->apiKey,
                ])
                ->withOptions(['verify' => $this->caBundle])
                ->delete($endpoint);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Supabase Storage avatar delete exception: '.$e->getMessage());

            return false;
        }
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getBucket(): string
    {
        return $this->bucket;
    }

    public function getAvatarBucket(): string
    {
        return $this->avatarBucket;
    }
}
