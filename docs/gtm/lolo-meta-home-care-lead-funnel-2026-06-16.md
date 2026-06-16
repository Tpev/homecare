# LoLo Meta Home Care Lead Funnel

Date: June 16, 2026  
Destination: `/get-care`  
Primary outcome: qualified callback requests from families looking for non-medical home care support.

## Executive Setup

This funnel sends Meta paid traffic to a dedicated LoLo callback landing page with a short form. The form creates a family lead in the CRM, sends the existing ops alert, captures paid-social attribution, stores consent to contact, and fires the Meta Pixel `Lead` event after submission.

Meta Ads MCP status: no Meta Ads-specific MCP tool is available in this Codex session. Tool discovery only exposed Node REPL and Playwright/browser tooling, so the setup below is based on the local app plus current official Meta documentation.

## Funnel Flow

1. User sees a Facebook or Instagram ad for non-medical home support.
2. User clicks to `/get-care` with Meta dynamic UTM parameters.
3. Landing page gives a premium, direct explanation: home care support, from $30/hr, vetted caregiver profiles, flexible first step.
4. User submits name, phone, optional email, ZIP, care need, callback time, notes, and contact consent.
5. App creates a `family` lead with `source = meta_ads` when Meta attribution is present.
6. App dispatches the browser event that fires Meta Pixel `Lead`.
7. Ops calls or texts the lead back using the follow-up SOP below.

## Tracking And URLs

Use this as the Meta ad URL:

```text
https://carelolo.com/get-care?utm_source=meta&utm_medium=paid_social&utm_campaign={{campaign.name}}&utm_term={{adset.name}}&utm_content={{ad.name}}&campaign_id={{campaign.id}}&adset_id={{adset.id}}&ad_id={{ad.id}}&placement={{placement}}&site_source_name={{site_source_name}}
```

Required events:

- PageView: already in the marketing layout.
- Lead: fired after successful callback form submission.
- CRM lead source: `meta_ads`.
- CRM source detail: `campaign / ad set / ad`.

QA before launch:

1. Open `/get-care?utm_source=meta&utm_medium=paid_social&utm_campaign=test_campaign&utm_term=test_adset&utm_content=test_ad`.
2. Submit a test lead with a test phone number.
3. Confirm CRM lead source is `meta_ads`.
4. Confirm `data.tracking` stores UTM values.
5. Confirm Meta Events Manager Test Events sees `PageView` and `Lead`.
6. Confirm ops alert email is received.
7. Delete or mark the test lead as test/closed in CRM.
8. In Events Manager, confirm the production domain is allowed under the Pixel traffic permissions/settings. Localhost may show a Pixel traffic-permission warning during development; production should not.

## Landing Page Direction

Page promise:

> A calmer way to start home care.

Support copy:

> Tell us what kind of support your family is arranging. LoLo will review the details and call back with a clear next step for companionship and everyday help at home.

Trust signals:

- Clear hourly care from $30/hr.
- Vetted caregiver profiles.
- Flexible first step: one visit, a few hours, or recurring support.
- Non-medical support only.
- No emergency positioning.

Form fields:

- Full name: required.
- Phone: required.
- Email: optional.
- ZIP code: optional but recommended for qualification.
- Care need: companion care, meal prep, errands and rides, light housekeeping, not sure yet.
- Best time to call: today, tomorrow morning, tomorrow afternoon, later this week.
- Notes: optional.
- Contact consent: required.

## Meta Campaign Setup

Campaign name:

```text
LoLo | Leads | Home Care Callback | Wake County | 2026-06
```

Campaign parameters:

- Buying type: Auction.
- Objective: Leads.
- Special Ad Category: None selected for the default launch. Home care is not one of Meta's core Special Ad Categories of housing, employment, financial products/services, or social issues/elections/politics. Re-check if the account is flagged by Meta as health/wellness or if legal counsel asks for a stricter interpretation.
- Campaign budget: Off for launch, so the local prospecting ad set gets enough spend.
- A/B test: Off at launch.

Ad Set 1:

```text
Prospecting | Wake County | Broad 35-64 | Website Lead
```

Ad set parameters:

