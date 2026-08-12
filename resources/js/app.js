import './bootstrap';
import './support-chat';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('contentEditor', (params) => ({
        ready: false,
        saveState: 'Loading editor…',
        active: () => false,
        command: () => {},
        setLink: () => {},
        insertCallout: () => {},
        insertCta: () => {},
        insertCitation: () => {},
        insertFaq: () => {},

        async init() {
            const { createContentEditorController } = await import('./blog-editor');
            Object.assign(this, createContentEditorController(params));
            this.initialize();
        },
    }));
});

const initializeBlogAnalytics = () => {
    const article = document.querySelector('[data-blog-article]');
    if (!article || article.dataset.analyticsReady === 'true') return;

    article.dataset.analyticsReady = 'true';
    const endpoint = article.dataset.eventUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const sent = new Set();
    const utmSource = new URLSearchParams(window.location.search).get('utm_source');
    const visitorStorageKey = 'lolo-content-visitor-v1';
    let visitorToken = '';
    try {
        const stored = JSON.parse(window.localStorage.getItem(visitorStorageKey) || 'null');
        if (stored?.token && stored?.expiresAt > Date.now()) visitorToken = stored.token;
        if (!visitorToken) {
            visitorToken = window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
            window.localStorage.setItem(visitorStorageKey, JSON.stringify({ token: visitorToken, expiresAt: Date.now() + 30 * 86400000 }));
        }
    } catch {
        visitorToken = window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    }
    let engagedSeconds = 0;
    let progress = 0;

    const record = (event, metadata = {}) => {
        const signature = `${event}:${metadata.placement ?? ''}`;
        if (event !== 'cta_click' && event !== 'source_click' && sent.has(signature)) return;
        sent.add(signature);
        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Content-Visitor': visitorToken, Accept: 'application/json' },
            body: JSON.stringify({ event, utm_source: utmSource, ...metadata }),
        }).catch(() => {});
    };

    record('page_view');
    const recordEngagement = () => {
        if (progress >= 0.5 && engagedSeconds >= 30) record('read_50');
        if (progress >= 0.9 && engagedSeconds >= 60) record('read_complete');
    };
    const onScroll = () => {
        const max = document.documentElement.scrollHeight - window.innerHeight;
        progress = max > 0 ? Math.max(progress, window.scrollY / max) : 1;
        recordEngagement();
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    window.setInterval(() => {
        if (document.visibilityState === 'visible') {
            engagedSeconds += 1;
            recordEngagement();
        }
    }, 1000);
    article.addEventListener('click', (event) => {
        const link = event.target.closest('[data-blog-event]');
        if (!link) return;
        record(link.dataset.blogEvent, { href: link.href, placement: link.dataset.placement ?? '' });
    });
};

document.addEventListener('DOMContentLoaded', initializeBlogAnalytics);
document.addEventListener('livewire:navigated', initializeBlogAnalytics);

const acceptedImageExtensions = new Set([
    'avif',
    'bmp',
    'gif',
    'heic',
    'heif',
    'jpeg',
    'jpg',
    'png',
    'tif',
    'tiff',
    'webp',
]);

const acceptedImageMimeTypes = new Set([
    'image/avif',
    'image/bmp',
    'image/gif',
    'image/heic',
    'image/heic-sequence',
    'image/heif',
    'image/heif-sequence',
    'image/jpeg',
    'image/jpg',
    'image/pjpeg',
    'image/png',
    'image/tiff',
    'image/webp',
    'image/x-ms-bmp',
    'image/x-tiff',
]);

const fileExtension = (file) => {
    const name = file?.name ?? '';
    const index = name.lastIndexOf('.');

    return index === -1 ? '' : name.slice(index + 1).toLowerCase();
};

const replaceExtension = (filename, extension) => {
    const base = filename && filename.includes('.')
        ? filename.slice(0, filename.lastIndexOf('.'))
        : (filename || 'profile-photo');

    return `${base}.${extension}`;
};

const isAcceptedImage = (file) => {
    const type = (file?.type ?? '').toLowerCase();
    const extension = fileExtension(file);

    return acceptedImageMimeTypes.has(type) || acceptedImageExtensions.has(extension);
};

const isHeicImage = (file) => {
    const type = (file?.type ?? '').toLowerCase();
    const extension = fileExtension(file);

    return ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'].includes(type)
        || ['heic', 'heif'].includes(extension);
};

const formatMegabytes = (bytes) => `${(bytes / (1024 * 1024)).toFixed(bytes > 10 * 1024 * 1024 ? 0 : 1)} MB`;

const canvasToBlob = (canvas, type, quality) => new Promise((resolve, reject) => {
    canvas.toBlob((blob) => {
        if (!blob) {
            reject(new Error('Unable to prepare this image.'));

            return;
        }

        resolve(blob);
    }, type, quality);
});

