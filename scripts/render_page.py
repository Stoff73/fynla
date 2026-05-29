#!/usr/bin/env python3
"""
Render a JS-heavy SPA page using Playwright (headless Chromium) and dump the
fully-rendered HTML.

Usage:
    python3 render_page.py <url> [--wait-selector="article"] [--wait-ms=3000]

Outputs the rendered HTML to stdout.
Exits 0 on success, non-zero with error on stderr.
"""
import sys
import argparse
from playwright.sync_api import sync_playwright


def render(url: str, wait_selector: str | None = None, wait_ms: int = 3000) -> str:
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True, args=["--no-sandbox"])
        context = browser.new_context(
            user_agent="Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            viewport={"width": 1280, "height": 1024},
            locale="en-GB",
        )
        page = context.new_page()
        page.goto(url, wait_until="networkidle", timeout=30000)

        if wait_selector:
            try:
                page.wait_for_selector(wait_selector, timeout=15000)
            except Exception as e:
                sys.stderr.write(f"WARN: selector '{wait_selector}' didn't appear: {e}\n")

        # Wait extra for any post-load content
        page.wait_for_timeout(wait_ms)

        html = page.content()
        browser.close()
        return html


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("url")
    parser.add_argument("--wait-selector", default=None,
                        help="CSS selector to wait for before extracting (e.g. 'article')")
    parser.add_argument("--wait-ms", type=int, default=3000,
                        help="Additional ms to wait after networkidle")
    args = parser.parse_args()

    try:
        html = render(args.url, args.wait_selector, args.wait_ms)
        print(html)
    except Exception as e:
        sys.stderr.write(f"ERROR: {e}\n")
        sys.exit(1)
