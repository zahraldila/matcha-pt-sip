import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/match/presentation/match_scoring_page.dart';

void main() {
  testWidgets('MatchScoringPage widget smoke test', (WidgetTester tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: MatchScoringPage(
          sessionName: 'Saturday Morning',
          courtName: 'Court 1',
          sideA: 'Aldi · Budi',
          sideB: 'Caca · Dina',
        ),
      ),
    );
    expect(find.text('Input Score'), findsOneWidget);
    expect(find.text('Court 1'), findsOneWidget);
  });
}
