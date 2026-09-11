# Locally served LoLo fonts

Inter and Source Serif 4 are the existing app typefaces. These normal-style variable fonts retain all upstream glyphs and weight/optical-size axes. They are packaged as WOFF with fontTools without subsetting; the original font names and license files are preserved.

Sources retrieved September 8, 2026:

- [Inter, Google Fonts](https://github.com/google/fonts/tree/main/ofl/inter), `Inter[opsz,wght].ttf`; license: `Inter-OFL.txt`.
- [Source Serif 4, Google Fonts](https://github.com/google/fonts/tree/main/ofl/sourceserif4), `SourceSerif4[opsz,wght].ttf`; license: `SourceSerif4-OFL.txt`.

The app declares these in `resources/css/app.css`. Local font files avoid substituting fallback fonts when the app or screenshot browser cannot reach Google Fonts. Keep the corresponding OFL notices with the distributed fonts.
