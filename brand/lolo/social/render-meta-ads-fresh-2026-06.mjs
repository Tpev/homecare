import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const root = path.resolve(__dirname, '../../..');
const adsPath = path.join(__dirname, 'meta-ads-fresh-2026-06.json');
const outputDir = path.join(__dirname, 'output', 'meta-ads-fresh-2026-06');

const brand = {
  deep: '#0F3D3E',
  deepSoft: '#23483F',
  ink: '#24302D',
  cream: '#F5F1EB',
  ivory: '#FAF9F7',
  oat: '#F1E5D2',
  blue: '#4F6FAF',
  lavender: '#7C5DDC',
  coral: '#C96B55',
  cta: '#B95745',
  muted: '#64736F',
};

const contact = {
  domain: 'carelolo.com/get-care',
  phone: '(984) 400-4008',
};

const formats = {
  square: { width: 1080, height: 1080 },
  portrait: { width: 1080, height: 1920 },
};

function mimeFor(filePath) {
  const extension = path.extname(filePath).toLowerCase();

  if (extension === '.svg') return 'image/svg+xml';
  if (extension === '.jpg' || extension === '.jpeg') return 'image/jpeg';
  if (extension === '.png') return 'image/png';
  if (extension === '.ttf') return 'font/ttf';

  return 'application/octet-stream';
}

async function dataUrl(relativePath) {
  const normalized = relativePath.replaceAll('\\', '/');
  const absolutePath = path.join(root, normalized);
  const bytes = await fs.readFile(absolutePath);

  return `data:${mimeFor(normalized)};base64,${bytes.toString('base64')}`;
}

function escapeHtml(value = '') {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;');
}

function themeVars(theme) {
  if (theme === 'deep') {
    return {
      panel: brand.deep,
      panelText: '#FFFFFF',
      body: 'rgba(255,255,255,0.78)',
      kicker: '#F1E5D2',
      proofBg: 'rgba(255,255,255,0.12)',
      proofColor: '#FFFFFF',
      logoFilter: 'brightness(0) invert(1)',
      ctaBg: brand.ivory,
      ctaColor: brand.deep,
      line: brand.coral,
    };
  }

  if (theme === 'cream') {
    return {
      panel: 'rgba(245,241,235,0.94)',
      panelText: brand.deep,
      body: 'rgba(36,48,45,0.72)',
      kicker: brand.cta,
      proofBg: 'rgba(79,111,175,0.12)',
      proofColor: brand.blue,
      logoFilter: 'none',
      ctaBg: brand.deep,
      ctaColor: '#FFFFFF',
      line: brand.lavender,
    };
  }

  return {
    panel: 'rgba(250,249,247,0.95)',
    panelText: brand.deep,
    body: 'rgba(36,48,45,0.72)',
    kicker: brand.cta,
    proofBg: 'rgba(201,107,85,0.12)',
    proofColor: brand.cta,
    logoFilter: 'none',
    ctaBg: brand.deep,
    ctaColor: '#FFFFFF',
    line: brand.blue,
  };
}

function squareMarkup(ad, assets) {
  const vars = themeVars(ad.theme);

  return `
    <section class="ad square theme-${escapeHtml(ad.theme)}">
      <img class="photo" src="${assets.image}" alt="">
      <div class="photo-wash"></div>
      <div class="panel">
        <header class="brand-row">
          <img src="${assets.logo}" alt="LoLo">
          <span></span>
        </header>

        <main>
          <p class="kicker">${escapeHtml(ad.kicker)}</p>
          <h1>${escapeHtml(ad.headline)}</h1>
          <p class="body">${escapeHtml(ad.body)}</p>
          <p class="proof">${escapeHtml(ad.proof)}</p>
        </main>

        <footer>
          <span class="cta">${escapeHtml(ad.cta)}</span>
          <span class="contact">${escapeHtml(contact.domain)}<br>${escapeHtml(contact.phone)}</span>
        </footer>
      </div>

      <style>
        .panel { background: ${vars.panel}; color: ${vars.panelText}; }
        .brand-row img { filter: ${vars.logoFilter}; }
        .brand-row span { background: ${vars.line}; }
        h1 { color: ${vars.panelText}; }
        .kicker { color: ${vars.kicker}; }
        .body { color: ${vars.body}; }
        .proof { background: ${vars.proofBg}; color: ${vars.proofColor}; }
        .cta { background: ${vars.ctaBg}; color: ${vars.ctaColor}; }
      </style>
    </section>
  `;
}

