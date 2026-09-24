<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];
$action = api_action();

function assert_can_call(int $uid, int $peerId): array
{
    if ($peerId <= 0 || $peerId === $uid) {
        json_error(__('call.invalid_callee'));
    }
    $peer = get_user_by_id($peerId);
    if (!$peer || !(int) $peer['is_active']) {
        json_error(__('msg.user_disabled'));
    }
    if (is_blocked_either($uid, $peerId)) {
        json_error(__('call.unable'));
    }
    if (!users_are_friends($uid, $peerId)) {
        json_error(__('call.friends_only'));
    }
    return $peer;
}

function call_payload(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'caller_id' => (int) $row['caller_id'],
        'callee_id' => (int) $row['callee_id'],
        'call_type' => $row['call_type'],
        'status' => $row['status'],
        'started_at' => $row['started_at'],
        'answered_at' => $row['answered_at'],
        'ended_at' => $row['ended_at'],
        'end_reason' => $row['end_reason'],
    ];
}

switch ($action) {
    case 'start':
        require_csrf();
        require_rate_limit('call', RATE_CALL, $uid);
        $peerId = int_input('user_id');
        $callType = str_input('call_type', 'voice');
        if (!in_array($callType, ['voice', 'video'], true)) {
            json_error(__('call.invalid_type'));
        }
        $peer = assert_can_call($uid, $peerId);

        // End any stale ringing calls for this user (keep short — leftover
        // ringing from a failed phone→PC attempt blocks the next dial).
        db()->prepare(
            "UPDATE voice_call_sessions SET status = 'missed', ended_at = NOW(), end_reason = 'stale'
             WHERE status = 'ringing' AND (caller_id = ? OR callee_id = ?)
               AND started_at < DATE_SUB(NOW(), INTERVAL 45 SECOND)"
        )->execute([$uid, $uid]);

        $active = db()->prepare(
            "SELECT id FROM voice_call_sessions
             WHERE status IN ('ringing','accepted') AND (caller_id = ? OR callee_id = ? OR caller_id = ? OR callee_id = ?)
             LIMIT 1"
        );
        $active->execute([$uid, $uid, $peerId, $peerId]);
        if ($active->fetch()) {
            json_error(__('call.already'));
        }

        db()->prepare(
            'INSERT INTO voice_call_sessions (caller_id, callee_id, call_type, status) VALUES (?, ?, ?, ?)'
        )->execute([$uid, $peerId, $callType, 'ringing']);
        $callId = (int) db()->lastInsertId();

        create_notification(
            $peerId,
            'incoming_call',
            ['name' => $user['username'], 'call_type' => $callType],
            'chat.php?user=' . $uid,
            $callId
        );

        json_success(__('call.started'), [
            'call' => call_payload([
                'id' => $callId,
                'caller_id' => $uid,
                'callee_id' => $peerId,
                'call_type' => $callType,
                'status' => 'ringing',
                'started_at' => date('Y-m-d H:i:s'),
                'answered_at' => null,
                'ended_at' => null,
                'end_reason' => null,
            ]),
            'peer' => public_user($peer),
        ]);

    case 'signal':
        require_csrf();
        $callId = int_input('call_id');
        $type = str_input('signal_type');
        $payload = input('payload');
        if (!in_array($type, ['offer', 'answer', 'ice', 'hangup'], true)) {
            json_error(__('call.invalid_signal'));
        }
        if ($payload === null || $payload === '') {
            json_error(__('call.missing_payload'));
        }
        if (!is_string($payload)) {
            $payload = json_encode($payload);
        }
        $stmt = db()->prepare('SELECT * FROM voice_call_sessions WHERE id = ?');
        $stmt->execute([$callId]);
        $call = $stmt->fetch();
        if (!$call) {
            json_error(__('call.not_found'));
        }
        if ((int) $call['caller_id'] !== $uid && (int) $call['callee_id'] !== $uid) {
            json_error(__('common.not_allowed'), 403);
        }
        if (!in_array($call['status'], ['ringing', 'accepted'], true) && $type !== 'hangup') {
            json_error(__('call.not_active'));
        }
        db()->prepare(
            'INSERT INTO voice_call_signals (call_id, sender_id, signal_type, payload) VALUES (?, ?, ?, ?)'
        )->execute([$callId, $uid, $type, $payload]);
        json_success(__('call.signal_stored'));

    case 'poll_signals':
        $callId = int_input('call_id');
        $afterId = int_input('after_id');
        $stmt = db()->prepare('SELECT * FROM voice_call_sessions WHERE id = ?');
        $stmt->execute([$callId]);
        $call = $stmt->fetch();
        if (!$call || ((int) $call['caller_id'] !== $uid && (int) $call['callee_id'] !== $uid)) {
            json_error(__('common.not_allowed'), 403);
        }
        // Cursor-based delivery (do NOT consume/delete) — critical when mobile
        // callers miss a poll tick or setRemoteDescription fails once.
        $sig = db()->prepare(
            'SELECT id, sender_id, signal_type, payload, created_at
             FROM voice_call_signals
             WHERE call_id = ? AND sender_id <> ? AND id > ?
             ORDER BY id ASC LIMIT 80'
        );
        $sig->execute([$callId, $uid, $afterId]);
        $signals = $sig->fetchAll();
        $out = [];
        $maxId = $afterId;
        foreach ($signals as $s) {
            $decoded = json_decode((string) $s['payload'], true);
            $sid = (int) $s['id'];
            if ($sid > $maxId) {
                $maxId = $sid;
            }
            $out[] = [
                'id' => $sid,
                'sender_id' => (int) $s['sender_id'],
                'signal_type' => $s['signal_type'],
                'payload' => $decoded ?? $s['payload'],
                'created_at' => $s['created_at'],
            ];
        }
        json_success(__('common.ok'), [
            'call' => call_payload($call),
            'signals' => $out,
            'last_id' => $maxId,
        ]);

    case 'incoming':
        // Do not auto-miss quickly — callee ringtone should keep going until accept/reject
        // (or caller cancel). Safety timeout only after a long wait.
        db()->prepare(
            "UPDATE voice_call_sessions SET status = 'missed', ended_at = NOW(), end_reason = 'timeout'
             WHERE status = 'ringing' AND callee_id = ? AND started_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
        )->execute([$uid]);

        // Record chat logs for newly timed-out missed calls
        $missed = db()->prepare(
            "SELECT * FROM voice_call_sessions
             WHERE callee_id = ? AND status = 'missed' AND end_reason = 'timeout'
               AND ended_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)"
        );
        $missed->execute([$uid]);
        while ($m = $missed->fetch()) {
            record_call_chat_message($m);
        }
        $stmt = db()->prepare(
            "SELECT c.*, u.username, u.avatar, u.status_message, u.presence, u.last_seen_at, u.last_activity_at, u.is_active
             FROM voice_call_sessions c
             JOIN users u ON u.id = c.caller_id
             WHERE c.callee_id = ? AND c.status = 'ringing'
             ORDER BY c.id DESC LIMIT 1"
        );
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if (!$row) {
            json_success(__('common.ok'), ['call' => null]);
        }
        json_success(__('common.ok'), [
            'call' => call_payload($row),
            'caller' => public_user([
                'id' => $row['caller_id'],
                'username' => $row['username'],
                'avatar' => $row['avatar'],
                'status_message' => $row['status_message'],
                'presence' => $row['presence'],
                'last_seen_at' => $row['last_seen_at'],
                'last_activity_at' => $row['last_activity_at'],
                'is_active' => $row['is_active'],
            ]),
        ]);

    case 'accept':
        require_csrf();
        $callId = int_input('call_id');
        $stmt = db()->prepare(
            "SELECT * FROM voice_call_sessions WHERE id = ? AND callee_id = ? AND status = 'ringing'"
        );
        $stmt->execute([$callId, $uid]);
        $call = $stmt->fetch();
        if (!$call) {
            json_error(__('call.not_found'));
        }
        assert_can_call($uid, (int) $call['caller_id']);
        db()->prepare(
            "UPDATE voice_call_sessions SET status = 'accepted', answered_at = NOW() WHERE id = ?"
        )->execute([$callId]);
        $call['status'] = 'accepted';
        $call['answered_at'] = date('Y-m-d H:i:s');
        json_success(__('call.accepted'), ['call' => call_payload($call)]);

    case 'reject':
        require_csrf();
        $callId = int_input('call_id');
        $stmt = db()->prepare(
            "UPDATE voice_call_sessions SET status = 'rejected', ended_at = NOW(), end_reason = 'rejected'
             WHERE id = ? AND callee_id = ? AND status = 'ringing'"
        );
        $stmt->execute([$callId, $uid]);
        $row = db()->prepare('SELECT * FROM voice_call_sessions WHERE id = ? AND callee_id = ?');
        $row->execute([$callId, $uid]);
        $call = $row->fetch();
        if (!$call) {
            json_error(__('call.not_found'));
        }
        // MySQL PDO rowCount on UPDATE is unreliable — verify final status
        if ($call['status'] === 'ringing') {
            db()->prepare(
                "UPDATE voice_call_sessions SET status = 'rejected', ended_at = NOW(), end_reason = 'rejected'
                 WHERE id = ? AND callee_id = ? AND status = 'ringing'"
            )->execute([$callId, $uid]);
            $row->execute([$callId, $uid]);
            $call = $row->fetch();
        }
        if (!$call || $call['status'] === 'ringing') {
            json_error(__('call.not_found'));
        }
        if ($call['status'] === 'rejected') {
            record_call_chat_message($call);
        }
        json_success(__('call.rejected'));

    case 'cancel':
        require_csrf();
        $callId = int_input('call_id');
        $stmt = db()->prepare(
            "UPDATE voice_call_sessions SET status = 'cancelled', ended_at = NOW(), end_reason = 'cancelled'
             WHERE id = ? AND caller_id = ? AND status = 'ringing'"
        );
        $stmt->execute([$callId, $uid]);
        $row = db()->prepare('SELECT * FROM voice_call_sessions WHERE id = ? AND caller_id = ?');
        $row->execute([$callId, $uid]);
        $call = $row->fetch();
        if (!$call) {
            json_error(__('call.not_found'));
        }
        if ($call['status'] === 'ringing') {
            db()->prepare(
                "UPDATE voice_call_sessions SET status = 'cancelled', ended_at = NOW(), end_reason = 'cancelled'
                 WHERE id = ? AND caller_id = ? AND status = 'ringing'"
            )->execute([$callId, $uid]);
            $row->execute([$callId, $uid]);
            $call = $row->fetch();
        }
        if (!$call || $call['status'] === 'ringing') {
            json_error(__('call.not_found'));
        }
        if ($call['status'] === 'cancelled') {
            record_call_chat_message($call);
        }
        json_success(__('call.cancelled'));

    case 'end':
        require_csrf();
        $callId = int_input('call_id');
        $reason = str_input('reason', 'ended');
        $stmt = db()->prepare('SELECT * FROM voice_call_sessions WHERE id = ?');
        $stmt->execute([$callId]);
        $call = $stmt->fetch();
        if (!$call || ((int) $call['caller_id'] !== $uid && (int) $call['callee_id'] !== $uid)) {
            json_error(__('common.not_allowed'), 403);
        }
        if (in_array($call['status'], ['ended', 'rejected', 'missed', 'cancelled'], true)) {
            record_call_chat_message($call);
            json_success(__('call.already_ended'), ['call' => call_payload($call)]);
        }
        // If still ringing when "end" is pressed, treat as cancel/missed
        $newStatus = $call['status'] === 'ringing' ? 'cancelled' : 'ended';
        if ($call['status'] === 'ringing' && (int) $call['callee_id'] === $uid) {
            $newStatus = 'missed';
        }
        db()->prepare(
            "UPDATE voice_call_sessions SET status = ?, ended_at = NOW(), end_reason = ? WHERE id = ?"
        )->execute([$newStatus, $reason, $callId]);
        db()->prepare(
            'INSERT INTO voice_call_signals (call_id, sender_id, signal_type, payload) VALUES (?, ?, ?, ?)'
        )->execute([$callId, $uid, 'hangup', json_encode(['reason' => $reason])]);
        $call['status'] = $newStatus;
        $call['ended_at'] = date('Y-m-d H:i:s');
        record_call_chat_message($call);
        json_success(__('call.ended'), ['call' => call_payload($call)]);

    case 'status':
        $callId = int_input('call_id');
        $stmt = db()->prepare('SELECT * FROM voice_call_sessions WHERE id = ?');
        $stmt->execute([$callId]);
        $call = $stmt->fetch();
        if (!$call || ((int) $call['caller_id'] !== $uid && (int) $call['callee_id'] !== $uid)) {
            json_error(__('common.not_allowed'), 403);
        }
        json_success(__('common.ok'), ['call' => call_payload($call)]);

    default:
        json_error(__('common.unknown_action'), 404);
}
