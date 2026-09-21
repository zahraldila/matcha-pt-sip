class CourtModel {
  final int courtId;
  final int? venueId;
  final int? sportId;
  final String namaCourt;
  final String? lokasi;
  final String? statusKetersediaan;
  final String? statusAktif;
  final String? deskripsi;
  final String? imageUrl;
  final String? tipeCourt;
  final double? hargaPerJam;
  final DateTime? createAt;
  final DateTime? updateAt;

  CourtModel({
    required this.courtId,
    this.venueId,
    this.sportId,
    required this.namaCourt,
    this.lokasi,
    this.statusKetersediaan,
    this.statusAktif,
    this.deskripsi,
    this.imageUrl,
    this.tipeCourt,
    this.hargaPerJam,
    this.createAt,
    this.updateAt,
  });

  factory CourtModel.fromMap(Map<String, dynamic> map) {
    return CourtModel(
      courtId: map['court_id'] as int,
      venueId: map['venue_id'] as int?,
      sportId: map['sport_id'] as int?,
      namaCourt: (map['nama_court'] as String?) ?? 'Court',
      lokasi: map['lokasi'] as String?,
      statusKetersediaan: map['status_ketersediaan'] as String?,
      statusAktif: map['status_aktif'] as String?,
      deskripsi: map['deskripsi'] as String?,
      imageUrl: map['image_url'] as String?,
      tipeCourt: map['tipe_court'] as String?,
      hargaPerJam: (map['harga_per_jam'] is num)
          ? (map['harga_per_jam'] as num).toDouble()
          : null,
      createAt: map['created_at'] != null
          ? DateTime.tryParse(map['created_at'].toString())
          : null,
      updateAt: map['updated_at'] != null
          ? DateTime.tryParse(map['updated_at'].toString())
          : null,
    );
  }
}