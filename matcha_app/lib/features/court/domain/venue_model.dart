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
}
