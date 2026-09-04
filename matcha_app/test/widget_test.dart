import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/main.dart';

void main() {
  testWidgets('Matcha app smoke test', (WidgetTester tester) async {
    await tester.pumpWidget(const MatchaApp());
    expect(find.text('MATCHA'), findsOneWidget);
  });
}
