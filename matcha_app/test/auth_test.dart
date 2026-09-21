import 'package:bcrypt/bcrypt.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/auth/domain/models/user_model.dart';

void main() {
  group('Auth & BCrypt Logic Test', () {
    test('BCrypt password hash and check test', () {
      const password = 'mySecretPassword123';
      final hashed = BCrypt.hashpw(password, BCrypt.gensalt());

      expect(hashed.isNotEmpty, true);
      expect(BCrypt.checkpw(password, hashed), true);
      expect(BCrypt.checkpw('wrongPassword', hashed), false);
    });

    test('UserModel serialization with tb_player integration test', () {
      final userJson = {
        'user_id': 1,
        'nama': 'Marcel Santoso',
        'email': 'marcel@matcha.id',
        'no_hp': '08123456789',
        'role': 'member',
        'status_user': 'Active',
        'is_host': true,
      };

      final playerJson = {
        'player_id': 10,
        'user_id': 1,
        'level': 'Advanced',
        'gender': 'Male',
        'usia': 26,
      };

      final user = UserModel.fromJson(userJson, playerJson: playerJson);

      expect(user.userId, 1);
      expect(user.nama, 'Marcel Santoso');
      expect(user.email, 'marcel@matcha.id');
      expect(user.noHp, '08123456789');
      expect(user.isHost, true);
      expect(user.playerId, 10);
      expect(user.level, 'Advanced');
      expect(user.usia, 26);
    });
  });
}
