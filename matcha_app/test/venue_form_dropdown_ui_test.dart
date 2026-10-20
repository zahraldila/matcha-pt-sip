import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/auth/data/datasource/auth_remote_data_source.dart';
import 'package:matcha_app/features/auth/domain/models/user_model.dart';
import 'package:matcha_app/features/auth/presentation/controllers/auth_controller.dart';
import 'package:matcha_app/features/court/data/venue_service.dart';
import 'package:matcha_app/features/court/presentation/create_venue_page.dart';

class _FakeAuthDataSource extends Fake implements AuthRemoteDataSource {}
class _FakeVenueService extends Fake implements VenueService {}

class _MockAuthController extends AuthController {
  UserModel? _mockUser;
  _MockAuthController({UserModel? initialUser}) : super(authDataSource: _FakeAuthDataSource()) {
    _mockUser = initialUser;
  }

  @override
  UserModel? get currentUser => _mockUser;
}

void main() {
  group('Venue Form Dropdown UI Tests (Jumlah Court & Tipe Arena)', () {
    late _MockAuthController venueOwnerAuth;
    late _FakeVenueService fakeVenueService;

    setUp(() {
      fakeVenueService = _FakeVenueService();
      venueOwnerAuth = _MockAuthController(
        initialUser: const UserModel(
          userId: 77,
          nama: 'Budi Owner',
          email: 'owner@matcha.id',
          role: 'venue_owner',
        ),
      );
    });

    void setTestSize(WidgetTester tester, Size size) {
      tester.view.physicalSize = size;
      tester.view.devicePixelRatio = 1.0;
      addTearDown(() {
        tester.view.resetPhysicalSize();
        tester.view.resetDevicePixelRatio();
      });
    }

    testWidgets('1. Both dropdowns render with correct initial values and labels', (tester) async {
      setTestSize(tester, const Size(500, 1000));

      await tester.pumpWidget(
        MaterialApp(
          home: CreateVenuePage(
            authController: venueOwnerAuth,
            venueService: fakeVenueService,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Check field labels exist
      expect(
        find.byWidgetPredicate((w) => w is RichText && w.text.toPlainText().contains('Jumlah Court')),
        findsOneWidget,
      );
      expect(
        find.byWidgetPredicate((w) => w is RichText && w.text.toPlainText().contains('Tipe Arena')),
        findsOneWidget,
      );

      // Check initial values are displayed
      expect(find.text('1 Court'), findsOneWidget);
      expect(find.text('Semi-Indoor (Atap Pelindung)'), findsOneWidget);
    });

    testWidgets('2. Stacks vertically on narrow viewport (< 380px)', (tester) async {
      setTestSize(tester, const Size(350, 1000));

      await tester.pumpWidget(
        MaterialApp(
          home: CreateVenuePage(
            authController: venueOwnerAuth,
            venueService: fakeVenueService,
          ),
        ),
      );
      await tester.pumpAndSettle();

      final courtFinder = find.byType(MatchaAnchoredDropdown<int>);
      final arenaFinder = find.byType(MatchaAnchoredDropdown<String>);

      expect(courtFinder, findsOneWidget);
      expect(arenaFinder, findsOneWidget);

      final courtTop = tester.getTopLeft(courtFinder).dy;
      final arenaTop = tester.getTopLeft(arenaFinder).dy;

      // In vertical Column layout, arena must be below court
      expect(arenaTop, greaterThan(courtTop + 30));
    });

    testWidgets('3. Sits side-by-side on wide viewport (>= 380px)', (tester) async {
      setTestSize(tester, const Size(600, 1000));

      await tester.pumpWidget(
        MaterialApp(
          home: CreateVenuePage(
            authController: venueOwnerAuth,
            venueService: fakeVenueService,
          ),
        ),
      );
      await tester.pumpAndSettle();

      final courtFinder = find.byType(MatchaAnchoredDropdown<int>);
      final arenaFinder = find.byType(MatchaAnchoredDropdown<String>);

      expect(courtFinder, findsOneWidget);
      expect(arenaFinder, findsOneWidget);

      final courtTop = tester.getTopLeft(courtFinder).dy;
      final arenaTop = tester.getTopLeft(arenaFinder).dy;

      // In side-by-side Row layout, top coordinates are aligned
      expect((arenaTop - courtTop).abs(), lessThan(2.0));
      // And arena is to the right of court
      expect(tester.getTopLeft(arenaFinder).dx, greaterThan(tester.getTopLeft(courtFinder).dx));
    });

    testWidgets('4. Anchored menu opens directly below with exact field width and 6px gap', (tester) async {
      setTestSize(tester, const Size(500, 1600));

      await tester.pumpWidget(
        MaterialApp(
          home: CreateVenuePage(
            authController: venueOwnerAuth,
            venueService: fakeVenueService,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Find trigger field container
      final courtDropdownFinder = find.byType(MatchaAnchoredDropdown<int>);
      expect(courtDropdownFinder, findsOneWidget);
      final triggerBox = tester.renderObject<RenderBox>(
        find.descendant(of: courtDropdownFinder, matching: find.byType(AnimatedContainer)),
      );
      final triggerWidth = triggerBox.size.width;
      final triggerBottom = triggerBox.localToGlobal(Offset(0, triggerBox.size.height)).dy;

      // Tap to open
      await tester.tap(find.text('1 Court'));
      await tester.pumpAndSettle();

      // Menu should be open
      expect(find.text('6+ Courts (Arena Besar)'), findsOneWidget);

      // Verify the menu container has EXACT width of the trigger field
      final menuFinder = find.byKey(const ValueKey('matcha_dropdown_menu'));
      expect(menuFinder, findsOneWidget);
      final menuRenderBox = tester.renderObject<RenderBox>(menuFinder);
      expect(menuRenderBox.size.width, equals(triggerWidth));

      // Verify menu top is 6px below trigger bottom (within 1px tolerance)
      final menuTop = menuRenderBox.localToGlobal(Offset.zero).dy;
      expect((menuTop - triggerBottom - 6.0).abs(), lessThanOrEqualTo(1.0));
    });

    testWidgets('5. Selected item has MATCHA soft lime background and checkmark; no default grey block', (tester) async {
      setTestSize(tester, const Size(500, 1600));

      await tester.pumpWidget(
        MaterialApp(
          home: CreateVenuePage(
            authController: venueOwnerAuth,
            venueService: fakeVenueService,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Tap to open Jumlah Court dropdown
      await tester.tap(find.text('1 Court'));
      await tester.pumpAndSettle();

      // Selected item ('1 Court') should have checkmark icon
      expect(find.byIcon(Icons.check_rounded), findsOneWidget);

      // Find the Container of selected item '1 Court' inside the menu
      final selectedItemContainer = find.ancestor(
        of: find.byIcon(Icons.check_rounded),
        matching: find.byType(Container),
      ).first;

      final containerWidget = tester.widget<Container>(selectedItemContainer);
      expect(containerWidget.color, equals(const Color(0xFFEBF8D8)));

      // Non-selected option '3 Courts' should NOT have checkmark and should have transparent background
      final unselectedItemRow = find.ancestor(
        of: find.text('3 Courts'),
        matching: find.byType(Container),
      ).first;
      final unselectedWidget = tester.widget<Container>(unselectedItemRow);
      expect(unselectedWidget.color, equals(Colors.transparent));
    });

    testWidgets('6. Selecting new option updates value immediately and closes menu', (tester) async {
      setTestSize(tester, const Size(500, 1600));

      await tester.pumpWidget(
        MaterialApp(
          home: CreateVenuePage(
            authController: venueOwnerAuth,
            venueService: fakeVenueService,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Open Jumlah Court dropdown
      await tester.tap(find.text('1 Court'));
      await tester.pumpAndSettle();

      // Tap 3 Courts
      await tester.tap(find.text('3 Courts'));
      await tester.pumpAndSettle();

      // Menu closed, value updated
      expect(find.text('3 Courts'), findsOneWidget);
      expect(find.byKey(const ValueKey('matcha_dropdown_menu')), findsNothing);

      // Open Tipe Arena dropdown
      await tester.tap(find.text('Semi-Indoor (Atap Pelindung)'));
      await tester.pumpAndSettle();

      expect(find.text('Indoor (Full AC / Tertutup)'), findsOneWidget);
      expect(find.text('Outdoor (Terbuka)'), findsOneWidget);

      // Tap Indoor
      await tester.tap(find.text('Indoor (Full AC / Tertutup)'));
      await tester.pumpAndSettle();

      // Menu closed, value updated
      expect(find.text('Indoor (Full AC / Tertutup)'), findsOneWidget);
      expect(find.byKey(const ValueKey('matcha_dropdown_menu')), findsNothing);
    });

    testWidgets('7. Tap outside dismisses menu without changing value', (tester) async {
      setTestSize(tester, const Size(500, 1600));

      await tester.pumpWidget(
        MaterialApp(
          home: CreateVenuePage(
            authController: venueOwnerAuth,
            venueService: fakeVenueService,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Open Jumlah Court dropdown
      await tester.tap(find.text('1 Court'));
      await tester.pumpAndSettle();

      expect(find.byKey(const ValueKey('matcha_dropdown_menu')), findsOneWidget);

      // Tap outside (e.g. top banner / header)
      await tester.tapAt(const Offset(20, 20));
      await tester.pumpAndSettle();

      // Menu should be closed
      expect(find.byKey(const ValueKey('matcha_dropdown_menu')), findsNothing);
      expect(find.text('1 Court'), findsOneWidget);
    });

    testWidgets('8. Reset form restores initial court count and arena type', (tester) async {
      setTestSize(tester, const Size(500, 1800));

      await tester.pumpWidget(
        MaterialApp(
          home: CreateVenuePage(
            authController: venueOwnerAuth,
            venueService: fakeVenueService,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Change court to 4
      await tester.tap(find.text('1 Court'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('4 Courts'));
      await tester.pumpAndSettle();
      expect(find.text('4 Courts'), findsOneWidget);

      // Scroll to Reset button and tap
      await tester.scrollUntilVisible(
        find.text('Reset Form'),
        300,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      expect(find.text('Reset Form'), findsOneWidget);
      await tester.tap(find.text('Reset Form'));
      await tester.pumpAndSettle();

      // Scroll back up and verify reset to 1 Court and Semi-Indoor
      await tester.scrollUntilVisible(
        find.text('1 Court'),
        -300,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      expect(find.text('1 Court'), findsOneWidget);
      expect(find.text('Semi-Indoor (Atap Pelindung)'), findsOneWidget);
    });

    testWidgets('9. Anchored menu opens above if bottom space is restricted', (tester) async {
      setTestSize(tester, const Size(500, 600));

      int selectedCourt = 1;
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Align(
              alignment: Alignment.bottomCenter,
              child: Padding(
                padding: const EdgeInsets.only(bottom: 24, left: 16, right: 16),
                child: MatchaAnchoredDropdown<int>(
                  label: 'Jumlah Court',
                  value: selectedCourt,
                  options: const [
                    DropdownOption<int>(value: 1, label: '1 Court'),
                    DropdownOption<int>(value: 2, label: '2 Courts'),
                    DropdownOption<int>(value: 3, label: '3 Courts'),
                    DropdownOption<int>(value: 4, label: '4 Courts'),
                    DropdownOption<int>(value: 6, label: '6+ Courts (Arena Besar)'),
                  ],
                  onChanged: (v) => selectedCourt = v,
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      final courtDropdownFinder = find.byType(MatchaAnchoredDropdown<int>);
      final triggerBox = tester.renderObject<RenderBox>(
        find.descendant(of: courtDropdownFinder, matching: find.byType(AnimatedContainer)),
      );
      final triggerTop = triggerBox.localToGlobal(Offset.zero).dy;

      // Tap to open
      await tester.tap(find.text('1 Court'));
      await tester.pumpAndSettle();

      // Menu opens above because spaceBelow is only ~24px
      final menuFinder = find.byKey(const ValueKey('matcha_dropdown_menu'));
      expect(menuFinder, findsOneWidget);
      final menuRenderBox = tester.renderObject<RenderBox>(menuFinder);
      final menuBottom = menuRenderBox.localToGlobal(Offset(0, menuRenderBox.size.height)).dy;

      // Menu bottom is 6px above trigger top
      expect((triggerTop - menuBottom - 6.0).abs(), lessThanOrEqualTo(1.0));
    });
  });
}
