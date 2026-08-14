import { expect, type Locator } from '@playwright/test';

type Rgb = [number, number, number];

export async function expectMinimumTextContrast(
    locator: Locator,
    backgroundOverride?: Rgb,
    minimum = 4.5,
): Promise<void> {
    const ratio = await locator.evaluate((element, options) => {
        const parseComputedRgb = (value: string): Rgb => {
            const channels = value.match(/[\d.]+/g)?.slice(0, 3).map(Number);
            if (! channels || channels.length !== 3) {
                throw new Error(`Unsupported computed color: ${value}`);
            }

            return channels as Rgb;
        };
        const luminance = (rgb: Rgb): number => {
            const [red, green, blue] = rgb.map((channel) => {
                const normalized = channel / 255;

                return normalized <= 0.04045
                    ? normalized / 12.92
                    : Math.pow((normalized + 0.055) / 1.055, 2.4);
            });

            return (0.2126 * red) + (0.7152 * green) + (0.0722 * blue);
        };

        const style = getComputedStyle(element);
        const foreground = parseComputedRgb(style.color);
        const background = options.backgroundOverride ?? parseComputedRgb(style.backgroundColor);
        const foregroundLuminance = luminance(foreground);
        const backgroundLuminance = luminance(background);
        const lighter = Math.max(foregroundLuminance, backgroundLuminance);
        const darker = Math.min(foregroundLuminance, backgroundLuminance);

        return (lighter + 0.05) / (darker + 0.05);
    }, { backgroundOverride });

    expect(ratio).toBeGreaterThanOrEqual(minimum);
}
