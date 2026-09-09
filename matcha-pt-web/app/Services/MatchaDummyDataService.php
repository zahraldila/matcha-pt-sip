<?php

namespace App\Services;

class MatchaDummyDataService
{
    public static function getVenues()
    {
        return [
            [
                'id' => 1,
                'name' => 'Gelora Sports Center',
                'sport' => 'Tennis',
                'address' => 'Jl. Gelora Pemuda No. 12, Jakarta Pusat',
                'city' => 'Jakarta Pusat',
                'pic_name' => 'Bambang Sudirman',
                'pic_phone' => '0812-3456-7890',
                'operating_hours' => '06:00 - 22:00',
                'unavailability_note' => 'Outdoor Court tidak disewakan pukul 10:00 - 13:00 (Panas terik)',
                'image' => 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=800&q=80',
                'facilities' => ['WC / Toilet Bersih', 'Kantin Sehat', 'Ruang Ganti & Shower', 'Tempat Parkir Luas', 'Loker Penyimpanan', 'Pro Shop Tenis'],
                'description' => 'Pusat pelatihan dan lapangan tenis bertaraf nasional dengan lantai hard-court berkualitas tinggi.',
                'courts' => [
                    ['id' => 101, 'name' => 'Court 1 (Center Hard Court)', 'type' => 'Outdoor', 'status' => 'Available'],
                    ['id' => 102, 'name' => 'Court 2 (Regular Hard Court)', 'type' => 'Outdoor', 'status' => 'Available'],
                    ['id' => 103, 'name' => 'Court 3 (Covered / Indoor)', 'type' => 'Indoor', 'status' => 'Booked'],
                    ['id' => 104, 'name' => 'Court 4 (Clay Court)', 'type' => 'Outdoor', 'status' => 'Available'],
                ],
            ],
            [
                'id' => 2,
                'name' => 'Bonang Padel Arena & Club',
                'sport' => 'Padel',
                'address' => 'Jl. Bonang Raya No. 45, Jakarta Selatan',
                'city' => 'Jakarta Selatan',
                'pic_name' => 'Ferry Gunawan',
                'pic_phone' => '0811-9876-5432',
                'operating_hours' => '07:00 - 23:00',
                'unavailability_note' => 'Semua court ber-AC & Semi-Indoor, tersedia sepanjang hari',
                'image' => 'https://images.unsplash.com/photo-1554068865-24cecd4e34b8?auto=format&fit=crop&w=800&q=80',
                'facilities' => ['WC & Shower Air Hangat', 'Cafe Matcha Bar', 'Ruang Ganti VIP', 'Free Wi-Fi', 'Rental Raket & Bola Padel', 'Parkir Mobil & Motor'],
                'description' => 'Arena padel modern dengan pemandangan panoramic glass court standar World Padel Tour.',
                'courts' => [
                    ['id' => 201, 'name' => 'Court 1 (Red Panoramic Court)', 'type' => 'Semi-Indoor', 'status' => 'Available'],
                    ['id' => 202, 'name' => 'Court 2 (Blue Panoramic Court)', 'type' => 'Semi-Indoor', 'status' => 'Available'],
                    ['id' => 203, 'name' => 'Court 3 (Black Stadium Court)', 'type' => 'Indoor', 'status' => 'Available'],
                ],
            ],
            [
                'id' => 3,
                'name' => 'JTK Padel & Tennis Hub',
                'sport' => 'Padel',
                'address' => 'Kawasan Olahraga Terpadu JTK, Bandung',
                'city' => 'Bandung',
                'pic_name' => 'Andi Pratama',
                'pic_phone' => '0813-2233-4455',
                'operating_hours' => '06:00 - 21:00',
                'unavailability_note' => 'Court outdoor ditutup saat hujan lebat',
                'image' => 'https://images.unsplash.com/photo-1622279457486-62dcc4a431d6?auto=format&fit=crop&w=800&q=80',
                'facilities' => ['WC Bersih', 'Kantin Komunitas', 'Ruang Istirahat', 'Parkir Luas', 'Lampu Lapangan LED'],
                'description' => 'Basecamp utama komunitas JTK Padel dengan suasana sejuk dan fasilitas lengkap.',
                'courts' => [
                    ['id' => 301, 'name' => 'Court A (Padel Glass)', 'type' => 'Outdoor', 'status' => 'Available'],
                    ['id' => 302, 'name' => 'Court B (Tennis Hard)', 'type' => 'Outdoor', 'status' => 'Booked'],
                ],
            ],
        ];
    }

