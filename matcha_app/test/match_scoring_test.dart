import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/core/data/mock_data_service.dart';

void main() {
  group('Match & Scoring State Tests', () {
    test('Drawing match court generation test', () {
      final dataService = MockDataService();
      expect(dataService.currentDrawingMatches.length, 2);
      expect(dataService.currentDrawingMatches[0].courtNumber, 1);
      expect(dataService.currentDrawingMatches[1].courtNumber, 2);
    });

    test('Shuffle and Lock drawing toggle test', () {
      final dataService = MockDataService();
      expect(dataService.isDrawingLocked, false);

      dataService.toggleLockDrawing();
      expect(dataService.isDrawingLocked, true);

      dataService.toggleLockDrawing();
      expect(dataService.isDrawingLocked, false);
    });

    test('Finish current set test', () {
      final dataService = MockDataService();
      final initialSet = dataService.currentSet;

      dataService.finishCurrentSet();
      expect(dataService.currentSet, initialSet + 1);
      expect(dataService.teamAPoints, 0);
      expect(dataService.teamBPoints, 0);
    });
  });
}
