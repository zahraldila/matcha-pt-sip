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
  }) async {
    try {
      final venueInsert = await _supabase
          .from('tb_venue')
          .insert({
            'nama_venue': namaVenue,
            'alamat': alamat ?? 'Alamat belum diatur',
            'kota': kota ?? 'Bandung',
            'jam_operasional': '08:00 - 22:00 WIB',
            'hari_buka': 'Setiap Hari',
          })
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
          'tipe_court': 'Outdoor',
          'harga_per_jam': 0.0,
        });
      }

      await _supabase.from('tb_court').insert(courtsToInsert);

      return await getVenueById(newVenueId);
    } on PostgrestException catch (e) {
      throw Exception('Gagal menambahkan venue instan: ${e.message}');
    }
  }
}
