<?php

/**
 * App settings. These are the SERVER-side rules: the browser can never change them.
 * Values marked PLACEHOLDER are NOT confirmed by the cafe (see README).
 * Keep the display text in js/config.js in sync if you change a rate here.
 */
return [
    'timezone' => 'Asia/Manila',

    // PLACEHOLDER rates (the cafe has not confirmed them)
    'reservation_fee'          => 0,    // pesos per reservation
    'minimum_spend_per_guest'  => 200,  // pesos
    'downpayment_percent'      => 30,   // % of the total
    'duration_hours'           => 2,    // how long a table is held (placeholder)

    // From the branch manager's interview
    'min_guests'         => 2,
    'max_guests'         => 10,
    'capacity_per_slot'  => 20,         // guests per time slot per branch
    'min_days_ahead'     => 1,          // at least 1 day notice
    'max_days_ahead'     => 60,         // PLACEHOLDER: the cafe left "how far ahead" blank
    'grace_minutes'      => 15,         // table is held this long after the reservation time (staff apply it)
    // Customers may cancel their own pending/confirmed reservation up to this many hours before it starts.
    // Later than that, they must contact the cafe (staff decide). Enforced in includes/reservations.php.
    'cancel_hours_before' => 24,
    'branches' => [
    'calamba' => [
        'name' => 'Calamba',
        'first_hour' => 10,
        'last_hour' => 21,
        'capacity' => 20
    ],
    'cabuyao' => [
        'name' => 'Cabuyao',
        'first_hour' => 9,
        'last_hour' => 20,
        'capacity' => 30
    ],
],
    // Payment proof upload rules
    'require_proof'   => true,
    'max_proof_bytes' => 3 * 1024 * 1024,
    'proof_types'     => [   // allowed MIME type => file extension we store
        'image/png'       => 'png',
        'image/jpeg'      => 'jpg',
        'application/pdf' => 'pdf',
    ],

    'proof_dir' => dirname(__DIR__) . '/storage/proofs',
    'log_dir'   => dirname(__DIR__) . '/storage/logs',
];