function portraitMarkup(ad, assets) {
  const vars = themeVars(ad.theme);

  return `
    <section class="ad portrait theme-${escapeHtml(ad.theme)}">
      <div class="photo-panel">
        <img class="photo" src="${assets.image}" alt="">
        <div class="photo-wash"></div>
      </div>

      <div class="panel">
        <header class="brand-row">
          <img src="${assets.logo}" alt="LoLo">
          <span></span>
        </header>

        <main>
          <p class="kicker">${escapeHtml(ad.kicker)}</p>
          <h1>${escapeHtml(ad.headline)}</h1>
          <p class="body">${escapeHtml(ad.body)}</p>
          <p class="proof">${escapeHtml(ad.proof)}</p>
        </main>

        <footer>
          <span class="cta">${escapeHtml(ad.cta)}</span>
          <span class="contact">${escapeHtml(contact.domain)}<br>${escapeHtml(contact.phone)}</span>
        </footer>
      </div>

      <style>
        .panel { background: ${vars.panel}; color: ${vars.panelText}; }
        .brand-row img { filter: ${vars.logoFilter}; }
        .brand-row span { background: ${vars.line}; }
        h1 { color: ${vars.panelText}; }
        .kicker { color: ${vars.kicker}; }
        .body { color: ${vars.body}; }
        .proof { background: ${vars.proofBg}; color: ${vars.proofColor}; }
        .cta { background: ${vars.ctaBg}; color: ${vars.ctaColor}; }
      </style>
    </section>
  `;
}

function documentHtml(ad, formatName, assets) {
  const { width, height } = formats[formatName];
  const layout = formatName === 'portrait'
    ? portraitMarkup(ad, assets)
    : squareMarkup(ad, assets);

  return `
    <!doctype html>
    <html>
      <head>
        <meta charset="utf-8">
        <style>
          @font-face {
            font-family: 'Source Serif 4 Local';
            src: url('${assets.serif}') format('truetype');
            font-weight: 700;
          }

          @font-face {
            font-family: 'Inter Local';
            src: url('${assets.inter}') format('truetype');
            font-weight: 500 850;
          }

          * { box-sizing: border-box; }

          html,
          body {
            width: ${width}px;
            height: ${height}px;
            margin: 0;
            overflow: hidden;
            background: ${brand.cream};
          }

          body {
            font-family: 'Inter Local', Inter, Arial, sans-serif;
            color: ${brand.ink};
          }

          .ad {
            position: relative;
            width: ${width}px;
            height: ${height}px;
            overflow: hidden;
            background: ${brand.cream};
          }

          .photo {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
          }

          .photo-wash {
            position: absolute;
            inset: 0;
            background:
              linear-gradient(90deg, rgba(15,61,62,0.10), rgba(15,61,62,0.02)),
              linear-gradient(0deg, rgba(15,61,62,0.18), rgba(15,61,62,0));
          }

          .square .panel {
            position: absolute;
            inset: 48px auto 48px 48px;
            display: flex;
            width: 458px;
            flex-direction: column;
            justify-content: space-between;
            border: 1px solid rgba(15,61,62,0.12);
            border-radius: 8px;
            padding: 42px;
            box-shadow: 0 24px 76px rgba(15,61,62,0.20);
          }

          .square.theme-deep .panel {
            border-color: rgba(255,255,255,0.14);
          }

          .brand-row {
            display: flex;
            align-items: center;
            gap: 18px;
          }

          .brand-row img {
            width: 142px;
            height: auto;
          }

          .brand-row span {
            width: 58px;
            height: 4px;
            border-radius: 999px;
          }

          .kicker {
            margin: 0 0 22px;
            font-size: 18px;
            font-weight: 850;
            letter-spacing: 0.12em;
            text-transform: uppercase;
          }

          h1 {
            margin: 0;
            font-family: 'Source Serif 4 Local', Georgia, serif;
            font-size: 68px;
            font-weight: 700;
            line-height: 0.95;
            letter-spacing: 0;
          }

          .body {
            margin: 28px 0 0;
            font-size: 25px;
            font-weight: 650;
            line-height: 1.34;
          }

          .proof {
            display: inline-flex;
            width: fit-content;
            max-width: 100%;
            margin: 30px 0 0;
            border-radius: 8px;
            padding: 14px 18px;
            font-size: 18px;
            font-weight: 850;
            line-height: 1.18;
          }

          footer {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 20px;
          }

          .cta {
            display: inline-flex;
            min-height: 58px;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            padding: 0 24px;
            font-size: 19px;
            font-weight: 850;
            white-space: nowrap;
          }

          .contact {
            color: currentColor;
            font-size: 17px;
            font-weight: 820;
            line-height: 1.28;
            opacity: 0.76;
            text-align: right;
          }

          .portrait {
            display: grid;
            grid-template-rows: 48% 52%;
          }

          .portrait .photo-panel {
            position: relative;
            overflow: hidden;
          }

          .portrait .panel {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-top: 1px solid rgba(15,61,62,0.12);
            padding: 66px 64px 68px;
          }

          .portrait.theme-deep .panel {
            border-color: rgba(255,255,255,0.12);
          }

          .portrait .brand-row img {
            width: 156px;
          }

          .portrait .kicker {
            margin-bottom: 28px;
            font-size: 22px;
          }

          .portrait h1 {
            max-width: 9.5ch;
            font-size: 94px;
            line-height: 0.94;
          }

          .portrait .body {
            max-width: 850px;
            margin-top: 34px;
            font-size: 37px;
            line-height: 1.28;
          }

          .portrait .proof {
            margin-top: 36px;
            padding: 18px 22px;
            font-size: 25px;
          }

          .portrait .cta {
            min-height: 70px;
            padding-inline: 30px;
            font-size: 26px;
          }

          .portrait .contact {
            font-size: 24px;
          }
        </style>
      </head>
      <body>${layout}</body>
    </html>
  `;
}

