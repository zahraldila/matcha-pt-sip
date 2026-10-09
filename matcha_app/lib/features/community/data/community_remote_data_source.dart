import 'dart:typed_data';
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
      ''').order('nama_community', ascending: true);

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
              email,
              foto,
              tb_user (
                foto
              )
            )
          ''')
          .eq('community_id', communityId)
          .single();

      final model = CommunityModel.fromMap(
        Map<String, dynamic>.from(response),
        currentUserId: currentUserId,
      );

      final members = (response['tb_player'] as List? ?? [])
          .map((p) {
            final copy = Map<String, dynamic>.from(p);
            String? fotoUrl = copy['foto'] as String?;
            if ((fotoUrl == null || fotoUrl.trim().isEmpty) && copy['tb_user'] is Map) {
              fotoUrl = copy['tb_user']['foto'] as String?;
            }
            copy['foto'] = (fotoUrl != null && fotoUrl.trim().isNotEmpty) ? fotoUrl.trim() : null;
            return copy;
          })
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
      final List<dynamic> existing = await _supabase
          .from('tb_player')
          .select('player_id')
          .eq('user_id', userId)
          .eq('community_id', communityId)
          .limit(1);

      if (existing.isNotEmpty) return;

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
    String? logo,
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
            'benefits': benefits ?? ['weekly_mabar', 'whatsapp_group'],
            if (logo != null && logo.isNotEmpty) 'logo': logo,
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

  /// Upload file logo / foto komunitas ke Supabase Storage
  Future<String?> uploadCommunityLogo(List<int> bytes, String filename) async {
    try {
      final ext = filename.contains('.') ? filename.split('.').last.toLowerCase() : 'jpg';
      final storagePath = 'comm_${DateTime.now().millisecondsSinceEpoch}.$ext';
      
      await _supabase.storage.from('community-logos').uploadBinary(
            storagePath,
            Uint8List.fromList(bytes),
            fileOptions: FileOptions(
              contentType: 'image/$ext',
              upsert: true,
            ),
          );

      return _supabase.storage.from('community-logos').getPublicUrl(storagePath);
    } catch (_) {
      try {
        // Fallback coba ke bucket umum jika community-logos belum ada
        final ext = filename.contains('.') ? filename.split('.').last.toLowerCase() : 'jpg';
        final storagePath = 'comm_${DateTime.now().millisecondsSinceEpoch}.$ext';
        await _supabase.storage.from('general').uploadBinary(
              storagePath,
              Uint8List.fromList(bytes),
              fileOptions: FileOptions(contentType: 'image/$ext', upsert: true),
            );
        return _supabase.storage.from('general').getPublicUrl(storagePath);
      } catch (_) {
        return null;
      }
    }
  }

  /// Menonaktifkan komunitas (Soft delete/deactivation oleh Admin atau Pembuat)
  Future<void> deactivateCommunity(int communityId) async {
    try {
      await _supabase
          .from('tb_community')
          .update({'status_keanggotaan': 'Closed'})
          .eq('community_id', communityId);
    } on PostgrestException catch (e) {
      throw Exception('Gagal menonaktifkan komunitas: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan: $e');
    }
  }

  /// Memperbarui informasi data komunitas (Admin / Pembuat)
  Future<void> updateCommunity({
    required int communityId,
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
    String? logo,
  }) async {
    try {
      final updateData = <String, dynamic>{
        'nama_community': namaCommunity,
        'deskripsi': deskripsi,
        'sport': sport,
        if (tagline != null) 'tagline': tagline,
        if (kotaHomebase != null) 'kota_homebase': kotaHomebase,
        if (targetLevel != null) 'target_level': targetLevel,
        if (statusKeanggotaan != null) 'status_keanggotaan': statusKeanggotaan,
        if (jadwalRutin != null) 'jadwal_rutin': jadwalRutin,
        if (homebaseVenue != null) 'homebase_venue': homebaseVenue,
        if (benefits != null) 'benefits': benefits,
        if (logo != null && logo.isNotEmpty) 'logo': logo,
        'updated_at': DateTime.now().toIso8601String(),
      };

      await _supabase.from('tb_community').update(updateData).eq('community_id', communityId);
    } on PostgrestException catch (e) {
      throw Exception('Gagal memperbarui komunitas: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan: $e');
    }
  }

  /// Menghapus komunitas secara permanen dari database (Admin / Pembuat)
  Future<void> deleteCommunity(int communityId) async {
    try {
      // 1. Bersihkan relasi anggota komunitas di tb_player
      try {
        await _supabase
            .from('tb_player')
            .delete()
            .eq('community_id', communityId);
      } catch (_) {
        try {
          await _supabase
              .from('tb_player')
              .update({'community_id': null})
              .eq('community_id', communityId);
        } catch (_) {}
      }

      // 2. Hapus record komunitas dari tb_community
      await _supabase.from('tb_community').delete().eq('community_id', communityId);
    } on PostgrestException catch (e) {
      throw Exception('Gagal menghapus komunitas: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan: $e');
    }
  }
}