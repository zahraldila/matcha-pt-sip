import 'package:flutter/material.dart';
import 'core/theme/app_theme.dart';
import 'core/theme/theme_controller.dart';
import 'features/splash/presentation/splash_page.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const MatchaApp());
}

class MatchaApp extends StatefulWidget {
  const MatchaApp({super.key});

  @override
  State<MatchaApp> createState() => _MatchaAppState();
}

class _MatchaAppState extends State<MatchaApp> {
  final ThemeController _themeController = ThemeController();

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: _themeController,
      builder: (context, _) {
        return MaterialApp(
          title: 'Matcha - Tennis & Padel Community Arena',
          debugShowCheckedModeBanner: false,
          theme: AppTheme.lightTheme,
          darkTheme: AppTheme.darkTheme,
          themeMode: _themeController.themeMode,
          home: const SplashPage(),
        );
      },
    );
  }
}
