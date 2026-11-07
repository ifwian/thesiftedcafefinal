<?php
/*
 * AUDIT LOG: one row in the audit_logs table for every important event.
 * It is written by the PHP backend at the moment the action really happens
 * (never by JavaScript), and it is only ever INSERTed. The code never updates or deletes
 * audit rows, and the database triggers in database/thesifted_db.sql refuse it too.
 */

/** The actions we record, grouped for the Owner's filter. */
const AUDIT_ACTIONS = [
    'Sign-ins' => [
        'login'                 => 'Signed in',
        'logout'                => 'Signed out',
        'login_failed'          => 'Failed sign-in',
        'access_denied'         => 'Blocked from owner area',
    ],
    'Reservations' => [
        'reservation_created'   => 'Reservation created',
        'reservation_confirmed' => 'Reservation confirmed',
        'reservation_completed' => 'Reservation completed',
        'reservation_cancelled' => 'Reservation cancelled',
        'reservation_archived'  => 'Reservation archived',
        'reservation_restored'  => 'Reservation restored',
        'reservation_deleted'   => 'Archived reservation deleted',
        'notes_updated'         => 'Staff notes updated',
    ],
    'Payments' => [
        'payment_verified'      => 'Payment verified',
    ],
    'Accounts' => [
        'account_role_changed'  => 'Account role changed',
    ],
];

function audit_action_label(string $action): string
{
    foreach (AUDIT_ACTIONS as $group) {
        if (isset($group[$action])) { return $group[$action]; }
    }
    return $action;
}

/**
 * Adds ONE audit row. $actor is the signed-in user row (or null = system).
 * $f may hold: entity_type, entity_id, ref, old, new, details.
 * An audit problem is logged to storage/logs/app.log but never breaks the visitor's action.
 */
function audit_log(string $action, ?array $actor, array $f = []): void
{
    try {
        db()->prepare('INSERT INTO audit_logs
            (created_at, actor_id, actor_name, actor_role, action, entity_type, entity_id, entity_ref, old_value, new_value, details, ip_address)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                now_text(),
                $actor['id'] ?? null,
                isset($actor['full_name']) ? mb_substr((string) $actor['full_name'], 0, 100) : null,
                $actor['role'] ?? null,
                $action,
                $f['entity_type'] ?? null,
                $f['entity_id'] ?? null,
                $f['ref'] ?? null,
                $f['old'] ?? null,
                $f['new'] ?? null,
                isset($f['details']) ? mb_substr((string) $f['details'], 0, 500) : null,
                mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            ]);
    } catch (Throwable $e) {
        error_log('Audit log write failed: ' . $e->getMessage());
    }
}

/** Shortcut for events about one reservation. */
function audit_reservation(string $action, ?array $actor, int $id, ?string $old = null, ?string $new = null, ?string $details = null): void
{
    audit_log($action, $actor, [
        'entity_type' => 'reservation', 'entity_id' => $id, 'ref' => reference_for($id),
        'old' => $old, 'new' => $new, 'details' => $details,
    ]);
}
