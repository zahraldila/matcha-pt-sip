import 'dart:math';
import '../models/drawing_round_model.dart';
import '../models/match_pairing_model.dart';

class DrawingEngineService {
  final Random _random = Random();

  /// Menghasilkan susunan pertandingan dengan metode AMERICANO (Social & Rotasi Adil)
  DrawingRoundModel generateAmericanoRound({
    required String sessionName,
    required String sportName,
    required List<String> playerNames,
    List<int>? playerIds,
    required List<String> courtNames,
    List<int>? courtIds,
    int roundNumber = 1,
    String format = 'Doubles',
    bool isReshuffle = false,
  }) {
    if (playerNames.isEmpty || courtNames.isEmpty) {
      throw ArgumentError('Daftar pemain dan lapangan tidak boleh kosong');
    }

    final isDoubles = format.toLowerCase() == 'doubles';
    final playersPerCourt = isDoubles ? 4 : 2;
    final totalCapacity = courtNames.length * playersPerCourt;

    // Duplikasi list pemain untuk dirotasi/diacak
    final List<String> workingPlayers = List<String>.from(playerNames);
    final List<int> workingIds = playerIds != null && playerIds.length == playerNames.length
        ? List<int>.from(playerIds)
        : List<int>.generate(playerNames.length, (i) => i + 1);

    if (isReshuffle || roundNumber == 1) {
      // Fisher-Yates Shuffle
      for (int i = workingPlayers.length - 1; i > 0; i--) {
        final j = _random.nextInt(i + 1);
        // Swap names
        final tempName = workingPlayers[i];
        workingPlayers[i] = workingPlayers[j];
        workingPlayers[j] = tempName;
        // Swap IDs
        final tempId = workingIds[i];
        workingIds[i] = workingIds[j];
        workingIds[j] = tempId;
      }
    } else {
      // Algoritma rotasi shift bertahap per ronde agar pemain waiting list masuk
      final shift = (roundNumber - 1) % workingPlayers.length;
      for (int s = 0; s < shift; s++) {
        final firstPlayer = workingPlayers.removeAt(0);
        workingPlayers.add(firstPlayer);
        final firstId = workingIds.removeAt(0);
        workingIds.add(firstId);
      }
    }

    // Pisahkan pemain yang aktif di lapangan dan yang masuk waiting list
    final int activeCount = min(workingPlayers.length, totalCapacity);
    final activePlayers = workingPlayers.sublist(0, activeCount);
    final activeIds = workingIds.sublist(0, activeCount);

    final waitingPlayers = workingPlayers.length > totalCapacity
        ? workingPlayers.sublist(totalCapacity)
        : <String>[];
    final waitingIds = workingIds.length > totalCapacity
        ? workingIds.sublist(totalCapacity)
        : <int>[];

    final List<MatchPairingModel> matches = [];
    int playerIndex = 0;

    for (int c = 0; c < courtNames.length; c++) {
      if (playerIndex + playersPerCourt <= activePlayers.length) {
        final courtName = courtNames[c];
        final courtId = courtIds != null && c < courtIds.length ? courtIds[c] : c + 1;

        if (isDoubles) {
          final sideANames = [activePlayers[playerIndex], activePlayers[playerIndex + 1]];
          final sideAIds = [activeIds[playerIndex], activeIds[playerIndex + 1]];

          final sideBNames = [activePlayers[playerIndex + 2], activePlayers[playerIndex + 3]];
          final sideBIds = [activeIds[playerIndex + 2], activeIds[playerIndex + 3]];

          matches.add(MatchPairingModel(
            courtId: courtId,
            courtName: courtName,
            roundNumber: roundNumber,
            sideA: sideANames,
            sideB: sideBNames,
            sideAPlayerIds: sideAIds,
            sideBPlayerIds: sideBIds,
            statusMatch: 'PLAYING',
          ));

          playerIndex += 4;
        } else {
          // Singles Format
          final sideANames = [activePlayers[playerIndex]];
          final sideAIds = [activeIds[playerIndex]];

          final sideBNames = [activePlayers[playerIndex + 1]];
          final sideBIds = [activeIds[playerIndex + 1]];

          matches.add(MatchPairingModel(
            courtId: courtId,
            courtName: courtName,
            roundNumber: roundNumber,
            sideA: sideANames,
            sideB: sideBNames,
            sideAPlayerIds: sideAIds,
            sideBPlayerIds: sideBIds,
            statusMatch: 'PLAYING',
          ));

          playerIndex += 2;
        }
      } else if (isDoubles && playerIndex + 2 <= activePlayers.length) {
        // Fallback ke 1 vs 1 jika sisa pemain 2 atau 3
        final courtName = courtNames[c];
        final courtId = courtIds != null && c < courtIds.length ? courtIds[c] : c + 1;

        final sideANames = [activePlayers[playerIndex]];
        final sideAIds = [activeIds[playerIndex]];

        final sideBNames = [activePlayers[playerIndex + 1]];
        final sideBIds = [activeIds[playerIndex + 1]];

        matches.add(MatchPairingModel(
          courtId: courtId,
          courtName: courtName,
          roundNumber: roundNumber,
          sideA: sideANames,
          sideB: sideBNames,
          sideAPlayerIds: sideAIds,
          sideBPlayerIds: sideBIds,
          statusMatch: 'PLAYING',
        ));

        playerIndex += 2;
      }
    }

    return DrawingRoundModel(
      sessionName: sessionName,
      sportName: sportName,
      roundNumber: roundNumber,
      drawingMethod: 'Americano',
      format: format,
      matches: matches,
      waitingPlayers: waitingPlayers,
      waitingPlayerIds: waitingIds,
      statusDrawing: 'ACTIVE',
      createdAt: DateTime.now(),
    );
  }

