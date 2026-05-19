import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const root = path.resolve(__dirname, '../../..');
const postsPath = path.join(__dirname, 'posts.json');
const outputDir = path.join(__dirname, 'output');
const assetCache = new Map();

const brand = {
  evergreen: '#23483F',
  evergreenDeep: '#173A33',
  ivory: '#FFF7EA',
  oat: '#F1E5D2',
  coral: '#C96B55',
  cta: '#B95745',
  ink: '#24302D',
  stone: '#6F766F',
};

const contact = {
  domain: 'carelolo.com',
  phone: '(984) 400-4008',
};

const formats = {
  square: { width: 1080, height: 1080 },
  story: { width: 1080, height: 1920 },
  linkedin: { width: 1200, height: 628 },
};

function mimeFor(relativePath) {
  const extension = path.extname(relativePath).toLowerCase();

  if (extension === '.svg') {
    return 'image/svg+xml';
  }

  if (extension === '.jpg' || extension === '.jpeg') {
    return 'image/jpeg';
  }

  if (extension === '.png') {
    return 'image/png';
  }

  if (extension === '.ttf') {
    return 'font/ttf';
  }

  return 'application/octet-stream';
}

async function loadAsset(relativePath) {
  const normalized = relativePath.replaceAll('\\', '/');
  const absolute = path.join(root, normalized);
  const bytes = await fs.readFile(absolute);
  assetCache.set(normalized, `data:${mimeFor(normalized)};base64,${bytes.toString('base64')}`);
}

const assetUrl = (relativePath) => assetCache.get(relativePath.replaceAll('\\', '/'));
const localUrl = (relativePath) => assetCache.get(relativePath.replaceAll('\\', '/'));

function escapeHtml(value = '') {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;');
}

function layoutFor(post, formatName) {
  if (formatName === 'story') {
    return storyLayout(post);
  }

  if (formatName === 'linkedin') {
    return linkedinLayout(post);
  }

  return squareLayout(post);
}

function squareLayout(post) {
  return `
    <section class="canvas square ${post.template}">
      <img class="photo photo-large" src="${assetUrl(post.image)}" alt="">
      <div class="veil"></div>
      <header class="brand-row">
        <img src="${localUrl('brand/lolo/assets/lolo-wordmark-evergreen.svg')}" alt="LoLo">
        <span>${escapeHtml(post.audience)}</span>
      </header>

      <main class="main-copy">
        <p class="kicker">${escapeHtml(post.kicker)}</p>
        <h1>${escapeHtml(post.headline)}</h1>
        <p class="body">${escapeHtml(post.body)}</p>
        ${copyListMarkup(post)}
        ${noteMarkup(post)}
      </main>

      ${post.template === 'price' ? priceBadge() : ''}
      ${post.template === 'steps' ? stepBand(post.items) : ''}
      ${post.template === 'profile' ? profileCard(post) : ''}

      <footer class="footer-row">
        <span class="cta">${escapeHtml(post.cta)}</span>
        <span class="site">${contactMarkup()}</span>
      </footer>
    </section>
  `;
}

function storyLayout(post) {
  return `
    <section class="canvas story ${post.template}">
      <img class="photo photo-top" src="${assetUrl(post.image)}" alt="">
      <div class="story-panel">
        <header class="brand-row">
          <img src="${localUrl('brand/lolo/assets/lolo-wordmark-evergreen.svg')}" alt="LoLo">
          <span>${escapeHtml(post.audience)}</span>
        </header>
        <main class="main-copy">
          <p class="kicker">${escapeHtml(post.kicker)}</p>
          <h1>${escapeHtml(post.headline)}</h1>
          <p class="body">${escapeHtml(post.body)}</p>
          ${copyListMarkup(post)}
          ${noteMarkup(post)}
        </main>
        ${post.template === 'price' ? priceBadge() : ''}
        ${post.template === 'steps' ? stepBand(post.items) : ''}
        ${post.template === 'profile' ? profileCard(post) : ''}
        <footer class="footer-row">
          <span class="cta">${escapeHtml(post.cta)}</span>
          <span class="site">${contactMarkup()}</span>
        </footer>
      </div>
    </section>
  `;
}

