# LoLo Care editorial standards

## Publication rule

An article may be public only after a named author completes the working draft, a different qualified person reviews it, and a publisher approves the immutable live revision. The CMS enforces this separation. Draft edits never silently change the public revision.

## Evidence and trust

- Use primary sources for laws, regulations, public programs, clinical boundaries, statistics, prices, and product capabilities.
- Date every claim that can change. Record source publication and access dates.
- Describe LoLo Care as a marketplace for flexible, non-medical support. Never imply medical care, emergency response, guaranteed availability, employment, licensure, or clinical supervision unless the underlying service and evidence truly support it.
- Do not publish medical, legal, or financial advice. Explain scope and direct readers to an appropriate licensed professional or emergency service when needed.
- Treat competitor and facility claims as high risk. Verify them near publication and write neutrally.
- Never fabricate quotations, testimonials, credentials, research, outcomes, or local experience.

## Original research

For surveys, interviews, case studies, and proprietary data, complete the methodology field with sample, recruitment, dates, geography, exclusions, analysis method, limitations, privacy handling, and the person accountable for the work. Preserve de-identified source material outside the public article according to company retention policy.

## Search and AI visibility

- Answer the primary question early in plain language, then add useful local context and supporting detail.
- Use descriptive H2-H4 headings, accessible tables, concise lists, and FAQ blocks only for genuine reader questions.
- Maintain one canonical URL, a truthful title and description, visible author/reviewer/date information, source links, and original imagery with rights and alt text.
- Refresh or archive stale content. Do not change dates merely to make an article appear new.

## Required review checklist

The publishing gate requires originality, fact verification, source quality, medical boundaries, privacy/product claims, brand/competitor checks, and accessibility. Reviewers record useful notes; publishers set the next review date automatically.

Run `php artisan content:audit --fail-on-issues` in release checks. The scheduler also records a weekly audit in `storage/logs/content-audit.log`.
