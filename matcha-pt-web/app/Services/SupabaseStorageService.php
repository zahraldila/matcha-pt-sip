<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupabaseStorageService
{
    protected string $bucket;
    protected ?string $url;
    protected ?string $apiKey;
    protected string|bool $caBundle;

    public function __construct()
    {
        $this->bucket = config('services.supabase.bucket', 'community-logos');
        $this->url = config('services.supabase.url') ? rtrim(config('services.supabase.url'), '/') : null;
        $this->apiKey = config('services.supabase.key');
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
        return !empty($this->url) && !empty($this->apiKey);
    }

    /**
     * Upload logo komunitas ke Supabase Storage bucket community-logos
     *
     * @param UploadedFile $file
     * @return array ['success' => bool, 'url' => ?string, 'filename' => ?string, 'message' => ?string]
     */
    public function uploadLogo(UploadedFile $file): array
    {
        // 1. Validasi format file (hanya JPG, JPEG, PNG)
        $allowedExtensions = ['jpg', 'jpeg', 'png'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, $allowedExtensions)) {
            return [
                'success' => false,
                'message' => 'Format logo tidak valid. Hanya JPG, JPEG, atau PNG yang diterima.',
            ];
        }

        // 2. Validasi ukuran file (maksimal 2 MB)
        $maxBytes = 2 * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            return [
                'success' => false,
                'message' => 'Ukuran logo maksimal 2 MB.',
            ];
        }

        // 3. Validasi konfigurasi Supabase
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Konfigurasi Supabase Storage belum lengkap di environment (.env). Pastikan SUPABASE_URL dan SUPABASE_ANON_KEY telah diatur.',
            ];
        }

        // 4. Generate nama file unik
        $cleanRandom = Str::random(12);
        $fileName = "logo_{$cleanRandom}_" . time() . ".{$ext}";
        $endpoint = "{$this->url}/storage/v1/object/{$this->bucket}/{$fileName}";

        try {
            $mimeType = $file->getMimeType() ?: ('image/' . ($ext === 'jpg' ? 'jpeg' : $ext));
            $fileContent = file_get_contents($file->getRealPath());

            // 5. Kirim file fisik ke Supabase Storage API
            $response = Http::withHeaders([
                'apikey'        => $this->apiKey,
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => $mimeType,
            ])
            ->withOptions(['verify' => $this->caBundle])
            ->withBody($fileContent, $mimeType)
            ->post($endpoint);

            // 6. Verifikasi respons HTTP dari Supabase Storage
            if ($response->successful()) {
                $publicUrl = "{$this->url}/storage/v1/object/public/{$this->bucket}/{$fileName}";
                return [
                    'success'  => true,
                    'url'      => $publicUrl,
                    'filename' => $fileName,
                ];
            }

            // Tangani error dari Supabase Storage
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
            $endpoint = "{$this->url}/storage/v1/object/{$this->bucket}/{$fileName}";
            $response = Http::withHeaders([
                'apikey'        => $this->apiKey,
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->withOptions(['verify' => $this->caBundle])->delete($endpoint);

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
