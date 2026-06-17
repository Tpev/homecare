import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const root = path.resolve(__dirname, '../../..');
const adsPath = path.join(__dirname, 'meta-ads-home-care-older-adults-2026-06.json');
const outputDir = path.join(__dirname, 'output', 'meta-ads-home-care-older-adults-2026-06');

const brand = {
  deep: '#0F3D3E',
  ink: '#24302D',
  cream: '#F5F1EB',
  ivory: '#FAF9F7',
  oat: '#F1E5D2',
  blue: '#4F6FAF',
  lavender: '#7C5DDC',
  coral: '#C96B55',
  cta: '#B95745',
};

const contact = {
  domain: 'carelolo.com/get-care',
  phone: '(984) 400-4008',
};

const formats = {
  square: { width: 1080, height: 1080 },
  portrait: { width: 1080, height: 1350 },
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
      text: '#FFFFFF',
      body: 'rgba(255,255,255,0.78)',
      kicker: brand.oat,
      logo: 'brightness(0) invert(1)',
      chipBg: 'rgba(255,255,255,0.12)',
      chipText: '#FFFFFF',
      ctaBg: brand.ivory,
      ctaText: brand.deep,
      accent: brand.coral,
    };
  }

  if (theme === 'cream') {
    return {
      panel: 'rgba(245,241,235,0.96)',
      text: brand.deep,
      body: 'rgba(36,48,45,0.74)',
      kicker: brand.cta,
      logo: 'none',
      chipBg: 'rgba(79,111,175,0.12)',
      chipText: brand.blue,
      ctaBg: brand.deep,
      ctaText: '#FFFFFF',
      accent: brand.lavender,
    };
  }

  return {
    panel: 'rgba(250,249,247,0.96)',
    text: brand.deep,
    body: 'rgba(36,48,45,0.74)',
    kicker: brand.cta,
    logo: 'none',
    chipBg: 'rgba(201,107,85,0.12)',
    chipText: brand.cta,
    ctaBg: brand.deep,
    ctaText: '#FFFFFF',
    accent: brand.blue,
  };
}

function markup(ad, assets, formatName) {
  const vars = themeVars(ad.theme);
  const isPortrait = formatName === 'portrait';

  return `
    <section class="ad ${formatName} theme-${escapeHtml(ad.theme)}">
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
        .panel { background: ${vars.panel}; color: ${vars.text}; }
        .brand-row img { filter: ${vars.logo}; }
        .brand-row span { background: ${vars.accent}; }
        h1 { color: ${vars.text}; }
        .kicker { color: ${vars.kicker}; }
        .body { color: ${vars.body}; }
        .proof { background: ${vars.chipBg}; color: ${vars.chipText}; }
        .cta { background: ${vars.ctaBg}; color: ${vars.ctaText}; }
        .photo { object-position: ${isPortrait ? 'center 42%' : 'center'}; }
      </style>
    </section>
  `;
}

