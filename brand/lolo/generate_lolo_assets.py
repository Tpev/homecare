from pathlib import Path
from xml.sax.saxutils import escape

from fontTools.pens.boundsPen import BoundsPen
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont


ROOT = Path(__file__).resolve().parent
FONT_DIR = ROOT / "fonts"
OUT_DIR = ROOT / "assets"

WORDMARK_FONT = FONT_DIR / "LibreBaskerville-Bold.ttf"
INTER_MEDIUM = FONT_DIR / "Inter-Medium.ttf"

EVERGREEN = "#23483F"
IVORY = "#FFF7EA"
CORAL = "#C96B55"
DEEP_INK = "#24302D"


class OutlineFont:
    def __init__(self, path: Path):
        self.font = TTFont(path)
        self.glyph_set = self.font.getGlyphSet()
        self.cmap = self.font.getBestCmap()
        self.units_per_em = self.font["head"].unitsPerEm
        self.hmtx = self.font["hmtx"].metrics

    def _glyph_name(self, char: str) -> str:
        glyph_name = self.cmap.get(ord(char))
        if glyph_name is None:
            raise ValueError(f"Character {char!r} not found in font")
        return glyph_name

    def outline(self, text: str, size: float, tracking: float = 0) -> tuple[str, tuple[float, float, float, float]]:
        scale = size / self.units_per_em
        cursor = 0.0
        path_parts: list[str] = []
        bounds: list[tuple[float, float, float, float]] = []

        for index, char in enumerate(text):
            if char == " ":
                cursor += self.hmtx[self._glyph_name("n")][0] * scale * 0.55
                continue

            glyph_name = self._glyph_name(char)
            glyph = self.glyph_set[glyph_name]

            pen = SVGPathPen(self.glyph_set)
            transform_pen = TransformPen(pen, (scale, 0, 0, -scale, cursor, 0))
            glyph.draw(transform_pen)
            path_parts.append(pen.getCommands())

            bounds_pen = BoundsPen(self.glyph_set)
            glyph.draw(bounds_pen)
            glyph_bounds = bounds_pen.bounds
            if glyph_bounds:
                x_min, y_min, x_max, y_max = glyph_bounds
                bounds.append(
                    (
                        cursor + x_min * scale,
                        -y_max * scale,
                        cursor + x_max * scale,
                        -y_min * scale,
                    )
                )

            advance = self.hmtx[glyph_name][0] * scale
            cursor += advance
            if index < len(text) - 1:
                cursor += tracking

        if not bounds:
            return "", (0, 0, 0, 0)

        x0 = min(b[0] for b in bounds)
        y0 = min(b[1] for b in bounds)
        x1 = max(b[2] for b in bounds)
        y1 = max(b[3] for b in bounds)
        return " ".join(path_parts), (x0, y0, x1, y1)


def svg_doc(width: float, height: float, body: str) -> str:
    return f"""<svg xmlns="http://www.w3.org/2000/svg" width="{width:.0f}" height="{height:.0f}" viewBox="0 0 {width:.2f} {height:.2f}" role="img" aria-label="LoLo logo">
  {body}
</svg>
"""