async function ensureImagesLoaded(page) {
  await page.evaluate(async () => {
    await Promise.all(
      [...document.images].map((image) => {
        if (image.complete) return Promise.resolve();

        return new Promise((resolve, reject) => {
          image.addEventListener('load', resolve, { once: true });
          image.addEventListener('error', reject, { once: true });
        });
      }),
    );
  });
}

async function renderAd(browser, ad, formatName, sharedAssets) {
  const { width, height } = formats[formatName];
  const page = await browser.newPage({
    viewport: { width, height },
    deviceScaleFactor: 1,
  });
  const assets = {
    ...sharedAssets,
    image: await dataUrl(ad.image),
  };

  await page.setContent(documentHtml(ad, formatName, assets), { waitUntil: 'load' });
  await ensureImagesLoaded(page);
  await page.screenshot({
    path: path.join(outputDir, formatName, `${ad.id}-${formatName}.png`),
    clip: { x: 0, y: 0, width, height },
  });
  await page.close();
}

async function createContactSheet(browser, ads) {
  const squareImages = await Promise.all(
    ads.map(async (ad) => ({
      id: ad.id,
      data: await dataUrl(`brand/lolo/social/output/meta-ads-fresh-2026-06/square/${ad.id}-square.png`),
    })),
  );
  const portraitImages = await Promise.all(
    ads.map(async (ad) => ({
      id: ad.id,
      data: await dataUrl(`brand/lolo/social/output/meta-ads-fresh-2026-06/portrait/${ad.id}-portrait.png`),
    })),
  );
  const width = 1920;
  const height = 1720;
  const html = `
    <!doctype html>
    <html>
      <head>
        <meta charset="utf-8">
        <style>
          * { box-sizing: border-box; }
          html, body {
            width: ${width}px;
            height: ${height}px;
            margin: 0;
            background: ${brand.oat};
            font-family: Arial, sans-serif;
          }
          body {
            padding: 36px;
          }
          .label {
            margin: 0 0 18px;
            color: ${brand.deep};
            font-size: 28px;
            font-weight: 800;
          }
          .grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 18px;
            margin-bottom: 32px;
          }
          .grid img {
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 14px 36px rgba(15,61,62,0.16);
          }
          .portrait-grid img {
            aspect-ratio: 9 / 16;
            object-fit: cover;
          }
        </style>
      </head>
      <body>
        <p class="label">Fresh Meta Ads - Square Feed</p>
        <div class="grid">
          ${squareImages.map((item) => `<img src="${item.data}" alt="${escapeHtml(item.id)}">`).join('')}
        </div>
        <p class="label">Fresh Meta Ads - Stories / Reels</p>
        <div class="grid portrait-grid">
          ${portraitImages.map((item) => `<img src="${item.data}" alt="${escapeHtml(item.id)}">`).join('')}
        </div>
      </body>
    </html>
  `;
  const page = await browser.newPage({
    viewport: { width, height },
    deviceScaleFactor: 1,
  });

  await page.setContent(html, { waitUntil: 'load' });
  await ensureImagesLoaded(page);
  await page.screenshot({
    path: path.join(outputDir, 'lolo-meta-ads-fresh-2026-06-contact-sheet.png'),
    clip: { x: 0, y: 0, width, height },
  });
  await page.close();
}

async function cleanOutput() {
  for (const dir of ['square', 'portrait']) {
    const fullDir = path.join(outputDir, dir);
    await fs.mkdir(fullDir, { recursive: true });
    const files = await fs.readdir(fullDir);

    await Promise.all(
      files
        .filter((file) => file.endsWith('.png'))
        .map((file) => fs.unlink(path.join(fullDir, file))),
    );
  }

  await fs.mkdir(outputDir, { recursive: true });
}

async function main() {
  const ads = JSON.parse(await fs.readFile(adsPath, 'utf8'));
  const sharedAssets = {
    logo: await dataUrl('brand/lolo/assets/lolo-wordmark-evergreen.svg'),
    serif: await dataUrl('brand/lolo/fonts/SourceSerif4-Bold.ttf'),
    inter: await dataUrl('brand/lolo/fonts/Inter-Medium.ttf'),
  };

  await cleanOutput();

  const browser = await chromium.launch({ headless: true });

  for (const ad of ads) {
    await renderAd(browser, ad, 'square', sharedAssets);
    await renderAd(browser, ad, 'portrait', sharedAssets);
  }

  await createContactSheet(browser, ads);
  await browser.close();

  console.log(`Rendered ${ads.length * 2} fresh Meta ad files into ${outputDir}`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