function linkedinLayout(post) {
  return `
    <section class="canvas linkedin ${post.template}">
      <div class="linkedin-copy">
        <header class="brand-row">
          <img src="${localUrl('brand/lolo/assets/lolo-wordmark-evergreen.svg')}" alt="LoLo">
          <span>${escapeHtml(post.audience)}</span>
        </header>
        <main class="main-copy">
          <p class="kicker">${escapeHtml(post.kicker)}</p>
          <h1>${escapeHtml(post.headline)}</h1>
          <p class="body">${escapeHtml(post.body)}</p>
          ${copyListMarkup(post)}
          ${noteMarkup(post)}
        </main>
        <footer class="footer-row">
          <span class="cta">${escapeHtml(post.cta)}</span>
          <span class="site">${contactMarkup()}</span>
        </footer>
      </div>
      <div class="linkedin-photo-wrap">
        <img class="photo" src="${assetUrl(post.image)}" alt="">
      </div>
    </section>
  `;
}

function listMarkup(items = []) {
  return `
    <div class="item-list">
      ${items.map((item) => `<div><span></span>${escapeHtml(item)}</div>`).join('')}
    </div>
  `;
}

function copyListMarkup(post) {
  if (!post.items || post.template === 'steps') {
    return '';
  }

  return listMarkup(post.items);
}

function noteMarkup(post) {
  if (!post.note) {
    return '';
  }

  return `<p class="note">${escapeHtml(post.note)}</p>`;
}

function priceBadge() {
  return `
    <aside class="price-badge">
      <span>Starting family rate</span>
      <strong>$30/hr</strong>
    </aside>
  `;
}

function stepBand(items = []) {
  return `
    <div class="step-band">
      ${items.map((item, index) => `
        <div>
          <span>0${index + 1}</span>
          <strong>${escapeHtml(item)}</strong>
        </div>
      `).join('')}
    </div>
  `;
}

function profileCard(post) {
  return `
    <aside class="profile-card">
      <img src="${assetUrl(post.image)}" alt="">
      <div>
        <span>Caregiver profile</span>
        <strong>Trusted, flexible, local</strong>
      </div>
    </aside>
  `;
}

function contactMarkup() {
  return `${escapeHtml(contact.domain)}<br>${escapeHtml(contact.phone)}`;
}

