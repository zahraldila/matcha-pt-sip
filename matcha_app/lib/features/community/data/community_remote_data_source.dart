import 'package:supabase_flutter/supabase_flutter.dart';
import '../domain/community_model.dart';

class CommunityRemoteDataSource {
  final SupabaseClient _supabase;

  CommunityRemoteDataSource({
    SupabaseClient? supabase,
  }) : _supabase = supabase ?? Supabase.instance.client;

  /// Mengambil seluruh data komunitas dari database Supabase
  Future<List<CommunityModel>> getCommunities({int? currentUserId}) async {
    try {
      final response = await _supabase.from('tb_community').select('''
        community_id,
        nama_community,
        deskripsi,
        logo,
        sport,
        tagline,
        kota_homebase,
        target_level,
        status_keanggotaan,
        jadwal_rutin,
        homebase_venue,
        benefits,
        created_by,
        created_at,
        updated_at,
        tb_user:created_by (
          user_id,
          nama
        ),
        tb_player (
          player_id,
          user_id
        )
      ''').order('community_id', ascending: false);

      return (response as List)
          .map((item) => CommunityModel.fromMap(
                Map<String, dynamic>.from(item),
                currentUserId: currentUserId,
              ))
          .toList();
    } on PostgrestException catch (e) {
      throw Exception('Gagal memuat komunitas: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan: $e');
    }
  }

  /// Mengambil detail satu komunitas beserta daftar pemain/anggotanya
  Future<Map<String, dynamic>> getCommunityDetail(int communityId, {int? currentUserId}) async {
    try {
      final response = await _supabase
          .from('tb_community')
          .select('''
            community_id,
            nama_community,
            deskripsi,
            logo,
            sport,
            tagline,
            kota_homebase,
            target_level,
            status_keanggotaan,
            jadwal_rutin,
            homebase_venue,
            benefits,
            created_by,
            created_at,
            updated_at,
            tb_user:created_by (
              user_id,
              nama,
              email,
              no_hp
            ),
            tb_player (
              player_id,
              user_id,
              nama,
              level,
              gender,
              rating,
              no_hp,
              email
            )
          ''')
          .eq('community_id', communityId)
          .single();

      final model = CommunityModel.fromMap(
        Map<String, dynamic>.from(response),
        currentUserId: currentUserId,
      );

      final members = (response['tb_player'] as List? ?? [])
          .map((p) => Map<String, dynamic>.from(p))
          .toList();

      return {
        'community': model,
        'members': members,
      };
    } on PostgrestException catch (e) {
      throw Exception('Gagal memuat detail komunitas: ${e.message}');
    }
  }

  /// Bergabung ke komunitas (insert ke tb_player)
  Future<void> joinCommunity({
    required int communityId,
    required int userId,
    required String nama,
    String? noHp,
    String? email,
  }) async {
    try {
      // Cek apakah sudah bergabung
      final existing = await _supabase
          .from('tb_player')
          .select('player_id')
          .eq('user_id', userId)
          .eq('community_id', communityId)
          .maybeSingle();

      if (existing != null) return;

      await _supabase.from('tb_player').insert({
        'user_id': userId,
        'community_id': communityId,
        'nama': nama,
        'rating': 1.00,
        'no_hp': noHp,
        'email': email,
      });
    } on PostgrestException catch (e) {
      throw Exception('Gagal bergabung ke komunitas: ${e.message}');
    }
  }

  /// Meninggalkan komunitas
  Future<void> leaveCommunity({
    required int communityId,
    required int userId,
  }) async {
    try {
      await _supabase
          .from('tb_player')
          .update({'community_id': null})
          .eq('user_id', userId)
          .eq('community_id', communityId);
    } on PostgrestException catch (e) {
      throw Exception('Gagal keluar dari komunitas: ${e.message}');
    }
  }

  /// Membuat komunitas baru
  Future<CommunityModel> createCommunity({
    required String namaCommunity,
    required String deskripsi,
    required String sport,
    String? tagline,
    String? kotaHomebase,
    String? targetLevel,
    String? statusKeanggotaan,
    String? jadwalRutin,
    String? homebaseVenue,
    List<String>? benefits,
    int? createdBy,
    String? creatorName,
  }) async {
    try {
      final insertRes = await _supabase
          .from('tb_community')
          .insert({
            'nama_community': namaCommunity,
            'deskripsi': deskripsi,
            'sport': sport,
            'tagline': tagline ?? 'Komunitas Olahraga Matcha',
            'kota_homebase': (kotaHomebase != null && kotaHomebase.isNotEmpty) ? kotaHomebase : 'Bandung',
            'target_level': targetLevel ?? 'All Levels',
            'status_keanggotaan': statusKeanggotaan ?? 'Open',
            'jadwal_rutin': jadwalRutin ?? 'Rutin Setiap Pekan',
            'homebase_venue': homebaseVenue,
            'benefits': benefits ?? ['sesi mabar mingguan', 'whatsapp group aktif'],
            'created_by': createdBy,
          })
          .select()
          .single();

      final newCommunityId = insertRes['community_id'] as int;

      // Daftarkan pembuat ke tb_player
      if (createdBy != null && creatorName != null) {
        await _supabase.from('tb_player').insert({
          'user_id': createdBy,
          'community_id': newCommunityId,
          'nama': creatorName,
          'rating': 1.00,
        });
      }

      final detail = await getCommunityDetail(newCommunityId, currentUserId: createdBy);
      return detail['community'] as CommunityModel;
    } on PostgrestException catch (e) {
      throw Exception('Gagal membuat komunitas: ${e.message}');
    }
  }
}