- Conversion location: Website.
- Pixel: `1842558893085096`.
- Conversion event: Lead.
- Performance goal: Maximize number of leads.
- Attribution setting: 7-day click, 1-day view.
- Budget: $75/day.
- Schedule: Start immediately after QA, no end date.
- Location: People living in Raleigh, NC plus 25 miles. If county targeting is available and matches service supply, use Wake County, NC.
- Age: 35-64.
- Gender: All.
- Audience: Advantage+ audience on, with location as the hard control. Keep detailed targeting broad. Use creative to qualify the audience instead of over-segmenting.
- Detailed targeting suggestions if available: family caregivers, elder care, home care, aging parents, AARP, assisted living, senior care. Do not force narrow stacks if Meta expands beyond them.
- Exclusions: existing family leads/customer list if available, existing employees/caregivers if uploaded.
- Placements: Advantage+ placements on.
- Brand safety: Standard inventory.
- Destination: Website.

Ad Set 2, start only after at least 500 warm users:

```text
Retargeting | 30D Site + Engagers | Website Lead
```

Ad set parameters:

- Budget: $15/day.
- Audience: website visitors 30 days, Facebook/Instagram engagers 365 days, video viewers if video ads are added.
- Exclude: submitted leads/customer list.
- Age/gender/location: same service area controls.
- Creative angle: direct callback, price clarity, one visit.

Fallback if Meta restricts health/wellness event optimization:

- Keep the same campaign objective.
- Switch performance goal to landing page views temporarily.
- Continue passing UTMs and collecting CRM lead source.
- Prioritize offline CRM quality reporting until Meta event eligibility is resolved.

## Ad Creative Assets

Use the existing Meta ad image files in:

```text
brand/lolo/social/output/meta-ads-v2/
```

Recommended first six ads:

1. `01-this-week-square.png` and `01-this-week-portrait.png`
   - Ad name: `A01_Need_Help_This_Week`
   - Angle: immediate but calm callback.
2. `03-short-visits-square.png` and `03-short-visits-portrait.png`
   - Ad name: `A02_Short_Visits_Real_Relief`
   - Angle: small visits, real support.
3. `04-real-door-square.png` and `04-real-door-portrait.png`
   - Ad name: `A03_Real_Person_At_The_Door`
   - Angle: human reassurance.
4. `06-price-square.png` and `06-price-portrait.png`
   - Ad name: `A04_Care_From_30`
   - Angle: price clarity.
5. `07-one-visit-square.png` and `07-one-visit-portrait.png`
   - Ad name: `A05_Start_With_One_Visit`
   - Angle: low-pressure first step.
6. `09-evening-square.png` and `09-evening-portrait.png`
   - Ad name: `A06_Help_After_Work`
   - Angle: flexible timing.

Hold back for now:

- `02-mom-fine-*`: stronger personal-attribute risk.
- `08-less-worry-*`: usable later, but start with more neutral wording.
- `05-no-agency-*`: can be tested after proof of performance, but avoid aggressive competitor framing early.
- `10-cant-be-there-*`: useful retargeting angle, but softer as a second-wave ad.

Creative specs to upload:

- Feed: use square or 4:5 where available.
- Stories/Reels: use portrait assets.
- File type: PNG.
- Keep automated creative enhancements on only if previews preserve readable text. Turn off any enhancement that crops the wordmark, price, phone number, or CTA.

## Ad Copy Bank

Keep copy neutral. Do not imply Meta knows the viewer has a medical condition, disability, elderly parent, or urgent family problem.

### Primary Texts

1. Flexible non-medical support at home, from companionship to errands and meals. Share a few details and LoLo will call back with the next step.

2. A few hours of practical help can make the week easier. LoLo offers non-medical home support from $30/hr. Request a callback.

3. Start with one visit or recurring help. LoLo connects families with vetted caregiver profiles for everyday support at home.

4. Clear hourly home support without a long-term commitment. Tell LoLo what kind of help would fit, and we will call back.

5. Companionship, meal support, errands, rides, and light housekeeping. Request a LoLo callback and start with a clear plan.

6. Home support can start small. Share the basics, choose a callback time, and LoLo will help with the next step.

### Headlines

- Start home care calmly
- Request a care callback
- Care from $30/hr
- Flexible help at home
- Start with one visit
- Non-medical home support

### Descriptions

- LoLo will call back.
- No long-term pressure.
- Everyday help at home.
- Clear first step.
- Vetted caregiver profiles.

### CTA Button

Cold prospecting: `Learn More`  
Retargeting: `Contact Us`

## Launch Structure

Launch day:

1. Confirm landing page and form QA.
2. Confirm Meta Pixel PageView and Lead fire in Events Manager.
3. Upload six ads into the prospecting ad set.
4. Use the exact UTM template.
5. Publish all ads at once so Meta can compare creative.
6. Do not edit for the first 72 hours unless an ad is rejected or broken.

Day 4 review:

