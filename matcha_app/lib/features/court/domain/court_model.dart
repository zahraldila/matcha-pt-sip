class CourtModel {
  final int courtId;
  final int? sportId;
  final String namaCourt;
  final String? lokasi;
  final String? statusKetersediaan;
  final String? statusAktif;
  final String? deskripsi;
  final String? imageUrl;
  final DateTime? createAt;
  final DateTime? updateAt;

  CourtModel({
    required this.courtId,
    this.sportId,
    required this.namaCourt,
    this.lokasi,
    this.statusKetersediaan,
    this.statusAktif,
    this.deskripsi,
    this.imageUrl,
    this.createAt,
    this.updateAt,
  });

  factory CourtModel.fromMap(Map<String, dynamic> map) {
    return CourtModel(
      courtId: map['court_id'] as int,
      sportId: map['sport_id'] as int?,
      namaCourt: map['nama_court'] as String,
      lokasi: map['lokasi'] as String?,
      statusKetersediaan: map['status_ketersediaan'] as String?,
      statusAktif: map['status_aktif'] as String?,
      deskripsi: map['deskripsi'] as String?,
      imageUrl: map['image_url'] as String?,
      createAt: map['create_at'] != null
          ? DateTime.tryParse(map['create_at'].toString())
          : null,
      updateAt: map['update_at'] != null
          ? DateTime.tryParse(map['update_at'].toString())
          : null,
    );
  }
}