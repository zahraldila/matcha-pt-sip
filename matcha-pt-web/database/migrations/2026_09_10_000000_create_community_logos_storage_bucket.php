<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Buat bucket 'community-logos' pada storage.buckets
        DB::statement("
            INSERT INTO storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
            VALUES (
                'community-logos',
                'community-logos',
                true,
                5242880,
                ARRAY['image/jpeg', 'image/jpg', 'image/png']
            )
            ON CONFLICT (id) DO UPDATE SET
                public = true,
                file_size_limit = 5242880,
                allowed_mime_types = ARRAY['image/jpeg', 'image/jpg', 'image/png'];
        ");

        // 2. Buat Storage Policies minimal hanya untuk bucket community-logos
        // Public Read
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_policies 
                    WHERE schemaname = 'storage' AND tablename = 'objects' AND policyname = 'Public read community logos'
                ) THEN
                    CREATE POLICY \"Public read community logos\"
                    ON storage.objects FOR SELECT
                    USING (bucket_id = 'community-logos');
                END IF;
            END $$;
        ");

        // Upload
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_policies 
                    WHERE schemaname = 'storage' AND tablename = 'objects' AND policyname = 'Allow upload community logos'
                ) THEN
                    CREATE POLICY \"Allow upload community logos\"
                    ON storage.objects FOR INSERT
                    WITH CHECK (bucket_id = 'community-logos');
                END IF;
            END $$;
        ");

        // Delete (hanya untuk rollback jika diperlukan)
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_policies 
                    WHERE schemaname = 'storage' AND tablename = 'objects' AND policyname = 'Allow delete community logos'
                ) THEN
                    CREATE POLICY \"Allow delete community logos\"
                    ON storage.objects FOR DELETE
                    USING (bucket_id = 'community-logos');
                END IF;
            END $$;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP POLICY IF EXISTS \"Public read community logos\" ON storage.objects;");
        DB::statement("DROP POLICY IF EXISTS \"Allow upload community logos\" ON storage.objects;");
        DB::statement("DROP POLICY IF EXISTS \"Allow delete community logos\" ON storage.objects;");
        DB::statement("DELETE FROM storage.buckets WHERE id = 'community-logos';");
    }
};