def write(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")


def path_el(d: str, fill: str, tx: float, ty: float) -> str:
    return f'<path d="{escape(d)}" fill="{fill}" transform="translate({tx:.2f} {ty:.2f})"/>'


def wordmark_svg(
    name: str,
    fill: str = EVERGREEN,
    underline: bool = False,
    descriptor: bool = False,
) -> None:
    serif = OutlineFont(WORDMARK_FONT)
    sans = OutlineFont(INTER_MEDIUM)

    word_d, word_box = serif.outline("LoLo", size=220, tracking=-5)
    wx0, wy0, wx1, wy1 = word_box
    word_width = wx1 - wx0
    word_height = wy1 - wy0

    pad_x = 30
    pad_top = 26
    gap_after_word = 24 if descriptor or underline else 0
    underline_h = 9 if underline else 0
    underline_gap = 14 if underline else 0
    descriptor_gap = 26 if descriptor else 0

    desc_d = ""
    desc_box = (0, 0, 0, 0)
    desc_width = 0
    desc_height = 0
    if descriptor:
        desc_d, desc_box = sans.outline("Home care and companionship", size=26, tracking=0.2)
        dx0, dy0, dx1, dy1 = desc_box
        desc_width = dx1 - dx0
        desc_height = dy1 - dy0

    content_width = max(word_width, desc_width)
    width = content_width + pad_x * 2
    height = pad_top + word_height
    if underline:
        height += underline_gap + underline_h
    if descriptor:
        height += descriptor_gap + desc_height
    height += 24

    word_tx = pad_x + (content_width - word_width) / 2 - wx0
    word_ty = pad_top - wy0
    elements = [path_el(word_d, fill, word_tx, word_ty)]

    baseline_y = pad_top + word_height
    if underline:
        u_w = word_width * 0.74
        u_x = pad_x + (content_width - u_w) / 2
        u_y = baseline_y + underline_gap
        radius = underline_h / 2
        elements.append(
            f'<rect x="{u_x:.2f}" y="{u_y:.2f}" width="{u_w:.2f}" height="{underline_h:.2f}" rx="{radius:.2f}" fill="{CORAL}"/>'
        )
        baseline_y += underline_gap + underline_h

    if descriptor:
        dx0, dy0, _dx1, _dy1 = desc_box
        desc_tx = pad_x + (content_width - desc_width) / 2 - dx0
        desc_ty = baseline_y + descriptor_gap - dy0
        elements.append(path_el(desc_d, fill, desc_tx, desc_ty))

    write(OUT_DIR / name, svg_doc(width, height, "\n  ".join(elements)))


def app_icon_svg() -> None:
    serif = OutlineFont(WORDMARK_FONT)
    word_d, word_box = serif.outline("LoLo", size=150, tracking=-4)
    wx0, wy0, wx1, wy1 = word_box
    word_width = wx1 - wx0
    word_height = wy1 - wy0

    size = 512
    tx = (size - word_width) / 2 - wx0
    ty = (size - word_height) / 2 - wy0 - 3
    body = f"""
  <rect x="0" y="0" width="{size}" height="{size}" rx="104" fill="{EVERGREEN}"/>
  {path_el(word_d, IVORY, tx, ty)}
"""
    write(OUT_DIR / "lolo-app-icon.svg", svg_doc(size, size, body))


def simple_color_readme() -> None:
    readme = """# LoLo Logo Assets

Generated from outlined font paths for reliable SVG use.

Colors:
- Warm Evergreen: #23483F
- Soft Ivory: #FFF7EA
- Clay Coral: #C96B55
- Deep Ink: #24302D

Logo usage:
- `lolo-wordmark-evergreen.svg`: primary wordmark.
- `lolo-wordmark-warm.svg`: secondary marketing wordmark with coral underline.
- `lolo-lockup-evergreen.svg`: wordmark with descriptor.
- `lolo-lockup-warm.svg`: descriptor lockup with coral underline.
- `lolo-wordmark-ivory.svg`: reversed wordmark for dark backgrounds.
- `lolo-app-icon.svg`: square app/social icon.
"""
    write(ROOT / "README.md", readme)


def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    wordmark_svg("lolo-wordmark-evergreen.svg", fill=EVERGREEN)
    wordmark_svg("lolo-wordmark-ivory.svg", fill=IVORY)
    wordmark_svg("lolo-wordmark-deep-ink.svg", fill=DEEP_INK)
    wordmark_svg("lolo-wordmark-warm.svg", fill=EVERGREEN, underline=True)
    wordmark_svg("lolo-lockup-evergreen.svg", fill=EVERGREEN, descriptor=True)
    wordmark_svg("lolo-lockup-warm.svg", fill=EVERGREEN, underline=True, descriptor=True)
    app_icon_svg()
    simple_color_readme()


if __name__ == "__main__":
    main()
