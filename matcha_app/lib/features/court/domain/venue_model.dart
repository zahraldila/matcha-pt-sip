import 'court_model.dart';

class VenueModel {
  final int venueId;
  final int? ownerUserId;
  final String namaVenue;
  final String? alamat;
  final String? kota;
  final String? foto;
  final String? fasilitas;
  final String? catatan;
  final String? jamOperasional;
  final String? hariBuka;
  final String? noWhatsapp;
  final String? namaPic;
  final List<CourtModel> courts;

  VenueModel({
    required this.venueId,
    this.ownerUserId,
    required this.namaVenue,
    this.alamat,
    this.kota,
    this.foto,
    this.fasilitas,
    this.catatan,
    this.jamOperasional,
    this.hariBuka,
    this.noWhatsapp,
    this.namaPic,
    this.courts = const [],
  });

  factory VenueModel.fromMap(Map<String, dynamic> map) {
    List<CourtModel> courtsList = [];
    if (map['tb_court'] != null && map['tb_court'] is List) {
      courtsList = (map['tb_court'] as List)
          .map((c) => CourtModel.fromMap(Map<String, dynamic>.from(c)))
          .toList();
    }

    return VenueModel(
      venueId: map['venue_id'] as int,
      ownerUserId: map['owner_user_id'] as int?,
      namaVenue: (map['nama_venue'] as String?) ?? 'Venue Lapangan',
      alamat: map['alamat'] as String?,
      kota: map['kota'] as String?,
      foto: map['foto'] as String?,
      fasilitas: map['fasilitas'] as String?,
      catatan: map['catatan'] as String?,
      jamOperasional: map['jam_operasional'] as String?,
      hariBuka: map['hari_buka'] as String?,
      noWhatsapp: map['no_whatsapp'] as String?,
      namaPic: map['nama_pic'] as String?,
      courts: courtsList,
    );
  }

  int get courtCount => courts.isNotEmpty ? courts.length : 1;

  List<String> get rawPhotoList {
    if (foto == null || foto!.trim().isEmpty) return [];
    return foto!.split(',').map((p) => p.trim()).where((p) => p.isNotEmpty).toList();
  }

  List<String> get photoList {
    if (foto == null || foto!.trim().isEmpty) {
      return ['https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=800&q=80'];
    }
    final raw = foto!.split(',').map((p) => p.trim()).where((p) => p.isNotEmpty).toList();
    if (raw.isEmpty) {
      return ['https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=800&q=80'];
    }
    return raw.map((p) {
      if (p.startsWith('http://') || p.startsWith('https://')) {
        return p;
      }
      final clean = p.startsWith('/') ? p.substring(1) : p;
      return 'http://demo.uteam.id:7000/$clean';
    }).toList();
  }

  String get mainPhoto => photoList.first;

  String get sportName {
    final sports = courts.map((c) => c.sportId == 2 ? 'Tennis' : 'Padel').toSet();
    if (sports.contains('Tennis') && sports.contains('Padel')) {
      return 'Padel & Tennis';
    } else if (sports.contains('Tennis')) {
      return 'Tennis';
    }
    return 'Padel';
  }

  List<String> get facilitiesList {
    if (fasilitas == null || fasilitas!.trim().isEmpty) {
      return ['Lampu Malam (LED)', 'Shower & Toilet', 'Kantin / Cafe'];
    }
    return fasilitas!
        .split(',')
        .map((e) => e.trim())
        .where((e) => e.isNotEmpty)
        .toList();
  }
}
