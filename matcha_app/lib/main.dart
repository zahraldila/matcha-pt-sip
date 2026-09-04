import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'core/config/supabase_config.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Inisialisasi Supabase
  await Supabase.initialize(
    url: SupabaseConfig.url,
    // ignore: deprecated_member_use
    anonKey: SupabaseConfig.anonKey,
  );

  runApp(const MatchaApp());
}

final supabase = Supabase.instance.client;

class MatchaApp extends StatelessWidget {
  const MatchaApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Matcha - Match Arena',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        brightness: Brightness.dark,
        scaffoldBackgroundColor: const Color(0xFF050608),
        colorScheme: const ColorScheme.dark(
          primary: Color(0xFFA8E63A),
          surface: Color(0xFF111318),
        ),
      ),
      home: const Scaffold(
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.sports_tennis, size: 64, color: Color(0xFFA8E63A)),
              SizedBox(height: 16),
              Text(
                'MATCHA',
                style: TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.bold,
                  letterSpacing: 2,
                  color: Colors.white,
                ),
              ),
              SizedBox(height: 8),
              Text(
                'MATCH ARENA',
                style: TextStyle(
                  fontSize: 14,
                  letterSpacing: 4,
                  color: Color(0xFFA9ADB5),
                ),
              ),
              SizedBox(height: 24),
              Text(
                'Supabase Connected Successfully! 🚀',
                style: TextStyle(color: Color(0xFFA8E63A), fontSize: 13),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