- Kill ads with clear delivery but weak signal: link CTR below 0.7 percent after 1,500 impressions.
- Kill ads spending more than 2.5x target CPL without a lead.
- Keep any ad with qualified lead conversations even if raw CPL is not the lowest.
- Duplicate the best ad and test one new primary text.

Day 7 review:

- Create the retargeting ad set if audience size is large enough.
- Move budget toward ads producing reachable phone leads.
- Add a second landing page variant only if the creative is winning but form conversion is weak.

Scale rule:

- Increase budget by 15 to 20 percent every 48 hours when CPL and lead quality are stable.
- Avoid daily edits that reset learning or blur the test.

## Lead Follow-Up SOP

Speed matters more than a perfect script.

Call attempt 1:

- Within 5 minutes during business hours.
- If after hours, call next morning and send the SMS below.

Opening:

```text
Hi, this is [Name] from LoLo. I am calling about the home support callback request you just sent in. Is now still an okay time?
```

Qualifying questions:

1. What kind of help would be most useful right now: companionship, meals, errands, rides, light housekeeping, or something else?
2. What ZIP code or neighborhood should we plan around?
3. Is this one visit, a few hours this week, or recurring support?
4. What days or times would work best?
5. Is any medical care, lifting, dementia supervision, or urgent safety support needed? If yes, clarify LoLo is non-medical and route appropriately.
6. Are you comfortable starting around the $30/hr companionship rate if the caregiver fit is right?

If no answer:

```text
Hi, this is [Name] from LoLo. We received your home support callback request. I can help with next steps for non-medical companionship and everyday support. Reply here or call (984) 400-4008 when it is a good time.
```

Follow-up cadence:

- Attempt 1: within 5 minutes.
- Attempt 2: 2 to 4 hours later.
- Attempt 3: next business day.
- Attempt 4: two days later with a softer "still useful?" message.
- Mark as lost only after no response to four attempts.

CRM stages:

- New: form submitted, not contacted.
- Contacted: call or SMS sent.
- Qualified: fit confirmed, service area and need clear.
- Intake scheduled: next step booked.
- Converted: care request or booking created.
- Lost: not a fit, unreachable, no longer needed, outside service area.

## Compliance Rules

Ad copy must not say or imply:

- "Your parent is unsafe."
- "Are you worried about Mom?"
- "Struggling with dementia care?"
- "Sick of caregiving burnout?"
- "We know you need help."
- Guaranteed caregiver availability.
- Medical home health, nursing, Medicare coverage, or emergency care.

Safer phrasing:

- "For families arranging help at home."
- "Non-medical companionship and everyday support."
- "Start with one visit or recurring help."
- "Request a callback."
- "Care from $30/hr."

Landing page and form requirements:

- Clear business identity: LoLo.
- Clear offer: non-medical home support.
- Consent line for call/text follow-up.
- Privacy policy link.
- No hidden pricing claim beyond stated starting rate.
- No emergency-care positioning.

## Reporting Dashboard

Daily:

- Spend.
- Impressions.
- Link CTR.
- Landing page views.
- Leads.
- Cost per lead.
- Reachable leads.
- Qualified leads.
- Cost per qualified lead.

Weekly:

- Creative winner by qualified lead, not only raw CPL.
- ZIP codes producing leads.
- Top care needs.
- Time from lead submission to first call.
- Contact rate by callback preference.
- Disqualification reasons.

## Official Meta Sources Checked

- Special Ad Categories, Meta for Developers: https://developers.facebook.com/documentation/ads-commerce/marketing-api/audiences/special-ad-category
- Meta Advertising Standards overview: https://transparency.meta.com/policies/ad-standards/
- Privacy Violations and Personal Attributes policy: https://transparency.meta.com/policies/ad-standards/objectionable-content/privacy-violations-personal-attributes/
- Health and Wellness policy: https://transparency.meta.com/policies/ad-standards/restricted-goods-services/health-wellness/
- Meta Pixel standard events and Lead event: https://www.facebook.com/business/help/402791146561655
- Standard and custom website events: https://www.facebook.com/business/help/964258670337005
- Performance goals in Meta Ads Manager: https://www.facebook.com/business/help/355670007911605
- Lead ads objective overview: https://www.facebook.com/business/ads/ad-objectives/lead-generation
- Lead image ad specs for Facebook Feed: https://www.facebook.com/business/ads-guide/update/image/facebook-feed/outcome-leads
- Lead image ad specs for Instagram Feed: https://www.facebook.com/business/ads-guide/update/image/instagram-feed/outcome-leads
- Aspect ratios supported by placements: https://www.facebook.com/business/help/682655495435254
