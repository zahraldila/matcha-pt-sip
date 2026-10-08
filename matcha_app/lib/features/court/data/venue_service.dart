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

  /// Mendaftarkan venue dengan pemilik dan metadata dari form Venue Owner.
  Future<VenueModel> createVenue({
    required int ownerUserId,
    required String namaVenue,
    required String alamat,
    required String kota,
    required String jamOperasional,
    required String hariBuka,
    required String namaPic,
    required String noWhatsapp,
    required List<String> fasilitas,
    required List<String> photos,
    String? catatan,
  }) async {
    try {
      final response = await _supabase
          .from('tb_venue')
          .insert({
            'owner_user_id': ownerUserId,
            'nama_venue': namaVenue.trim(),
            'alamat': alamat.trim(),
            'kota': kota.trim(),
            'jam_operasional': jamOperasional,
            'hari_buka': hariBuka,
            'nama_pic': namaPic.trim(),
            'no_whatsapp': noWhatsapp.trim(),
            'catatan': catatan?.trim(),
            'fasilitas': fasilitas.join(', '),
            'foto': photos.isEmpty ? null : photos.join(', '),
          })
          .select()
          .single();

      return VenueModel.fromMap(Map<String, dynamic>.from(response));
    } on PostgrestException catch (e) {
      throw Exception('Gagal mendaftarkan venue: ${e.message}');
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
        final arenaType = c['tipe_court'] ?? 'Indoor';
        final pricePerHour = c['harga_per_jam'] ?? 0;
        return {
          'venue_id': venueId,
          'nama_court': c['nama_court'] ?? 'Court',
          'sport_id': c['sport_id'] ?? 1,
          'tipe_court': arenaType,
          'deskripsi': 'Tipe: $arenaType • Rp $pricePerHour/jam',
          'harga_per_jam': pricePerHour,
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
      final ext = filename.contains('.')
          ? filename.split('.').last.toLowerCase()
          : 'jpg';
      final cleanName = filename
          .split('.')
          .first
          .replaceAll(RegExp(r'[^a-zA-Z0-9]'), '_');
      final storagePath =
          'venue_${DateTime.now().millisecondsSinceEpoch}_$cleanName.$ext';

      await _supabase.storage
          .from('venues')
          .uploadBinary(
            storagePath,
            bytes,
            fileOptions: FileOptions(contentType: 'image/$ext', upsert: true),
          );

      return _supabase.storage.from('venues').getPublicUrl(storagePath);
    } catch (e) {
      try {
        final ext = filename.contains('.')
            ? filename.split('.').last.toLowerCase()
            : 'jpg';
        final storagePath =
            'venue_${DateTime.now().millisecondsSinceEpoch}.$ext';
        await _supabase.storage
            .from('general')
            .uploadBinary(
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

  /// Memperbarui informasi venue
  Future<VenueModel> updateVenue({
    required int venueId,
    required String namaVenue,
    required String alamat,
    required String kota,
    String? jamOperasional,
    String? hariBuka,
    String? namaPic,
    String? noWhatsapp,
    String? fasilitas,
    String? catatan,
  }) async {
    try {
      final payload = <String, dynamic>{
        'nama_venue': namaVenue,
        'alamat': alamat,
        'kota': kota,
      };
      if (jamOperasional != null) payload['jam_operasional'] = jamOperasional;
      if (hariBuka != null) payload['hari_buka'] = hariBuka;
      if (namaPic != null) payload['nama_pic'] = namaPic;
      if (noWhatsapp != null) payload['no_whatsapp'] = noWhatsapp;
      if (fasilitas != null) payload['fasilitas'] = fasilitas;
      if (catatan != null) payload['catatan'] = catatan;

      await _supabase.from('tb_venue').update(payload).eq('venue_id', venueId);
      return await getVenueById(venueId);
    } on PostgrestException catch (e) {
      throw Exception('Gagal memperbarui informasi venue: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan saat memperbarui venue: $e');
    }
  }

  /// Menghapus venue
  Future<void> deleteVenue(int venueId) async {
    try {
      try {
        await _supabase.from('tb_court').delete().eq('venue_id', venueId);
      } catch (_) {}
      await _supabase.from('tb_venue').delete().eq('venue_id', venueId);
    } on PostgrestException catch (e) {
      throw Exception('Gagal menghapus venue: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan saat menghapus venue: $e');
    }
  }
}