const decodeWithImageBitmap = async (blob) => {
    if (!('createImageBitmap' in window)) {
        return null;
    }

    try {
        const bitmap = await createImageBitmap(blob, { imageOrientation: 'from-image' });

        return {
            image: bitmap,
            width: bitmap.width,
            height: bitmap.height,
            close: () => bitmap.close?.(),
        };
    } catch {
        return null;
    }
};

const decodeWithImageElement = (blob) => new Promise((resolve, reject) => {
    const url = URL.createObjectURL(blob);
    const image = new Image();

    image.onload = () => {
        URL.revokeObjectURL(url);
        resolve({
            image,
            width: image.naturalWidth,
            height: image.naturalHeight,
            close: () => {
                image.src = '';
            },
        });
    };

    image.onerror = () => {
        URL.revokeObjectURL(url);
        reject(new Error('Unable to read this image in your browser.'));
    };

    image.src = url;
});

const decodeImage = async (blob) => (await decodeWithImageBitmap(blob)) ?? decodeWithImageElement(blob);

const convertHeicToJpeg = async (file, quality) => {
    const { default: heic2any } = await import('heic2any');
    const converted = await heic2any({
        blob: file,
        toType: 'image/jpeg',
        quality,
    });

    const blob = Array.isArray(converted) ? converted[0] : converted;

    return new File([blob], replaceExtension(file.name, 'jpg'), {
        type: 'image/jpeg',
        lastModified: Date.now(),
    });
};

const resizeImage = async (file, maxDimension, quality) => {
    let source = file;

    if (isHeicImage(file)) {
        source = await convertHeicToJpeg(file, quality);
    }

    const decoded = await decodeImage(source);

    if (!decoded.width || !decoded.height) {
        decoded.close();
        throw new Error('Unable to read this image size.');
    }

    const scale = Math.min(1, maxDimension / Math.max(decoded.width, decoded.height));
    const width = Math.max(1, Math.round(decoded.width * scale));
    const height = Math.max(1, Math.round(decoded.height * scale));
    const canvas = document.createElement('canvas');

    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d', { alpha: false });

    if (!context) {
        decoded.close();
        throw new Error('Unable to prepare this image.');
    }

    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, width, height);
    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';
    context.drawImage(decoded.image, 0, 0, width, height);
    decoded.close();

    const blob = await canvasToBlob(canvas, 'image/jpeg', quality);

    return new File([blob], replaceExtension(file.name, 'jpg'), {
        type: 'image/jpeg',
        lastModified: Date.now(),
    });
};

document.addEventListener('alpine:init', () => {
    window.Alpine.data('caregiverProfilePhotoUpload', ({
        initialUrl = null,
        maxDimension = 1600,
        maxUploadMegabytes = 64,
        property = 'profile_photo',
        quality = 0.86,
    } = {}) => ({
        busy: false,
        error: '',
        previewUrl: initialUrl,
        progress: 0,
        property,
        status: '',
        uploadedName: '',

        async select(event) {
            const [file] = Array.from(event.target.files ?? []);

            this.error = '';
            this.progress = 0;

            if (!file) {
                return;
            }

            if (!isAcceptedImage(file)) {
                this.error = 'Use JPG, PNG, WEBP, HEIC, HEIF, AVIF, GIF, BMP, or TIFF.';
                event.target.value = '';

                return;
            }

            const maxBytes = maxUploadMegabytes * 1024 * 1024;

            if (file.size > maxBytes) {
                this.error = `This photo is ${formatMegabytes(file.size)}. Choose one under ${maxUploadMegabytes} MB.`;
                event.target.value = '';

                return;
            }

            this.busy = true;
            this.status = 'Preparing photo...';
            this.uploadedName = file.name;

            let uploadFile = file;

            try {
                uploadFile = await resizeImage(file, maxDimension, quality);
                this.status = 'Uploading optimized photo...';
            } catch {
                uploadFile = file;
                this.status = 'Uploading original photo...';
            }

            this.upload(uploadFile);
        },

        upload(file) {
            this.$wire.upload(
                this.property,
                file,
                () => {
                    this.busy = false;
                    this.progress = 100;
                    this.status = 'Photo ready.';
                    this.error = '';
                    this.uploadedName = file.name;
                    this.replacePreview(file);
                    this.$refs.file.value = '';
                },
                () => {
                    this.busy = false;
                    this.progress = 0;
                    this.status = '';
                    this.error = 'The photo upload failed. Try again with a JPG export if this keeps happening.';
                    this.$refs.file.value = '';
                },
                (event) => {
                    this.progress = Math.max(1, event.detail.progress ?? 0);
                },
            );
        },

        replacePreview(file) {
            if (this.previewUrl?.startsWith('blob:')) {
                URL.revokeObjectURL(this.previewUrl);
            }

            this.previewUrl = URL.createObjectURL(file);
        },
    }));
});
