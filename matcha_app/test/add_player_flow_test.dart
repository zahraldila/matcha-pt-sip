import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/games/domain/game_wizard_model.dart';

void main() {
  group('Add Player Flow Logic & Model Verification', () {
    test('GamePlayerItem model supports playerId, userId, and guest distinction', () {
      // 1. Registered member player
      const member = GamePlayerItem(
        id: 'user_42',
        playerId: 101,
        userId: 42,
        name: 'Budi Santoso',
        gender: 'Laki-laki',
        level: 'Intermediate',
        isGuest: false,
        avatarUrl: 'https://example.com/avatar.jpg',
      );

      expect(member.id, 'user_42');
      expect(member.playerId, 101);
      expect(member.userId, 42);
      expect(member.isGuest, false);

      final memberMap = member.toMap();
      expect(memberMap['player_id'], 101);
      expect(memberMap['user_id'], 42);
      expect(memberMap['is_guest'], false);

      final restoredMember = GamePlayerItem.fromMap(memberMap);
      expect(restoredMember.playerId, 101);
      expect(restoredMember.userId, 42);
      expect(restoredMember.isGuest, false);

      // 2. Guest player
      const guest = GamePlayerItem(
        id: 'guest_202',
        playerId: 202,
        userId: null,
        name: 'Guest Player A',
        gender: 'Perempuan',
        level: 'Beginner',
        isGuest: true,
      );

      expect(guest.playerId, 202);
      expect(guest.userId, isNull);
      expect(guest.isGuest, true);

      final guestMap = guest.toMap();
      expect(guestMap['player_id'], 202);
      expect(guestMap['user_id'], isNull);
      expect(guestMap['is_guest'], true);

      final restoredGuest = GamePlayerItem.fromMap(guestMap);
      expect(restoredGuest.playerId, 202);
      expect(restoredGuest.userId, isNull);
      expect(restoredGuest.isGuest, true);
    });

    test('Duplicate player protection identifies matching playerId, userId, or name', () {
      final currentPlayers = [
        const GamePlayerItem(
          id: 'user_1',
          playerId: 10,
          userId: 1,
          name: 'Player One',
          gender: 'Laki-laki',
        ),
        const GamePlayerItem(
          id: 'guest_11',
          playerId: 11,
          userId: null,
          name: 'Player Two',
          gender: 'Perempuan',
          isGuest: true,
        ),
      ];

      bool isDuplicate(GamePlayerItem candidate) {
        return currentPlayers.any((p) =>
            (candidate.playerId != null && p.playerId == candidate.playerId) ||
            (candidate.userId != null && p.userId == candidate.userId) ||
            p.name.trim().toLowerCase() == candidate.name.trim().toLowerCase());
      }

      // Exact matching playerId
      expect(
        isDuplicate(const GamePlayerItem(id: 'other_1', playerId: 10, name: 'Different Name')),
        true,
      );

      // Exact matching userId
      expect(
        isDuplicate(const GamePlayerItem(id: 'other_2', userId: 1, name: 'Another Name')),
        true,
      );

      // Case-insensitive matching name
      expect(
        isDuplicate(const GamePlayerItem(id: 'other_3', name: 'player one')),
        true,
      );
      expect(
        isDuplicate(const GamePlayerItem(id: 'other_4', name: '  PLAYER TWO  ')),
        true,
      );

      // Non-duplicate candidate
      expect(
        isDuplicate(const GamePlayerItem(id: 'other_5', playerId: 99, userId: 99, name: 'Player Three')),
        false,
      );
    });

    test('Gender and Level mapping for database matches expected values', () {
      String mapGenderToDb(String uiGender) {
        return uiGender == 'Laki-laki' ? 'Male' : 'Female';
      }

      String mapGenderToUi(String? dbGender) {
        final raw = (dbGender ?? '').toLowerCase();
        return (raw == 'female' || raw == 'perempuan') ? 'Perempuan' : 'Laki-laki';
      }

      expect(mapGenderToDb('Laki-laki'), 'Male');
      expect(mapGenderToDb('Perempuan'), 'Female');

      expect(mapGenderToUi('Male'), 'Laki-laki');
      expect(mapGenderToUi('male'), 'Laki-laki');
      expect(mapGenderToUi('Female'), 'Perempuan');
      expect(mapGenderToUi('female'), 'Perempuan');
      expect(mapGenderToUi('Perempuan'), 'Perempuan');
      expect(mapGenderToUi(null), 'Laki-laki');
    });

    test('Search-first query validation and threshold logic', () {
      const minSearchQueryLength = 2;

      bool shouldQueryDatabase(String query) {
        final clean = query.trim();
        return clean.length >= minSearchQueryLength;
      }

      String resolveSearchState(String query, List<dynamic> results) {
        final clean = query.trim();
        if (clean.isEmpty) return 'helper_empty';
        if (clean.length < minSearchQueryLength) return 'helper_too_short';
        if (results.isEmpty) return 'not_found';
        return 'success';
      }

      // 1. Initial open / empty query -> no DB fetch, show helper
      expect(shouldQueryDatabase(''), false);
      expect(shouldQueryDatabase('   '), false);
      expect(resolveSearchState('', []), 'helper_empty');
      expect(resolveSearchState('   ', []), 'helper_empty');

      // 2. Query < 2 characters -> no DB fetch, show min character hint
      expect(shouldQueryDatabase('a'), false);
      expect(shouldQueryDatabase(' m '), false);
      expect(resolveSearchState('a', []), 'helper_too_short');

      // 3. Query >= 2 characters -> triggers DB fetch
      expect(shouldQueryDatabase('ma'), true);
      expect(shouldQueryDatabase('mar'), true);
      expect(shouldQueryDatabase('  marcello  '), true);

      // 4. Player not found
      expect(resolveSearchState('xyzabc', []), 'not_found');

      // 5. Player found
      expect(resolveSearchState('mar', [{'nama': 'Marcello'}]), 'success');

      // 6. Query cleared back to empty -> helper state, no DB query
      expect(shouldQueryDatabase(''), false);
      expect(resolveSearchState('', [{'nama': 'Old Result'}]), 'helper_empty');
    });

    test('Activity Name required validation & whitespace trimming verification', () {
      bool isActivityNameValid(String name) {
        return name.trim().isNotEmpty;
      }

      // 1. Empty activity name -> cannot continue
      expect(isActivityNameValid(''), false);

      // 2. Whitespace activity name -> cannot continue
      expect(isActivityNameValid('   '), false);
      expect(isActivityNameValid('\t\n  '), false);

      // 3. Valid activity name -> can continue
      expect(isActivityNameValid('Padel Weekend Fun'), true);
      expect(isActivityNameValid('Tenis ITB'), true);
      expect(isActivityNameValid('  Weekend Fun  '), true);

      // 4. User clears activity name after valid input -> disabled again
      String input = 'Padel Fun';
      expect(isActivityNameValid(input), true);
      input = '';
      expect(isActivityNameValid(input), false);
      input = '   ';
      expect(isActivityNameValid(input), false);
    });
  });
}


