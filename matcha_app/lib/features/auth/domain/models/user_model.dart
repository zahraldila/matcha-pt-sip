class UserModel {
  final int userId;
  final String nama;
  final String email;
  final String? password;
  final String role; // 'Host' atau 'Personal User'
  final String statusUser;
  final DateTime? createAt;
  final DateTime? updateAt;

  const UserModel({
    required this.userId,
    required this.nama,
    required this.email,
    this.password,
    required this.role,
    this.statusUser = 'active',
    this.createAt,
    this.updateAt,
  });

  bool get isHost => role.toLowerCase() == 'host';
  bool get isPersonalUser =>
      role.toLowerCase() == 'personal user' || role.toLowerCase() == 'pu';

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      userId: json['user_id'] is int
          ? json['user_id'] as int
          : int.parse(json['user_id'].toString()),
      nama: json['nama'] as String? ?? '',
      email: json['email'] as String? ?? '',
      password: json['password'] as String?,
      role: json['role'] as String? ?? 'Personal User',
      statusUser: json['status_user'] as String? ?? 'active',
      createAt: json['create_at'] != null
          ? DateTime.tryParse(json['create_at'].toString())
          : null,
      updateAt: json['update_at'] != null
          ? DateTime.tryParse(json['update_at'].toString())
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'user_id': userId,
      'nama': nama,
      'email': email,
      if (password != null) 'password': password,
      'role': role,
      'status_user': statusUser,
      if (createAt != null) 'create_at': createAt!.toIso8601String(),
      if (updateAt != null) 'update_at': updateAt!.toIso8601String(),
    };
  }
}
