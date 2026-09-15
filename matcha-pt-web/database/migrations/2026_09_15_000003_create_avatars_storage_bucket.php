<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            // Cek apakah database PostgreSQL dan schema storage tersedia
            $driver = DB::connection()->getDriverName();
            if ($driver !== 'pgsql') {
                return;
            }

            // 1. Buat bucket 'avatars' pada storage.buckets
            DB::statement("
                INSERT INTO storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
                VALUES (
                    'avatars',
                    'avatars',
                    true,
                    2097152,
                    ARRAY['image/jpeg', 'image/jpg', 'image/png', 'image/webp']
                )
                ON CONFLICT (id) DO UPDATE SET
                    public = true,
                    file_size_limit = 2097152,
                    allowed_mime_types = ARRAY['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            ");

            // 2. Storage Policies untuk bucket avatars
            // Public Read
            DB::statement("
                DO $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_policies 
                        WHERE schemaname = 'storage' AND tablename = 'objects' AND policyname = 'Public read avatars'
                    ) THEN
                        CREATE POLICY \"Public read avatars\"
                        ON storage.objects FOR SELECT
                        USING (bucket_id = 'avatars');
                    END IF;
                END $$;
            ");

            // Upload
            DB::statement("
                DO $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_policies 
                        WHERE schemaname = 'storage' AND tablename = 'objects' AND policyname = 'Allow upload avatars'
                    ) THEN
                        CREATE POLICY \"Allow upload avatars\"
                        ON storage.objects FOR INSERT
                        WITH CHECK (bucket_id = 'avatars');
                    END IF;
                END $$;
            ");

            // Delete / Update
            DB::statement("
                DO $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_policies 
                        WHERE schemaname = 'storage' AND tablename = 'objects' AND policyname = 'Allow delete avatars'
                    ) THEN
                        CREATE POLICY \"Allow delete avatars\"
                        ON storage.objects FOR DELETE
                        USING (bucket_id = 'avatars');
                    END IF;
                END $$;
            ");
        } catch (Throwable $e) {
            Log::warning('Migrasi storage bucket avatars dilewati atau gagal: '.$e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            $driver = DB::connection()->getDriverName();
            if ($driver !== 'pgsql') {
                return;
            }

            DB::statement('DROP POLICY IF EXISTS "Public read avatars" ON storage.objects;');
            DB::statement('DROP POLICY IF EXISTS "Allow upload avatars" ON storage.objects;');
            DB::statement('DROP POLICY IF EXISTS "Allow delete avatars" ON storage.objects;');
            DB::statement("DELETE FROM storage.buckets WHERE id = 'avatars';");
        } catch (Throwable $e) {
            Log::warning('Rollback storage bucket avatars dilewati: '.$e->getMessage());
        }
    }
};