function htmlDocument(post, formatName) {
  const { width, height } = formats[formatName];
  const serifFontUrl = localUrl('brand/lolo/fonts/SourceSerif4-Bold.ttf');
  const interFontUrl = localUrl('brand/lolo/fonts/Inter-Medium.ttf');

  return `
    <!doctype html>
    <html>
      <head>
        <meta charset="utf-8">
        <style>
          @font-face {
            font-family: 'Source Serif 4 Local';
            src: url('${serifFontUrl}') format('truetype');
            font-weight: 700;
          }

          @font-face {
            font-family: 'Inter Local';
            src: url('${interFontUrl}') format('truetype');
            font-weight: 500 800;
          }

          * {
            box-sizing: border-box;
          }

          html,
          body {
            width: ${width}px;
            height: ${height}px;
            margin: 0;
            overflow: hidden;
            background: ${brand.ivory};
          }

          body {
            font-family: 'Inter Local', Inter, Arial, sans-serif;
            color: ${brand.ink};
          }

          .canvas {
            position: relative;
            width: ${width}px;
            height: ${height}px;
            overflow: hidden;
            background:
              linear-gradient(180deg, rgba(255, 247, 234, 0.98), rgba(255, 247, 234, 0.94)),
              ${brand.ivory};
          }

          .canvas::before {
            content: "";
            position: absolute;
            inset: 44px;
            border: 2px solid rgba(35, 72, 63, 0.10);
            border-radius: 22px;
            pointer-events: none;
          }

          .photo {
            display: block;
            object-fit: cover;
          }

          .photo-large {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
          }

          .photo-top {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 48%;
          }

          .veil {
            position: absolute;
            inset: 0;
            background:
              linear-gradient(90deg, rgba(255, 247, 234, 0.98) 0%, rgba(255, 247, 234, 0.90) 44%, rgba(255, 247, 234, 0.18) 76%),
              linear-gradient(0deg, rgba(35, 72, 63, 0.34), rgba(35, 72, 63, 0.02) 46%);
          }

          .brand-row {
            position: absolute;
            top: 76px;
            left: 76px;
            right: 76px;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 20px;
          }

          .brand-row img {
            width: 134px;
            height: auto;
          }

          .brand-row span {
            border-left: 2px solid rgba(35, 72, 63, 0.20);
            padding-left: 18px;
            color: ${brand.stone};
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
          }

          .main-copy {
            position: absolute;
            z-index: 2;
            left: 76px;
            right: 520px;
            top: 250px;
          }

          .square .main-copy {
            top: 205px;
            left: 62px;
            right: 330px;
            padding: 36px 38px 34px;
            border: 1px solid rgba(35, 72, 63, 0.10);
            border-radius: 24px;
            background: rgba(255, 247, 234, 0.88);
            box-shadow: 0 28px 74px rgba(23, 58, 51, 0.12);
            backdrop-filter: blur(14px);
          }

          .kicker {
            margin: 0 0 24px;
            color: ${brand.cta};
            font-size: 20px;
            font-weight: 900;
            letter-spacing: 0.14em;
            text-transform: uppercase;
          }

          h1 {
            margin: 0;
            color: ${brand.evergreen};
            font-family: 'Source Serif 4 Local', Georgia, serif;
            font-size: 86px;
            font-weight: 700;
            line-height: 0.92;
            letter-spacing: 0;
          }

          .square h1 {
            font-size: 61px;
            line-height: 0.95;
          }

          .body {
            max-width: 610px;
            margin: 34px 0 0;
            color: rgba(36, 48, 45, 0.74);
            font-size: 31px;
            line-height: 1.34;
          }

          .square .body {
            margin-top: 22px;
            font-size: 24px;
            line-height: 1.36;
          }

          .note {
            margin: 28px 0 0;
            border-left: 5px solid ${brand.coral};
            padding-left: 18px;
            color: rgba(36, 48, 45, 0.74);
            font-size: 21px;
            font-weight: 800;
            line-height: 1.34;
          }

          .item-list {
            display: grid;
            gap: 15px;
            margin-top: 28px;
            max-width: 650px;
          }

          .item-list div {
            display: flex;
            align-items: center;
            gap: 14px;
            border: 1px solid rgba(35, 72, 63, 0.12);
            border-radius: 16px;
            background: rgba(255, 250, 240, 0.84);
            padding: 14px 18px;
            color: ${brand.ink};
            font-size: 20px;
            font-weight: 800;
          }

          .item-list span {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            background: ${brand.coral};
            box-shadow: 0 0 0 7px rgba(201, 107, 85, 0.14);
          }

          .footer-row {
            position: absolute;
            z-index: 2;
            left: 76px;
            right: 76px;
            bottom: 76px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
          }

          .square .footer-row {
            left: 62px;
            right: 62px;
            bottom: 58px;
          }

          .cta {
            display: inline-flex;
            min-height: 66px;
            align-items: center;
            border-radius: 14px;
            background: ${brand.cta};
            color: #fff;
            padding: 0 28px;
            font-size: 22px;
            font-weight: 900;
            box-shadow: 0 18px 44px rgba(185, 87, 69, 0.20);
          }

          .site {
            display: inline-block;
            border: 1px solid rgba(35, 72, 63, 0.10);
            border-radius: 14px;
            background: rgba(255, 247, 234, 0.82);
            padding: 10px 14px;
            color: rgba(36, 48, 45, 0.72);
            font-size: 21px;
            font-weight: 800;
            line-height: 1.34;
            text-align: right;
            box-shadow: 0 14px 36px rgba(23, 58, 51, 0.10);
          }

          .price-badge {
            position: absolute;
            z-index: 3;
            right: 76px;
            bottom: 180px;
            width: 340px;
            border-radius: 18px;
            background: ${brand.evergreen};
            color: #fff;
            padding: 30px;
          }

          .price-badge span {
            color: rgba(255, 247, 234, 0.72);
            font-size: 18px;
            font-weight: 900;
            letter-spacing: 0.13em;
            text-transform: uppercase;
          }

          .price-badge strong {
            display: block;
            margin-top: 18px;
            font-family: 'Source Serif 4 Local', Georgia, serif;
            font-size: 88px;
            line-height: 0.9;
          }

          .step-band {
            position: absolute;
            z-index: 3;
            left: 76px;
            right: 76px;
            bottom: 180px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
          }

          .square .step-band {
            left: 100px;
            right: 100px;
            bottom: 182px;
          }

          .step-band div {
            border-radius: 16px;
            background: rgba(241, 229, 210, 0.92);
            padding: 24px;
          }

          .step-band span {
            color: ${brand.coral};
            font-size: 18px;
            font-weight: 900;
          }

          .step-band strong {
            display: block;
            margin-top: 16px;
            color: ${brand.evergreen};
            font-family: 'Source Serif 4 Local', Georgia, serif;
            font-size: 30px;
            line-height: 1.02;
          }

          .profile-card {
            position: absolute;
            z-index: 3;
            right: 74px;
            bottom: 180px;
            display: flex;
            align-items: center;
            gap: 22px;
            width: 410px;
            border: 1px solid rgba(35, 72, 63, 0.12);
            border-radius: 18px;
            background: rgba(255, 247, 234, 0.94);
            padding: 20px;
            box-shadow: 0 22px 60px rgba(23, 58, 51, 0.16);
          }

          .profile-card img {
            width: 104px;
            height: 104px;
            border-radius: 999px;
            object-fit: cover;
          }

          .profile-card span {
            color: ${brand.coral};
            font-size: 16px;
            font-weight: 900;
            letter-spacing: 0.11em;
            text-transform: uppercase;
          }

          .profile-card strong {
            display: block;
            margin-top: 10px;
            color: ${brand.evergreen};
            font-size: 25px;
            line-height: 1.1;
          }

          .checklist .main-copy,
          .quote .main-copy,
          .steps .main-copy,
          .price .main-copy {
            right: 76px;
          }

          .square.checklist .main-copy,
          .square.quote .main-copy,
          .square.steps .main-copy,
          .square.price .main-copy,
          .square.photo .main-copy {
            right: 330px;
          }

          .price .main-copy {
            max-width: 640px;
          }

          .square.price .main-copy {
            right: 390px;
          }

          .price .canvas,
          .steps .canvas {
            background: ${brand.oat};
          }

          .story {
            background: ${brand.oat};
          }

          .story .story-panel {
            position: absolute;
            inset: 42% 42px 42px;
            border-radius: 28px;
            background: rgba(255, 247, 234, 0.96);
            box-shadow: 0 -26px 80px rgba(23, 58, 51, 0.18);
          }

          .story .brand-row {
            top: 66px;
            left: 64px;
            right: 64px;
          }

          .story .main-copy {
            top: 230px;
            left: 64px;
            right: 64px;
          }

          .story h1 {
            font-size: 76px;
            line-height: 0.96;
          }

          .story .body {
            font-size: 30px;
          }

          .story .note {
            font-size: 25px;
          }

          .story .footer-row {
            left: 64px;
            right: 64px;
            bottom: 64px;
          }

          .story .price-badge,
          .story .profile-card {
            right: 64px;
            left: 64px;
            bottom: 210px;
            width: auto;
          }

          .story .step-band {
            left: 64px;
            right: 64px;
            bottom: 210px;
            grid-template-columns: 1fr;
          }

          .linkedin {
            display: grid;
            grid-template-columns: 1.04fr 0.96fr;
            background: ${brand.ivory};
          }

          .linkedin::before {
            inset: 32px;
          }

          .linkedin-copy {
            position: relative;
          }

          .linkedin .brand-row {
            top: 58px;
            left: 58px;
            right: 40px;
          }

          .linkedin .main-copy {
            top: 180px;
            left: 58px;
            right: 50px;
          }

          .linkedin h1 {
            font-size: 53px;
            line-height: 0.98;
          }

          .linkedin .body {
            font-size: 22px;
            line-height: 1.35;
          }

          .linkedin .note {
            display: none;
          }

          .linkedin .footer-row {
            left: 58px;
            right: 50px;
            bottom: 58px;
          }

          .linkedin .cta {
            min-height: 54px;
            border-radius: 12px;
            font-size: 18px;
          }

          .linkedin .site {
            font-size: 17px;
          }

          .linkedin-photo-wrap {
            position: relative;
            margin: 44px 44px 44px 0;
            overflow: hidden;
            border-radius: 22px;
          }

          .linkedin-photo-wrap::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(0deg, rgba(35, 72, 63, 0.18), rgba(35, 72, 63, 0));
          }

          .linkedin .photo {
            width: 100%;
            height: 100%;
          }

          .linkedin .item-list {
            display: none;
          }

          .linkedin .price-badge,
          .linkedin .profile-card,
          .linkedin .step-band {
            display: none;
          }
        </style>
      </head>
      <body>
        ${layoutFor(post, formatName)}
      </body>
    </html>
  `;
}

