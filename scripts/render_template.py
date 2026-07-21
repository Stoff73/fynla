#!/usr/bin/env python3
"""
Fynla template renderer — overlays text onto a PNG template per template_layouts.json spec.

Usage:
    python3 render_template.py \
        --template-type square_stat_card \
        --variant v1_general.png \
        --layouts /app/config/template_layouts.json \
        --templates-dir /app/templates \
        --fields-json '{"headline":"...","subheadline":"...","cta":"..."}' \
        --output /app/storage/output/article-123/square.png

Exits 0 on success, non-zero on failure with error message on stderr.
"""

import argparse
import json
import os
import sys
from PIL import Image, ImageDraw, ImageFont


def wrap_text(text, font, max_width, max_lines=None):
    """Word-wrap text to fit max_width. Truncate with ellipsis if max_lines exceeded."""
    if not text:
        return []
    words = text.split()
    lines = []
    current = []
    for w in words:
        test = " ".join(current + [w])
        if font.getbbox(test)[2] <= max_width:
            current.append(w)
        else:
            if current:
                lines.append(" ".join(current))
            current = [w]
    if current:
        lines.append(" ".join(current))

    if max_lines and len(lines) > max_lines:
        lines = lines[:max_lines]
        last = lines[-1]
        while len(last) > 1:
            test = last + "…"
            if font.getbbox(test)[2] <= max_width:
                lines[-1] = test
                break
            last = last[:-1]
    return lines


def render(args):
    with open(args.layouts) as f:
        layouts = json.load(f)

    if args.template_type not in layouts:
        sys.exit(f"ERROR: template_type '{args.template_type}' not in layouts.json")

    variants = layouts[args.template_type]["variants"]
    variant = next((v for v in variants if v["file"] == args.variant), None)
    if not variant:
        sys.exit(f"ERROR: variant '{args.variant}' not in {args.template_type}")

    img_path = os.path.join(args.templates_dir, args.template_type, args.variant)
    if not os.path.exists(img_path):
        sys.exit(f"ERROR: template image not found: {img_path}")

    img = Image.open(img_path).convert("RGBA")
    draw = ImageDraw.Draw(img)

    font_files = layouts.get("_font_files", {})
    fields_data = json.loads(args.fields_json)

    for field in variant["fields"]:
        text = fields_data.get(field["name"], "")
        if not text:
            continue
        font_file = font_files.get(field["font"])
        if not font_file or not os.path.exists(font_file):
            # Fallback to a system font
            fallback_paths = [
                "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
                "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
            ]
            font_file = next((p for p in fallback_paths if os.path.exists(p)), None)
            if not font_file:
                sys.exit(f"ERROR: cannot find font file for {field['font']}")
            sys.stderr.write(f"WARN: font {field['font']} missing, using {font_file}\n")

        font = ImageFont.truetype(font_file, field["size"])
        wrapped = wrap_text(text, font, field["max_width"], field.get("max_lines"))
        line_height = int(field["size"] * field.get("line_spacing", 1.2))
        y = field["y"]
        for line in wrapped:
            draw.text((field["x"], y), line, font=font, fill=field["color"])
            y += line_height

    os.makedirs(os.path.dirname(args.output), exist_ok=True)
    img.save(args.output)
    print(f"OK rendered: {args.output}")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--template-type", required=True)
    parser.add_argument("--variant", required=True)
    parser.add_argument("--layouts", required=True)
    parser.add_argument("--templates-dir", required=True)
    parser.add_argument("--fields-json", required=True)
    parser.add_argument("--output", required=True)
    render(parser.parse_args())