  /// Menghasilkan susunan pertandingan dengan metode MEXICANO (Dynamic Rank-Based)
  DrawingRoundModel generateMexicanoRound({
    required String sessionName,
    required String sportName,
    required List<Map<String, dynamic>> playerScores, // [{'name': 'Aldi', 'id': 1, 'score': 24}, ...]
    required List<String> courtNames,
    List<int>? courtIds,
    int roundNumber = 1,
    String format = 'Doubles',
  }) {
    if (playerScores.isEmpty || courtNames.isEmpty) {
      throw ArgumentError('Daftar pemain dan lapangan tidak boleh kosong');
    }

    final isDoubles = format.toLowerCase() == 'doubles';
    final playersPerCourt = isDoubles ? 4 : 2;
    final totalCapacity = courtNames.length * playersPerCourt;

    // Urutkan pemain berdasarkan skor tertinggi (Rank 1 to N)
    final sortedPlayers = List<Map<String, dynamic>>.from(playerScores)
      ..sort((a, b) => (b['score'] as int).compareTo(a['score'] as int));

    final int activeCount = min(sortedPlayers.length, totalCapacity);
    final activeList = sortedPlayers.sublist(0, activeCount);

    final waitingList = sortedPlayers.length > totalCapacity
        ? sortedPlayers.sublist(totalCapacity).map((p) => p['name'] as String).toList()
        : <String>[];
    final waitingIds = sortedPlayers.length > totalCapacity
        ? sortedPlayers.sublist(totalCapacity).map((p) => p['id'] as int).toList()
        : <int>[];

    final List<MatchPairingModel> matches = [];
    int playerIndex = 0;

    for (int c = 0; c < courtNames.length; c++) {
      if (playerIndex + playersPerCourt <= activeList.length) {
        final courtName = courtNames[c];
        final courtId = courtIds != null && c < courtIds.length ? courtIds[c] : c + 1;

        if (isDoubles) {
          // Mexicano Tier Pairing:
          // Rank 1 & Rank 3 VS Rank 2 & Rank 4
          final p1 = activeList[playerIndex];
          final p2 = activeList[playerIndex + 1];
          final p3 = activeList[playerIndex + 2];
          final p4 = activeList[playerIndex + 3];

          final sideANames = [p1['name'] as String, p3['name'] as String];
          final sideAIds = [p1['id'] as int, p3['id'] as int];

          final sideBNames = [p2['name'] as String, p4['name'] as String];
          final sideBIds = [p2['id'] as int, p4['id'] as int];

          matches.add(MatchPairingModel(
            courtId: courtId,
            courtName: courtName,
            roundNumber: roundNumber,
            sideA: sideANames,
            sideB: sideBNames,
            sideAPlayerIds: sideAIds,
            sideBPlayerIds: sideBIds,
            statusMatch: 'PLAYING',
          ));

          playerIndex += 4;
        } else {
          // Singles Mexicano: Rank 1 vs Rank 2, Rank 3 vs Rank 4
          final p1 = activeList[playerIndex];
          final p2 = activeList[playerIndex + 1];

          matches.add(MatchPairingModel(
            courtId: courtId,
            courtName: courtName,
            roundNumber: roundNumber,
            sideA: [p1['name'] as String],
            sideB: [p2['name'] as String],
            sideAPlayerIds: [p1['id'] as int],
            sideBPlayerIds: [p2['id'] as int],
            statusMatch: 'PLAYING',
          ));

          playerIndex += 2;
        }
      } else if (isDoubles && playerIndex + 2 <= activeList.length) {
        final courtName = courtNames[c];
        final courtId = courtIds != null && c < courtIds.length ? courtIds[c] : c + 1;
        final p1 = activeList[playerIndex];
        final p2 = activeList[playerIndex + 1];

        matches.add(MatchPairingModel(
          courtId: courtId,
          courtName: courtName,
          roundNumber: roundNumber,
          sideA: [p1['name'] as String],
          sideB: [p2['name'] as String],
          sideAPlayerIds: [p1['id'] as int],
          sideBPlayerIds: [p2['id'] as int],
          statusMatch: 'PLAYING',
        ));

        playerIndex += 2;
      }
    }

    return DrawingRoundModel(
      sessionName: sessionName,
      sportName: sportName,
      roundNumber: roundNumber,
      drawingMethod: 'Mexicano',
      format: format,
      matches: matches,
      waitingPlayers: waitingList,
      waitingPlayerIds: waitingIds,
      statusDrawing: 'ACTIVE',
      createdAt: DateTime.now(),
    );
  }
}
