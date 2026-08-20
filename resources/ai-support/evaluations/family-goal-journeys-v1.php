<?php

$cases = [];
$add = static function (string $id, string $expected, string $message) use (&$cases): void {
    $cases[] = compact('id', 'expected', 'message') + ['care_context' => $expected !== 'unrelated'];
};

foreach ([
    'I need one visit next Tuesday.',
    'This is only for September 12.',
    'My mother needs help once after an appointment.',
    'I need a caregiver for one afternoon.',
    'Please arrange a single visit.',
    'Someone should come tomorrow for two hours.',
    'It is a one-off need.',
    'Just this Sunday morning.',
    'One evening of companionship would help.',
    'I need care on August 28.',
    'This is not every week, only one day.',
    'A caregiver for one specific occasion.',
] as $index => $message) {
    $add('B10-CARE-ONE-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'one_time', $message);
}

foreach ([
    'We need help every Monday and Wednesday.',
    'This should repeat every week.',
    'My father needs regular care on Tuesdays.',
    'We need ongoing weekly meal preparation.',
    'Twice a week would work.',
    'The caregiver should come each Friday.',
    'Set up weekly visits on Saturday and Sunday.',
    'I need the same help three days a week.',
    'Regular visits every weekday.',
    'This will repeat Mondays for a few months.',
    'Every Thursday morning, starting next month.',
    'We need weekly care until I stop it.',
] as $index => $message) {
    $add('B10-CARE-REG-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'recurring', $message);
}

foreach ([
    'I need someone this Tuesday and then next Saturday.',
    'Care is needed on August 22, September 3, and September 19.',
    'Please arrange visits tomorrow and again in two weeks, but not weekly.',
    'We need three separate dates: Monday, Thursday, and the following Sunday.',
    'Two one-off visits on September 2 and September 18.',
    'Help on this Friday and next Wednesday only.',
] as $index => $message) {
    $add('B10-CARE-IRR-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'irregular_dates', $message);
}

foreach ([
    'My mother needs some help.',
    'I need care for a while.',
    'Can someone come sometimes?',
    'We may need help often.',
    'Morning help would be useful.',
    'I think we need overnight care.',
    'Can a caregiver help my father?',
    'We need some companionship at home.',
] as $index => $message) {
    $add('B10-CARE-AMB-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'clarify', $message);
}

foreach ([
    'We need 24/7 care.',
    'Someone must be there round the clock.',
    'Care is needed all day and all night.',
    'We need continuous day and night coverage.',
] as $index => $message) {
    $add('B10-CARE-24H-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'human_24_7', $message);
}

foreach ([
    'Why did my payment fail?',
    'Help me change my password.',
    'Open my messages.',
    'Where can I review submitted hours?',
    'I want to invite a family member.',
    'Show me my receipts.',
] as $index => $message) {
    $add('B10-CARE-NO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'unrelated', $message);
}

return [
    'version' => 'family-goal-journey-evals-v1',
    'frozen_on' => '2026-08-20',
    'cases' => $cases,
];
