<?php

namespace App\Http\Controllers;

use App\Services\CareHours\CareHourAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CareHoursMarketingController extends Controller
{
    public function prepaid(Request $request, CareHourAccess $access): View
    {
        return $this->page($request, $access, false);
    }

    public function gifts(Request $request, CareHourAccess $access): View
    {
        return $this->page($request, $access, true);
    }

    private function page(Request $request, CareHourAccess $access, bool $gift): View
    {
        $welcomeEnabled = (bool) config('care_hours.welcome_enabled', false);
        $packages = collect(config('care_hours.packages', []))->map(function (array $package) use ($welcomeEnabled): array {
            $hours = intdiv((int) $package['paid_minutes'], 60);
            $bonusHours = $welcomeEnabled ? intdiv((int) $package['bonus_minutes'], 60) : 0;

            return [
                'hours' => $hours,
                'paid_minutes' => (int) $package['paid_minutes'],
                'bonus_hours' => $bonusHours,
                'amount_cents' => (int) $package['amount_cents'],
                'formatted_price' => '$'.number_format($package['amount_cents'] / 100, 0),
                'total_hours' => $hours + $bonusHours,
            ];
        })->values()->all();
        // Marketing remains readable during the pilot or a sales pause. The CTA
        // uses existing purchase authorization; this page never enrolls a family.
        $canPurchase = $gift ? $access->canBuyGift($request->user())
            : $request->user()?->role === 'family' && $access->canBuy($request->user());
        $purchaseUrl = $canPurchase
            ? route($gift ? 'care-hours.gifts.index' : 'family.care-hours.buy')
            : route('landing.get-care');
        $pageUrl = route($gift ? 'care-gift-cards' : 'prepaid-care-hours');
        $pageTitle = $gift ? 'Care Gift Cards for Loved Ones | LoLo Care' : 'Prepaid Care Hours for Your Family | LoLo Care';
        $pageDescription = $gift
            ? 'Give practical support with a LoLo care gift card. Choose care hours, share a private code, and let your loved one arrange non-medical care when they need it.'
            : 'Plan ahead with prepaid LoLo care hours. One family balance for non-medical care, clear package pricing, no expiry, and a welcome bonus for eligible families.';
        if (! $welcomeEnabled && ! $gift) {
            $pageDescription = 'Plan ahead with prepaid LoLo care hours. One family balance for non-medical care, clear package pricing and no expiry. Explore packages and check availability.';
        }
        $faqs = $this->faqs($gift, $welcomeEnabled);
        $schema = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebPage', '@id' => $pageUrl.'#webpage', 'url' => $pageUrl,
                    'name' => $pageTitle, 'description' => $pageDescription,
                    'inLanguage' => str_replace('_', '-', app()->getLocale()),
                    'isPartOf' => ['@type' => 'WebSite', 'name' => 'LoLo Care', 'url' => route('landing')],
                    'breadcrumb' => ['@id' => $pageUrl.'#breadcrumb'],
                ],
                [
                    '@type' => 'BreadcrumbList', '@id' => $pageUrl.'#breadcrumb',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('landing')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => $gift ? 'Care gift cards' : 'Prepaid care hours', 'item' => $pageUrl],
                    ],
                ],
                [
                    '@type' => 'FAQPage', '@id' => $pageUrl.'#faq',
                    'mainEntity' => array_map(fn (array $faq): array => [
                        '@type' => 'Question', 'name' => $faq['question'],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
                    ], $faqs),
                ],
            ],
        ];

        return view($gift ? 'marketing.care-gift-cards' : 'marketing.prepaid-care-hours', compact(
            'packages', 'purchaseUrl', 'canPurchase', 'pageUrl', 'pageTitle', 'pageDescription', 'faqs', 'schema', 'welcomeEnabled',
        ));
    }

    /** @return list<array{question: string, answer: string}> */
    private function faqs(bool $gift, bool $welcomeEnabled): array
    {
        $bonus = $welcomeEnabled
            ? 'The first eligible 10-, 20- or 40-hour package adds 1, 2 or 4 welcome hours, once per family account. A 5-hour package has no bonus and does not use that eligibility.'
            : 'Welcome bonuses are not currently available. Each package includes the paid care hours shown.';

        if ($gift) {
            return [
                ['question' => 'Does my loved one need a LoLo account?', 'answer' => 'Not when you buy. Share the private gift code with them. They can enter the code, create a free family account and claim the hours for themselves, or sign in to an existing family account.'],
                ['question' => 'How is the gift delivered?', 'answer' => 'After payment is confirmed, the buyer receives a private gift code to share. The first person to claim it chooses the receiving family account. Keep the code private; it can only be claimed once.'],
                ['question' => 'Will my gift include welcome hours?', 'answer' => $bonus.($welcomeEnabled ? ' For gifts, eligibility belongs to the receiving family and the bonus is confirmed when the gift is claimed.' : '')],
                ['question' => 'What can the gift be used for?', 'answer' => 'The hours go into the receiving family account and can fund eligible standard-priced, individual non-medical visits on LoLo. The family chooses the care and schedule, subject to caregiver availability and service area. Custom-priced and shared-care bookings are excluded. Check availability before buying.'],
                ['question' => 'Can several family members contribute?', 'answer' => 'Yes. Each person can buy a gift for the same family, or the family can share a private contribution link. Claimed gifts and contributions add to the same family balance.'.($welcomeEnabled ? ' The welcome bonus still applies only once per family account.' : '')],
                ['question' => 'Do gift hours expire? Are they refundable?', 'answer' => 'Gift hours do not expire. One credited hour stays one hour, even if standard prices rise. Purchases are non-refundable. If a covered visit is cancelled or there is a no-show, its reserved hours return to the family balance.'],
            ];
        }

        return [
            ['question' => 'Who can use my prepaid hours?', 'answer' => 'Hours belong to one family account, managed by its owner. That shared balance can pay for eligible care for you or your loved ones. You do not need a separate prepaid balance for each care recipient.'],
            ['question' => 'How do I use hours for a booking?', 'answer' => 'Available prepaid hours are selected by default for eligible care requests. Hours are reserved for the visit and used when the care is completed and approved. Any amount not covered is shown before you confirm. Existing card bookings keep their original payment method.'],
            ['question' => 'How does the welcome bonus work?', 'answer' => $bonus],
            ['question' => 'What care is covered?', 'answer' => 'Prepaid hours cover eligible standard-priced, individual non-medical care on LoLo, such as companionship, meal preparation and everyday help at home. Care depends on caregiver availability and service area. Custom-priced and shared-care bookings are excluded. Check availability before buying.'],
            ['question' => 'Do hours expire or change value?', 'answer' => 'No. Paid and welcome hours do not expire. One credited hour stays one hour, even if the standard hourly price increases. The platform fee is included in the package price; it is not charged again when you use those hours.'],
            ['question' => 'What if my plans change?', 'answer' => 'Package purchases are non-refundable. If a covered visit is cancelled or there is a no-show, its reserved hours return to your family balance for future care. There is currently no cancellation or no-show fee.'],
        ];
    }
}
