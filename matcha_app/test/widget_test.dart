import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/main.dart';
import 'package:matcha_app/features/splash/presentation/splash_page.dart';

void main() {
  testWidgets('Matcha splash screen smoke test', (WidgetTester tester) async {
    await tester.pumpWidget(const MatchaApp());
    expect(find.byType(SplashPage), findsOneWidget);
  });
}
