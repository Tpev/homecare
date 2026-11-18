<?php

return [
    'currency' => 'usd',
    'unit_price_cents' => 3000,
    'minimum_quantity' => 25,
    'package_quantities' => [25],
    'plans' => [
        'one_time' => ['name' => 'One-time package', 'quantity' => 25, 'unit_price_cents' => 4000, 'recurring' => false],
        'monthly' => ['name' => 'Monthly subscription', 'quantity' => 25, 'unit_price_cents' => 3000, 'recurring' => true],
        'annual' => ['name' => 'Annual package', 'quantity' => 300, 'unit_price_cents' => 2500, 'recurring' => false],
    ],
];