    public static function getGames()
    {
        return [
            [
                'id' => 1,
                'title' => 'Mabar Padel JTK Bonang (6 Players Round-Robin)',
                'sport' => 'Padel',
                'venue_id' => 2,
                'venue_name' => 'Bonang Padel Arena & Club',
                'court_name' => 'Court 1 (Red Panoramic Court)',
                'date' => '2026-09-12',
                'time' => '18:30',
                'duration' => '2 Jam',
                'quota' => 6,
                'joined_count' => 6,
                'status' => 'Ready for Drawing', // Open, Full / Ready for Drawing, In Progress, Finished
                'level_recommendation' => 'Beginner - Intermediate',
                'match_format' => 'Double',
                'scoring_system' => 'Americano 32 Points',
                'host' => [
                    'name' => 'Billy Santoso',
                    'role' => 'Host & Community Admin',
                    'level' => 'Intermediate',
                    'phone' => '0812-9988-7766',
                    'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
                ],
                'participants' => [
                    ['name' => 'Billy Santoso (Host)', 'gender' => 'Male', 'age' => 28, 'level' => 'Intermediate', 'is_member' => true, 'phone' => '0812-9988-7766', 'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80'],
                    ['name' => 'Gisel Anastasia', 'gender' => 'Female', 'age' => 25, 'level' => 'Beginner', 'is_member' => true, 'phone' => '0813-1122-3344', 'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=200&q=80'],
                    ['name' => 'Andi Wijaya', 'gender' => 'Male', 'age' => 30, 'level' => 'Intermediate', 'is_member' => true, 'phone' => '0812-4455-6677', 'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80'],
                    ['name' => 'Fahri Dhani', 'gender' => 'Male', 'age' => 27, 'level' => 'Advanced', 'is_member' => true, 'phone' => '0817-8899-0011', 'avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=200&q=80'],
                    ['name' => 'Davina Putri', 'gender' => 'Female', 'age' => 24, 'level' => 'Newbie', 'is_member' => false, 'phone' => '0819-3344-5566', 'avatar' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=200&q=80'],
                    ['name' => 'Marame Nagoan', 'gender' => 'Male', 'age' => 32, 'level' => 'Beginner', 'is_member' => true, 'phone' => '0818-5566-7788', 'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=200&q=80'],
                ],
                'drawing' => [
                    'round_1' => [
                        'team_a' => ['Billy Santoso', 'Gisel Anastasia'],
                        'team_b' => ['Fahri Dhani', 'Davina Putri'],
                        'resting' => ['Andi Wijaya', 'Marame Nagoan'],
                    ],
                    'round_2' => [
                        'team_a' => ['Billy Santoso', 'Andi Wijaya'],
                        'team_b' => ['Marame Nagoan', 'Gisel Anastasia'],
                        'resting' => ['Fahri Dhani', 'Davina Putri'],
                    ],
                ],
            ],
            [
                'id' => 2,
                'title' => 'Sunday Morning Tennis Gelora (4 Players Single & Double)',
                'sport' => 'Tennis',
                'venue_id' => 1,
                'venue_name' => 'Gelora Sports Center',
                'court_name' => 'Court 1 (Center Hard Court)',
                'date' => '2026-09-13',
                'time' => '07:00',
                'duration' => '2 Jam',
                'quota' => 4,
                'joined_count' => 4,
                'status' => 'In Progress (Scoring)',
                'level_recommendation' => 'Intermediate - Advanced',
                'match_format' => 'Single / Double',
                'scoring_system' => 'Tennis System (15, 30, 40, Deuce, Adv, Game)',
                'host' => [
                    'name' => 'Steven Kurniawan',
                    'role' => 'Host Game',
                    'level' => 'Advanced',
                    'phone' => '0811-3344-9900',
                    'avatar' => 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?auto=format&fit=crop&w=200&q=80',
                ],
                'participants' => [
                    ['name' => 'Steven Kurniawan (Host)', 'gender' => 'Male', 'age' => 29, 'level' => 'Advanced', 'is_member' => true, 'phone' => '0811-3344-9900', 'avatar' => 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?auto=format&fit=crop&w=200&q=80'],
                    ['name' => 'Andi Wijaya', 'gender' => 'Male', 'age' => 30, 'level' => 'Intermediate', 'is_member' => true, 'phone' => '0812-4455-6677', 'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80'],
                    ['name' => 'Firman Utina (Guest)', 'gender' => 'Male', 'age' => 31, 'level' => 'Intermediate', 'is_member' => false, 'phone' => '0815-6677-8899', 'avatar' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=200&q=80'],
                    ['name' => 'Budi Pratama', 'gender' => 'Male', 'age' => 28, 'level' => 'Intermediate', 'is_member' => true, 'phone' => '0813-2233-4455', 'avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=200&q=80'],
                ],
                'drawing' => [
                    'round_1' => [
                        'team_a' => ['Steven Kurniawan', 'Andi Wijaya'],
                        'team_b' => ['Firman Utina', 'Budi Pratama'],
                        'resting' => [],
                    ],
                ],
            ],
            [
                'id' => 3,
                'title' => 'Matcha Evening Padel Battle (8 Players Americano)',
                'sport' => 'Padel',
                'venue_id' => 3,
                'venue_name' => 'JTK Padel & Tennis Hub',
                'court_name' => 'Court A (Padel Glass)',
                'date' => '2026-09-14',
                'time' => '19:00',
                'duration' => '4 Jam',
                'quota' => 8,
                'joined_count' => 8,
                'status' => 'In Progress (Scoring)',
                'level_recommendation' => 'All Level Welcome',
                'match_format' => 'Double Rotation',
                'scoring_system' => 'Points (1, 2, 3... / 24 Points per game)',
                'host' => [
                    'name' => 'Billy Santoso',
                    'role' => 'Host & Community Admin',
                    'level' => 'Intermediate',
                    'phone' => '0812-9988-7766',
                    'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
                ],
                'participants' => [
                    ['name' => 'Billy Santoso (Host)', 'gender' => 'Male', 'age' => 28, 'level' => 'Intermediate', 'is_member' => true, 'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80'],
                    ['name' => 'Gisel Anastasia', 'gender' => 'Female', 'age' => 25, 'level' => 'Beginner', 'is_member' => true, 'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=200&q=80'],
                    ['name' => 'Andi Wijaya', 'gender' => 'Male', 'age' => 30, 'level' => 'Intermediate', 'is_member' => true, 'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80'],
                    ['name' => 'Fahri Dhani', 'gender' => 'Male', 'age' => 27, 'level' => 'Advanced', 'is_member' => true, 'avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=200&q=80'],
                    ['name' => 'Davina Putri', 'gender' => 'Female', 'age' => 24, 'level' => 'Newbie', 'is_member' => false, 'avatar' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=200&q=80'],
                    ['name' => 'Marame Nagoan', 'gender' => 'Male', 'age' => 32, 'level' => 'Beginner', 'is_member' => true, 'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=200&q=80'],
                    ['name' => 'Steven Kurniawan', 'gender' => 'Male', 'age' => 29, 'level' => 'Advanced', 'is_member' => true, 'avatar' => 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?auto=format&fit=crop&w=200&q=80'],
                    ['name' => 'Reza Rahardian (Guest)', 'gender' => 'Male', 'age' => 26, 'level' => 'Beginner', 'is_member' => false, 'avatar' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=200&q=80'],
                ],
                'drawing' => [
                    'round_1' => [
                        'team_a' => ['Billy Santoso', 'Fahri Dhani'],
                        'team_b' => ['Steven Kurniawan', 'Andi Wijaya'],
                        'resting' => ['Gisel Anastasia', 'Davina Putri', 'Marame Nagoan', 'Reza Rahardian'],
                    ],
                ],
            ],
        ];
    }

    public static function getPlayerRecap($playerName = 'Billy Santoso')
    {
        return [
            'player' => [
                'name' => 'Billy Santoso',
                'username' => '@billy_matcha',
                'level' => 'Intermediate',
                'community' => 'JTK Padel Bandung',
                'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
                'total_matches' => 10,
                'wins' => 8,
                'losses' => 2,
                'win_rate' => '80%',
                'total_hours' => '18 Jam',
                'streak' => '4 Win Streak 🔥',
            ],
            'recent_matches' => [
                [
                    'match_date' => 'Hari ini, 08 Sep 2026',
                    'venue' => 'Bonang Padel Arena',
                    'sport' => 'Padel Double',
                    'result' => 'WIN',
                    'score' => '32 - 18',
                    'partner' => 'Fahri Dhani',
                    'opponents' => ['Andi Wijaya', 'Marame Nagoan'],
                ],
                [
                    'match_date' => '06 Sep 2026',
                    'venue' => 'Gelora Sports Center',
                    'sport' => 'Tennis Set 1',
                    'result' => 'WIN',
                    'score' => '6 - 3',
                    'partner' => 'Gisel Anastasia',
                    'opponents' => ['Steven Kurniawan', 'Davina Putri'],
                ],
                [
                    'match_date' => '03 Sep 2026',
                    'venue' => 'JTK Padel Hub',
                    'sport' => 'Padel Americano',
                    'result' => 'WIN',
                    'score' => '24 - 16',
                    'partner' => 'Marame Nagoan',
                    'opponents' => ['Gisel Anastasia', 'Davina Putri'],
                ],
                [
                    'match_date' => '30 Agu 2026',
                    'venue' => 'Gelora Sports Center',
                    'sport' => 'Tennis Double',
                    'result' => 'LOSE',
                    'score' => '4 - 6',
                    'partner' => 'Andi Wijaya',
                    'opponents' => ['Fahri Dhani', 'Steven Kurniawan'],
                ],
            ],
            'head_to_head' => [
                ['opponent' => 'Fahri Dhani', 'played' => 5, 'win' => 3, 'lose' => 2],
                ['opponent' => 'Gisel Anastasia', 'played' => 4, 'win' => 4, 'lose' => 0],
                ['opponent' => 'Steven Kurniawan', 'played' => 3, 'win' => 2, 'lose' => 1],
            ],
        ];
    }

    public static function getCommunities()
    {
        return [
            [
                'id' => 1,
                'name' => 'JTK Padel Club Bandung',
                'sport' => 'Padel',
                'members_count' => 42,
                'admin_name' => 'Billy Santoso',
                'description' => 'Komunitas pecinta padel terbesar di Bandung. Rutin mabar setiap Selasa & Jumat malam.',
                'image' => 'https://images.unsplash.com/photo-1554068865-24cecd4e34b8?auto=format&fit=crop&w=400&q=80',
                'status' => 'Active',
            ],
            [
                'id' => 2,
                'name' => 'Gelora Tennis Enthusiasts',
                'sport' => 'Tennis',
                'members_count' => 68,
                'admin_name' => 'Steven Kurniawan',
                'description' => 'Komunitas tenis weekend warrior Jakarta Pusat. Terbuka untuk semua level dari Newbie hingga Pro.',
                'image' => 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=400&q=80',
                'status' => 'Active',
            ],
            [
                'id' => 3,
                'name' => 'South Jakarta Padel Squad',
                'sport' => 'Padel',
                'members_count' => 35,
                'admin_name' => 'Fahri Dhani',
                'description' => 'Mabar santai dan competitive match play di Bonang Padel Arena.',
                'image' => 'https://images.unsplash.com/photo-1622279457486-62dcc4a431d6?auto=format&fit=crop&w=400&q=80',
                'status' => 'Active',
            ],
        ];
    }
}
