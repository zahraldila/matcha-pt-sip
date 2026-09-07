class CommunityModel {
  final int communityId;
  final String namaCommunity;
  final String? deskripsi;
  final String status;
  final String? imageUrl;
  final DateTime? createAt;
  final DateTime? updateAt;

  CommunityModel({
    required this.communityId,
    required this.namaCommunity,
    this.deskripsi,
    required this.status,
    this.imageUrl,
    this.createAt,
    this.updateAt,
  });

  factory CommunityModel.fromMap(Map<String, dynamic> map) {
    return CommunityModel(
      communityId: map['community_id'] as int,
      namaCommunity: map['nama_community'] as String,
      deskripsi: map['deskripsi'] as String?,
      status: map['status'] as String,
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