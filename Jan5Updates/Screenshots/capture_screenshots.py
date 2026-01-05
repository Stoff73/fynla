#!/usr/bin/env python3
"""
Fynla Demo Screenshot Capture Script v4
Captures all tabs with correct tab names.
"""

import asyncio
from playwright.async_api import async_playwright
import os

BASE_URL = "https://fynla.org"
SCREENSHOT_DIR = "/Users/Chris/Desktop/fpsApp/fynla/Jan5Updates/Screenshots"

PERSONAS = [
    {"name": "young_family", "display": "Emily & James Carter", "folder": "Carter"},
    {"name": "peak_earners", "display": "David & Sarah Mitchell", "folder": "Mitchell"},
    {"name": "widow", "display": "Margaret Thompson", "folder": "Thompson"},
    {"name": "entrepreneur", "display": "Alex Chen", "folder": "Chen"},
]

async def wait_for_page_ready(page, timeout=10000):
    """Wait for page to be fully loaded."""
    try:
        await page.wait_for_load_state("networkidle", timeout=timeout)
    except:
        pass
    await asyncio.sleep(1.5)

async def take_screenshot(page, folder, name):
    """Take a screenshot and save it to the specified folder."""
    filepath = os.path.join(SCREENSHOT_DIR, folder, f"{name}.png")
    await page.screenshot(path=filepath, full_page=False)
    print(f"    ✓ {name}.png")

async def click_element(page, selector, timeout=3000):
    """Try to click an element, return True if successful."""
    try:
        element = page.locator(selector).first
        if await element.count() > 0:
            await element.click(timeout=timeout)
            await asyncio.sleep(1)
            return True
    except:
        pass
    return False

async def click_tab(page, tab_text):
    """Try multiple selectors to click a tab."""
    selectors = [
        f"button:has-text('{tab_text}')",
        f"[role='tab']:has-text('{tab_text}')",
        f"a:has-text('{tab_text}')",
        f"span:has-text('{tab_text}')",
        f"div.tab:has-text('{tab_text}')",
        f"text='{tab_text}'",
    ]
    for selector in selectors:
        if await click_element(page, selector):
            return True
    return False

async def login_as_persona(page, persona):
    """Login as a specific persona via the demo modal."""
    await page.goto(BASE_URL)
    await wait_for_page_ready(page)

    try:
        demo_btn = page.locator("text='Try the Demo'").first
        await demo_btn.click(timeout=5000)
        await asyncio.sleep(1.5)
    except Exception as e:
        print(f"  Could not click 'Try the Demo': {e}")
        return False

    try:
        persona_btn = page.locator(f"text='{persona['display']}'").first
        await persona_btn.click(timeout=5000)
        await wait_for_page_ready(page)
        return True
    except Exception as e:
        print(f"  Could not select persona: {e}")
        return False

