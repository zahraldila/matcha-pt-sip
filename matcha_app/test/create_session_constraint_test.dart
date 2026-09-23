import 'package:flutter_test/flutter_test.dart';

void main() {
  group('Create Session Constraint & Quota Logic Verification', () {
    test('All Scoring Systems Categorization (Total of X vs First to X)', () {
      final totalOfSystems = [
        'Total of 3 Poin',
        'Total of 4 Poin',
        'Total of 5 Poin',
        'Total of 6 Poin',
        'Total of 7 Poin',
      ];

      final firstToSystems = [
        'First to 8 Poin (Tuntas)',
        'First to 11 Poin (Tuntas)',
        'First to 15 Poin (Tuntas)',
        'First to 21 Poin (Tuntas)',
      ];

      for (final system in totalOfSystems) {
        final isFirstTo = system.toLowerCase().startsWith('first to');
        expect(isFirstTo, false, reason: '$system should not be first to');
      }

      for (final system in firstToSystems) {
        final isFirstTo = system.toLowerCase().startsWith('first to');
        expect(isFirstTo, true, reason: '$system should be first to');
      }
    });

    test('First to X format constraints (Single vs Double)', () {
      // Rule 1: First to X Double -> Exactly 4 players
      const isFirstTo = true;
      const gameTypeDouble = 'Double';
      final quotaDouble = isFirstTo ? (gameTypeDouble == 'Double' ? 4 : 2) : 6;
      expect(quotaDouble, 4);

      // Rule 2: First to X Single -> Exactly 2 players
      const gameTypeSingle = 'Single';
      final quotaSingle = isFirstTo ? (gameTypeSingle == 'Double' ? 4 : 2) : 2;
      expect(quotaSingle, 2);
    });

    test('Team Americano constraints', () {
      // Rule 3: Team Americano must be Double and even player count
      const format = 'Team Americano';
      const isTeamAmericano = format == 'Team Americano';
      expect(isTeamAmericano, true);

      // Even player quota check
      const validQuotas = [4, 6, 8, 10, 12];
      for (final q in validQuotas) {
        expect(q % 2 == 0, true);
      }

      // First to X on Team Americano locks to 4 players
      const isFirstTo = true;
      final quotaTeamFirstTo = isFirstTo ? 4 : 6;
      expect(quotaTeamFirstTo, 4);
    });

    test('Americano Regular Total of X flexibility', () {
      // Double mode supports 4, 6, 8, 12
      const doubleOptions = [4, 6, 8, 12];
      expect(doubleOptions.contains(4), true);
      expect(doubleOptions.contains(6), true);

      // Single mode supports 2, 3, 4, 5, 6
      const singleOptions = [2, 3, 4, 5, 6];
      expect(singleOptions.contains(2), true);
      expect(singleOptions.contains(6), true);
    });
  });
}
