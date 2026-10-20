import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/community/domain/community_model.dart';

void main() {
  group('CommunityModel Tests', () {
    test('CommunityModel correctly parses Supabase map with default fallbacks', () {
      final map = {
        'community_id': 10,
        'nama_community': 'Jakarta Padel Society',
        'deskripsi': 'Komunitas padel asik dan seru.',
        'sport': 'Padel',
        'tagline': 'Play, Smash, Repeat!',
        'kota_homebase': 'Jakarta Selatan',
        'status_keanggotaan': 'Open',
        'tb_user': {'nama': 'John Doe'},
        'tb_player': [
          {'user_id': 101, 'tb_user': {'nama': 'Member 1'}},
          {'user_id': 102, 'tb_user': {'nama': 'Member 2'}},
        ],
      };

      final com = CommunityModel.fromMap(map, currentUserId: 101);

      expect(com.communityId, 10);
      expect(com.namaCommunity, 'Jakarta Padel Society');
      expect(com.adminName, 'John Doe');
      expect(com.memberCount, 2);
      expect(com.isMember, true);
      expect(com.statusKeanggotaan, 'Open');
      expect(com.sport, 'Padel');
    });

    test('CommunityModel handles null fields gracefully and falls back to default images', () {
      final map = {
        'community_id': 20,
        'nama_community': 'Tennis Solo Club',
        'sport': 'Tennis',
      };

      final com = CommunityModel.fromMap(map);

      expect(com.adminName, 'Admin Komunitas');
      expect(com.memberCount, 0);
      expect(com.isMember, false);
      expect(com.statusKeanggotaan, 'Open');
      expect(com.displayImage.isNotEmpty, true);
    });
  });
}
