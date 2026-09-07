import 'package:supabase_flutter/supabase_flutter.dart';

import '../domain/community_model.dart';

class CommunityRemoteDataSource {
  final SupabaseClient _supabase;

  CommunityRemoteDataSource({
    SupabaseClient? supabase,
  }) : _supabase = supabase ?? Supabase.instance.client;

  Future<List<CommunityModel>> getActiveCommunities() async {
    final response = await _supabase
        .from('tb_community')
        .select()
        .eq('status', 'active')
        .order('nama_community');

    return (response as List)
        .map(
          (item) => CommunityModel.fromMap(
            Map<String, dynamic>.from(item),
          ),
        )
        .toList();
  }

  Future<CommunityModel> getCommunityById(int communityId) async {
    final response = await _supabase
        .from('tb_community')
        .select()
        .eq('community_id', communityId)
        .single();

    return CommunityModel.fromMap(
      Map<String, dynamic>.from(response),
    );
  }
}