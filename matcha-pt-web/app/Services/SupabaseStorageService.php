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
    protected ?string $url;
    protected ?string $apiKey;
    protected string|bool $caBundle;

    public function __construct()
    {
        $this->url = config('services.supabase.url') ? rtrim(config('services.supabase.url', 'https://xkyneehswdqkdgzodwdc.supabase.co'), '/') : 'https://xkyneehswdqkdgzodwdc.supabase.co';
        $this->apiKey = config('services.supabase.key');
        $this->bucket = config('services.supabase.bucket', 'venues');
        $this->communityBucket = config('services.supabase.community_bucket', 'community-logos');
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
        return !empty($this->url) && !empty($cleanKey) && strlen($cleanKey) >= 20;
    }

    /**
     * Upload an uploaded file directly to Supabase Storage Bucket.
     *
     * @param UploadedFile $file
     * @param string $folder
     * @return string|null Returns the public URL of the uploaded image, or null on failure.
     */
    public function upload(UploadedFile $file, string $folder = 'venues'): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = trim($folder, '/') . '/' . $filename;
            $endpoint = "{$this->url}/storage/v1/object/{$this->bucket}/{$path}";
            $mimeType = $file->getMimeType() ?: 'application/octet-stream';

            $response = Http::timeout(6)
                ->connectTimeout(3)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'apikey'        => $this->apiKey,
                    'Content-Type'  => $mimeType,
                ])
                ->withOptions(['verify' => $this->caBundle])
                ->withBody(file_get_contents($file->getRealPath()), $mimeType)
                ->post($endpoint);

            if ($response->successful()) {
                return "{$this->url}/storage/v1/object/public/{$this->bucket}/{$path}";
            }

            Log::error('Supabase upload failed (' . $response->status() . '): ' . $response->body());
            return null;
        } catch (\Throwable $e) {
            Log::error('Supabase upload exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Upload logo komunitas ke Supabase Storage bucket community-logos
     *
     * @param UploadedFile $file
     * @return array ['success' => bool, 'url' => ?string, 'filename' => ?string, 'message' => ?string]
     */
    public function uploadLogo(UploadedFile $file): array
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, $allowedExtensions)) {
            return [
                'success' => false,
                'message' => 'Format logo tidak valid. Hanya JPG, JPEG, atau PNG yang diterima.',
            ];
        }

        $maxBytes = 2 * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            return [
                'success' => false,
                'message' => 'Ukuran logo maksimal 2 MB.',
            ];
        }

        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Konfigurasi Supabase Storage belum lengkap di environment (.env). Pastikan SUPABASE_URL dan SUPABASE_ANON_KEY telah diatur.',
            ];
        }

        $cleanRandom = Str::random(12);
        $fileName = "logo_{$cleanRandom}_" . time() . ".{$ext}";
        $endpoint = "{$this->url}/storage/v1/object/{$this->communityBucket}/{$fileName}";

        try {
            $mimeType = $file->getMimeType() ?: ('image/' . ($ext === 'jpg' ? 'jpeg' : $ext));
            $fileContent = file_get_contents($file->getRealPath());

            $response = Http::timeout(6)
                ->connectTimeout(3)
                ->withHeaders([
                    'apikey'        => $this->apiKey,
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => $mimeType,
                ])
                ->withOptions(['verify' => $this->caBundle])
                ->withBody($fileContent, $mimeType)
                ->post($endpoint);

            if ($response->successful()) {
                $publicUrl = "{$this->url}/storage/v1/object/public/{$this->communityBucket}/{$fileName}";
                return [
                    'success'  => true,
                    'url'      => $publicUrl,
                    'filename' => $fileName,
                ];
            }

            $status = $response->status();
            $body = $response->json();
            $errorMsg = $body['message'] ?? $body['error'] ?? "Supabase Storage merespons status {$status}.";

            Log::error("Supabase Storage upload failed ({$status}): " . $response->body());

            return [
                'success' => false,
                'message' => 'Gagal mengunggah logo ke Supabase Storage: ' . $errorMsg,
            ];
        } catch (\Throwable $e) {
            Log::error('Supabase Storage exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Kendala koneksi ke Supabase Storage: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Hapus file logo dari bucket Supabase Storage
     */
    public function deleteLogo(string $fileName): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $endpoint = "{$this->url}/storage/v1/object/{$this->communityBucket}/{$fileName}";
            $response = Http::timeout(6)
                ->connectTimeout(3)
                ->withHeaders([
                    'apikey'        => $this->apiKey,
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ])
                ->withOptions(['verify' => $this->caBundle])
                ->delete($endpoint);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Supabase Storage delete exception: ' . $e->getMessage());
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
}
