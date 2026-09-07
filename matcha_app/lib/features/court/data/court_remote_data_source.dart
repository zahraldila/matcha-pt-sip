import 'package:supabase_flutter/supabase_flutter.dart';
import '../domain/court_model.dart';

class CourtRemoteDataSource {
  final SupabaseClient _supabase;

  CourtRemoteDataSource({
    SupabaseClient? supabase,
  }) : _supabase = supabase ?? Supabase.instance.client;

  Future<List<CourtModel>> getActiveCourts() async {
    final response = await _supabase
        .from('tb_court')
        .select()
        .eq('status_aktif', 'active')
        .order('nama_court');

    return (response as List)
        .map(
          (item) => CourtModel.fromMap(
            Map<String, dynamic>.from(item),
          ),
        )
        .toList();
  }

  Future<CourtModel> getCourtById(int courtId) async {
    final response = await _supabase
        .from('tb_court')
        .select()
        .eq('court_id', courtId)
        .single();

    return CourtModel.fromMap(
      Map<String, dynamic>.from(response),
    );
  }
}