async def capture_persona_screenshots(page, persona):
    """Capture all screenshots for a single persona."""
    folder = persona["folder"]
    print(f"\n{'='*60}")
    print(f"📸 {persona['display']}")
    print(f"{'='*60}")

    os.makedirs(os.path.join(SCREENSHOT_DIR, folder), exist_ok=True)

    if not await login_as_persona(page, persona):
        print("  Failed to login, skipping persona")
        return

    # 1. DASHBOARD
    print("\n  [Dashboard]")
    await page.goto(f"{BASE_URL}/dashboard")
    await wait_for_page_ready(page)
    await take_screenshot(page, folder, "01_dashboard")

    # 2. NET WORTH MODULE
    print("\n  [Net Worth]")
    if await click_element(page, "text='Net Worth'"):
        await wait_for_page_ready(page)
    else:
        await page.goto(f"{BASE_URL}/net-worth")
        await wait_for_page_ready(page)

    await take_screenshot(page, folder, "02_networth_overview")

    # Net Worth tabs
    networth_tabs = [
        ("Retirement", "retirement"),
        ("Property", "property"),
        ("Investments", "investments"),
        ("Cash", "cash"),
        ("Business", "business"),
        ("Chattels", "chattels"),
    ]

    for tab_name, suffix in networth_tabs:
        if await click_tab(page, tab_name):
            await asyncio.sleep(1)
            await take_screenshot(page, folder, f"03_networth_{suffix}")

            # Property sub-tabs
            if tab_name == "Property":
                property_subtabs = [("Overview", "overview"), ("Mortgage", "mortgage"),
                                    ("Financials", "financials"), ("Taxes", "taxes")]
                for subtab, subname in property_subtabs:
                    if await click_tab(page, subtab):
                        await asyncio.sleep(0.5)
                        await take_screenshot(page, folder, f"04_property_{subname}")

            # Retirement sub-tabs
            if tab_name == "Retirement":
                ret_subtabs = [("Pensions", "pensions"), ("Future Value", "futurevalue"),
                               ("Strategies", "strategies"), ("Retirement Income", "income")]
                for subtab, subname in ret_subtabs:
                    if await click_tab(page, subtab):
                        await asyncio.sleep(0.5)
                        await take_screenshot(page, folder, f"05_retirement_{subname}")

            # Investment sub-tabs
            if tab_name == "Investments":
                inv_subtabs = [("Holdings", "holdings"), ("Strategy", "strategy")]
                for subtab, subname in inv_subtabs:
                    if await click_tab(page, subtab):
                        await asyncio.sleep(0.5)
                        await take_screenshot(page, folder, f"06_investments_{subname}")

    # 3. PROTECTION MODULE
    print("\n  [Protection]")
    await page.goto(f"{BASE_URL}/protection")
    await wait_for_page_ready(page)
    await take_screenshot(page, folder, "10_protection_overview")

    # Protection tabs - correct names
    protection_tabs = [
        ("Policy Overview", "policies"),
        ("Gap Analysis", "gap"),
        ("Strategy", "strategy")
    ]
    for tab_name, suffix in protection_tabs:
        if await click_tab(page, tab_name):
            await asyncio.sleep(1)
            await take_screenshot(page, folder, f"11_protection_{suffix}")

    # 4. ESTATE PLANNING MODULE
    print("\n  [Estate Planning]")
    await page.goto(f"{BASE_URL}/estate")
    await wait_for_page_ready(page)
    await take_screenshot(page, folder, "20_estate_overview")

    # Estate tabs - CORRECT names from UI
    estate_tabs = [
        ("IHT Planning", "iht"),
        ("Gifting Strategy", "gifting"),
        ("Life Policy Strategy", "life_policy"),
        ("Trust Strategy", "trust")
    ]
    for tab_name, suffix in estate_tabs:
        if await click_tab(page, tab_name):
            await asyncio.sleep(1)
            await take_screenshot(page, folder, f"21_estate_{suffix}")

    # 5. INVESTMENT & SAVINGS MODULE
    print("\n  [Investment & Savings]")
    await page.goto(f"{BASE_URL}/dashboard")
    await wait_for_page_ready(page)

    if await click_element(page, "text='Investment & Savings Plan'"):
        await wait_for_page_ready(page)
        await take_screenshot(page, folder, "30_investment_dashboard")

    print(f"\n  ✓ Completed {persona['display']}")

async def main():
    """Main function to capture all screenshots."""
    print("\n" + "="*60)
    print("   FYNLA DEMO SCREENSHOT CAPTURE v4")
    print("="*60)

    async with async_playwright() as p:
        browser = await p.chromium.launch(
            headless=False,
            slow_mo=100
        )
        context = await browser.new_context(
            viewport={"width": 1400, "height": 900}
        )
        page = await context.new_page()

        for persona in PERSONAS:
            try:
                await capture_persona_screenshots(page, persona)
            except Exception as e:
                print(f"  ❌ Error: {e}")

        await browser.close()

    print("\n" + "="*60)
    print("   SCREENSHOT CAPTURE COMPLETE")
    print(f"   Saved to: {SCREENSHOT_DIR}")
    print("="*60 + "\n")

if __name__ == "__main__":
    asyncio.run(main())
