# Templates folder

Drop your Canva-exported PNGs into the subfolders below.

## Structure

```
templates/
├── square_stat_card/
│   ├── v1.png   ← 1080 × 1080
│   ├── v2.png
│   └── v3.png
├── story_card/
│   ├── v1.png   ← 1080 × 1920
│   └── v2.png
└── youtube_thumbnail/
    ├── v1.png   ← 1280 × 720
    └── v2.png
```

## Critical rule

**No placeholder text on the exported PNGs.** The Pillow renderer overlays
article-specific text at runtime. Where text will appear, leave deliberate
empty space.

## Naming

Filenames must be `v1.png`, `v2.png`, `v3.png` etc. The pipeline finds them
via that naming convention.

## After uploading

Tell Claude when files are in place — it'll inspect each one and generate
`config/template_layouts.json` mapping text fields to coordinates per variant.
