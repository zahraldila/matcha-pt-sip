import 'dart:typed_data';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../domain/venue_model.dart';

class VenueService {
  final SupabaseClient _supabase;

  VenueService({SupabaseClient? supabaseClient})
      : _supabase = supabaseClient ?? Supabase.instance.client;

  /// Mengambil semua venue beserta daftar court/lapangan
  Future<List<VenueModel>> getVenues() async {
    try {
      final response = await _supabase
          .from('tb_venue')
          .select('''
            venue_id,
            owner_user_id,
            nama_venue,
            alamat,
            foto,
            fasilitas,
            catatan,
            kota,
            jam_operasional,
            hari_buka,
            no_whatsapp,
            nama_pic,
            tb_court (
              court_id,
              venue_id,
              sport_id,
              nama_court,
              status_ketersediaan,
              image_url,
              deskripsi,
              tipe_court,
              harga_per_jam
            )
          ''')
          .order('nama_venue');

      return (response as List)
          .map((item) => VenueModel.fromMap(Map<String, dynamic>.from(item)))
          .toList();
    } on PostgrestException catch (e) {
      throw Exception('Gagal mengambil data venue: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan saat memuat venue: $e');
    }
  }

  /// Mengambil venue berdasarkan ID
  Future<VenueModel> getVenueById(int venueId) async {
    try {
      final response = await _supabase
          .from('tb_venue')
          .select('''
            venue_id,
            owner_user_id,
            nama_venue,
            alamat,
            foto,
            fasilitas,
            catatan,
            kota,
            jam_operasional,
            hari_buka,
            no_whatsapp,
            nama_pic,
            tb_court (
              court_id,
              venue_id,
              sport_id,
              nama_court,
              status_ketersediaan,
              image_url,
              deskripsi,
              tipe_court,
              harga_per_jam
            )
          ''')
          .eq('venue_id', venueId)
          .single();

      return VenueModel.fromMap(Map<String, dynamic>.from(response));
    } on PostgrestException catch (e) {
      throw Exception('Gagal mengambil detail venue: ${e.message}');
    }
  }

  /// Quick add venue on-the-fly (oleh Host saat membuat sesi mabar)
  Future<VenueModel> quickAddVenue({
    required String namaVenue,
    String? alamat,
    String? kota,
    int numberOfCourts = 1,
    int? sportId,
    int? ownerUserId,
  }) async {
    try {
      int? effectiveOwnerId = ownerUserId;
      if (effectiveOwnerId == null || effectiveOwnerId <= 0) {
        // Fallback: ambil user_id dari venue yang sudah ada atau tb_user
        final existingVenue = await _supabase
            .from('tb_venue')
            .select('owner_user_id')
            .not('owner_user_id', 'is', null)
            .limit(1)
            .maybeSingle();

        if (existingVenue != null && existingVenue['owner_user_id'] != null) {
          effectiveOwnerId = existingVenue['owner_user_id'] as int;
        } else {
          final firstUser = await _supabase
              .from('tb_user')
              .select('user_id')
              .limit(1)
              .maybeSingle();
          if (firstUser != null && firstUser['user_id'] != null) {
            effectiveOwnerId = firstUser['user_id'] as int;
          }
        }
      }

      final Map<String, dynamic> insertPayload = {
        'nama_venue': namaVenue,
        'alamat': (alamat != null && alamat.isNotEmpty) ? alamat : namaVenue,
        'kota': (kota != null && kota.isNotEmpty) ? kota : 'Bandung',
        'jam_operasional': '08:00 - 22:00 WIB',
        'hari_buka': 'Setiap Hari (Senin - Minggu)',
        'fasilitas': 'Parkir, Toilet, Ruang Ganti',
      };

      if (effectiveOwnerId != null) {
        insertPayload['owner_user_id'] = effectiveOwnerId;
      }

      final venueInsert = await _supabase
          .from('tb_venue')
          .insert(insertPayload)
          .select()
          .single();

      final newVenueId = venueInsert['venue_id'] as int;

      // Auto-generate courts
      final List<Map<String, dynamic>> courtsToInsert = [];
      for (int i = 1; i <= numberOfCourts; i++) {
        courtsToInsert.add({
          'venue_id': newVenueId,
          'sport_id': sportId ?? 1,
          'nama_court': 'Court $i',
          'status_ketersediaan': 'Available',
          'tipe_court': 'Indoor',
          'deskripsi': 'Tipe: Indoor',
          'harga_per_jam': 0.0,
        });
      }

      await _supabase.from('tb_court').insert(courtsToInsert);

      return await getVenueById(newVenueId);
    } on PostgrestException catch (e) {
      throw Exception('Gagal menambahkan venue instan: ${e.message}');
    } catch (e) {
      throw Exception('Gagal menambahkan venue instan: $e');
    }
  }

  /// Menambahkan daftar court baru ke venue
  Future<void> addCourtsToVenue({
    required int venueId,
    required List<Map<String, dynamic>> courts,
  }) async {
    try {
      final List<Map<String, dynamic>> payload = courts.map((c) {
        return {
          'venue_id': venueId,
          'nama_court': c['nama_court'] ?? 'Court',
          'sport_id': c['sport_id'] ?? 1,
          'tipe_court': c['tipe_court'] ?? 'Indoor',
          'deskripsi': 'Tipe: ${c['tipe_court'] ?? "Indoor"}',
          'harga_per_jam': c['harga_per_jam'] ?? 0,
          'status_ketersediaan': 'Available',
        };
      }).toList();

      await _supabase.from('tb_court').insert(payload);
    } on PostgrestException catch (e) {
      throw Exception('Gagal menyimpan lapangan: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan: $e');
    }
  }

  /// Upload foto venue ke Supabase Storage bucket 'venues'
  Future<String> uploadVenuePhoto(Uint8List bytes, String filename) async {
    try {
      final ext = filename.contains('.') ? filename.split('.').last.toLowerCase() : 'jpg';
      final cleanName = filename.split('.').first.replaceAll(RegExp(r'[^a-zA-Z0-9]'), '_');
      final storagePath = 'venue_${DateTime.now().millisecondsSinceEpoch}_$cleanName.$ext';

      await _supabase.storage.from('venues').uploadBinary(
            storagePath,
            bytes,
            fileOptions: FileOptions(
              contentType: 'image/$ext',
              upsert: true,
            ),
          );

      return _supabase.storage.from('venues').getPublicUrl(storagePath);
    } catch (e) {
      try {
        final ext = filename.contains('.') ? filename.split('.').last.toLowerCase() : 'jpg';
        final storagePath = 'venue_${DateTime.now().millisecondsSinceEpoch}.$ext';
        await _supabase.storage.from('general').uploadBinary(
              storagePath,
              bytes,
              fileOptions: FileOptions(contentType: 'image/$ext', upsert: true),
            );
        return _supabase.storage.from('general').getPublicUrl(storagePath);
      } catch (_) {
        throw Exception('Gagal mengunggah foto ke storage: $e');
      }
    }
  }

  /// Update kolom foto pada tb_venue
  Future<void> updateVenuePhotos({
    required int venueId,
    required List<String> photos,
  }) async {
    try {
      final fotoString = photos.isEmpty ? null : photos.join(', ');
      await _supabase
          .from('tb_venue')
          .update({'foto': fotoString})
          .eq('venue_id', venueId);
    } on PostgrestException catch (e) {
      throw Exception('Gagal memperbarui foto venue: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan saat memperbarui foto: $e');
    }
  }
}