async function ensureImagesLoaded(page) {
  await page.evaluate(async () => {
    await Promise.all(
      [...document.images].map((image) => {
        if (image.complete) {
          return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
          image.addEventListener('load', resolve, { once: true });
          image.addEventListener('error', reject, { once: true });
        });
      }),
    );
  });
}

async function renderPost(browser, post, formatName) {
  const { width, height } = formats[formatName];
  const page = await browser.newPage({
    viewport: { width, height },
    deviceScaleFactor: 1,
  });

  await page.setContent(htmlDocument(post, formatName), { waitUntil: 'load' });
  await ensureImagesLoaded(page);
  await page.screenshot({
    path: path.join(outputDir, formatName, `${post.id}.png`),
    clip: { x: 0, y: 0, width, height },
  });
  await page.close();
}

async function main() {
  const raw = await fs.readFile(postsPath, 'utf8');
  const posts = JSON.parse(raw);
  const requiredAssets = new Set([
    'brand/lolo/assets/lolo-wordmark-evergreen.svg',
    'brand/lolo/fonts/SourceSerif4-Bold.ttf',
    'brand/lolo/fonts/Inter-Medium.ttf',
    ...posts.map((post) => post.image),
  ]);

  for (const asset of requiredAssets) {
    await loadAsset(asset);
  }

  for (const dir of ['square', 'story', 'linkedin']) {
    const full = path.join(outputDir, dir);
    await fs.mkdir(full, { recursive: true });
    const files = await fs.readdir(full);

    await Promise.all(
      files
        .filter((file) => file.endsWith('.png'))
        .map((file) => fs.unlink(path.join(full, file))),
    );
  }

  const browser = await chromium.launch({ headless: true });

  for (const post of posts) {
    await renderPost(browser, post, 'square');
    await renderPost(browser, post, 'story');

    await renderPost(browser, post, 'linkedin');
  }

  await browser.close();
  console.log(`Rendered ${posts.length} posts into ${outputDir}`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