function documentHtml(ad, formatName, assets) {
  const { width, height } = formats[formatName];
  const isPortrait = formatName === 'portrait';

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
          }

          .photo-wash {
            position: absolute;
            inset: 0;
            background:
              linear-gradient(90deg, rgba(15,61,62,0.08), rgba(15,61,62,0.02)),
              linear-gradient(0deg, rgba(15,61,62,0.20), rgba(15,61,62,0));
          }

          .panel {
            position: absolute;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border: 1px solid rgba(15,61,62,0.12);
            border-radius: 8px;
            box-shadow: 0 24px 76px rgba(15,61,62,0.22);
          }

          .theme-deep .panel {
            border-color: rgba(255,255,255,0.14);
          }

          .square .panel {
            inset: 48px 48px 48px auto;
            width: 520px;
            padding: 42px;
          }

          .portrait .panel {
            inset: auto 50px 54px 50px;
            min-height: 640px;
            padding: 44px;
          }

          .brand-row {
            display: flex;
            align-items: center;
            gap: 18px;
          }

          .brand-row img {
            width: ${isPortrait ? '150px' : '142px'};
            height: auto;
          }

          .brand-row span {
            width: 56px;
            height: 4px;
            border-radius: 999px;
          }

          .kicker {
            margin: 0 0 ${isPortrait ? '20px' : '18px'};
            font-size: ${isPortrait ? '20px' : '18px'};
            font-weight: 850;
            letter-spacing: 0.12em;
            line-height: 1.18;
            text-transform: uppercase;
          }

          h1 {
            max-width: ${isPortrait ? '9.6ch' : '9.5ch'};
            margin: 0;
            font-family: 'Source Serif 4 Local', Georgia, serif;
            font-size: ${isPortrait ? '76px' : '64px'};
            font-weight: 700;
            line-height: 0.95;
            letter-spacing: 0;
          }

          .body {
            max-width: 780px;
            margin: ${isPortrait ? '26px' : '24px'} 0 0;
            font-size: ${isPortrait ? '27px' : '24px'};
            font-weight: 650;
            line-height: 1.28;
          }

          .proof {
            display: inline-flex;
            width: fit-content;
            max-width: 100%;
            margin: ${isPortrait ? '26px' : '26px'} 0 0;
            border-radius: 8px;
            padding: ${isPortrait ? '14px 18px' : '13px 17px'};
            font-size: ${isPortrait ? '20px' : '18px'};
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
            min-height: ${isPortrait ? '62px' : '56px'};
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            padding: 0 ${isPortrait ? '24px' : '22px'};
            font-size: ${isPortrait ? '22px' : '19px'};
            font-weight: 850;
            white-space: nowrap;
          }

          .contact {
            color: currentColor;
            font-size: ${isPortrait ? '20px' : '17px'};
            font-weight: 820;
            line-height: 1.28;
            opacity: 0.76;
            text-align: right;
          }
        </style>
      </head>
      <body>${markup(ad, assets, formatName)}</body>
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

async function cleanOutput() {
  await fs.mkdir(outputDir, { recursive: true });

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
  const width = 1800;
  const height = 1580;
  const squareImages = await Promise.all(
    ads.map(async (ad) => ({
      id: ad.id,
      data: await dataUrl(`brand/lolo/social/output/meta-ads-home-care-older-adults-2026-06/square/${ad.id}-square.png`),
    })),
  );
  const portraitImages = await Promise.all(
    ads.map(async (ad) => ({
      id: ad.id,
      data: await dataUrl(`brand/lolo/social/output/meta-ads-home-care-older-adults-2026-06/portrait/${ad.id}-portrait.png`),
    })),
  );
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
          body { padding: 34px; }
          p {
            margin: 0 0 16px;
            color: ${brand.deep};
            font-size: 27px;
            font-weight: 800;
          }
          .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 30px;
          }
          img {
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 14px 36px rgba(15,61,62,0.16);
          }
          .portrait img {
            aspect-ratio: 4 / 5;
            object-fit: cover;
          }
        </style>
      </head>
      <body>
        <p>Meta Creative Set - 4:5 Feed</p>
        <div class="grid portrait">
          ${portraitImages.map((item) => `<img src="${item.data}" alt="${escapeHtml(item.id)}">`).join('')}
        </div>
        <p>Meta Creative Set - 1:1 Square</p>
        <div class="grid">
          ${squareImages.map((item) => `<img src="${item.data}" alt="${escapeHtml(item.id)}">`).join('')}
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
    path: path.join(outputDir, 'lolo-meta-home-care-older-adults-contact-sheet.png'),
    clip: { x: 0, y: 0, width, height },
  });
  await page.close();
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
    await renderAd(browser, ad, 'portrait', sharedAssets);
    await renderAd(browser, ad, 'square', sharedAssets);
  }

  await createContactSheet(browser, ads);
  await browser.close();

  console.log(`Rendered ${ads.length * 2} Meta ad files into ${outputDir}`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
