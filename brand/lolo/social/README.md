# LoLo Family Social Starter Pack

This folder contains a family-facing social media kit for LoLo, built around the current brand system:

- Warm Evergreen `#23483F`
- Soft Ivory `#FFF7EA`
- Warm Oat `#F1E5D2`
- Clay Coral `#C96B55`
- CTA Coral `#B95745`
- Deep Ink `#24302D`

## Files

- `posts.json`: source content, captions, hashtags, visual direction, and alt text for 12 family-side launch posts.
- `content-calendar.md`: a 30-day posting plan for family-side acquisition and education.
- `social-presence-setup.md`: platform bios, profile setup, highlight names, and tone rules.
- `image-prompts.md`: prompts for generating future LoLo family-side lifestyle images.
- `render-social-pack.mjs`: renders feed/story/linkedin PNG assets from `posts.json`.
- `output/`: generated image files.

## Generated Formats

- Square feed: `1080x1080`
- Story/Reel cover: `1080x1920`
- LinkedIn/Facebook link-style card: `1200x628`

Every post is rendered in square, story, and LinkedIn/Facebook card formats.

All generated assets include:

- Domain: `carelolo.com`
- Phone: `(984) 400-4008`

## Recommended Launch Rhythm

- Instagram: 4 feed posts/week, 3-5 stories/week.
- Facebook: 3 posts/week, with family-oriented captions and trust language.
- LinkedIn: 2 posts/week, focused on the family care model, transparent pricing, and founder/company credibility.

## Usage

Run this from the repo root to regenerate assets:

```bash
node brand/lolo/social/render-social-pack.mjs
```

The output images are designed as first-pass creative. They are intentionally simple, readable, and on-brand so they can move fast into Meta, LinkedIn, or Canva without redesigning the whole system.

## Notes

Use short captions on Instagram/Facebook and slightly more founder/company context on LinkedIn. Keep the voice warm, calm, direct, and adult. Avoid language that sounds too medical, too startup-y, or too cute. This pack is intentionally family-facing only.

Sources checked for current sizing norms:

- Sprout Social, always-up-to-date social media image sizes: https://sproutsocial.com/insights/social-media-image-sizes-guide/
- LinkedIn Help, single image ad specs: https://www.linkedin.com/help/linkedin/answer/a426534
