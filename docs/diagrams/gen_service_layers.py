#!/usr/bin/env python3
"""Generate the Fynla service-layers architecture diagram as draw.io XML.

Layered (n-tier) view of the repo: a vertical request-flow spine
(Client -> Frontend -> HTTP/API -> Agents -> Services -> Models -> DB)
with a cross-cutting Support column to the right. Counts and module names
are taken from the live repo; colours from the Fynla palette (tailwind.config.js).
"""
import os
from math import ceil
from xml.sax.saxutils import escape

# ---- Fynla palette ---------------------------------------------------------
PAL = {
    "raspberry": "#E83E6D", "raspberry_d": "#BE185D", "raspberry_l": "#FCE7F3", "raspberry_t": "#831843",
    "horizon":   "#1F2A44", "horizon_d": "#0F172A", "horizon_200": "#E2E8F0", "horizon_100": "#F1F5F9",
    "spring":    "#20B486", "spring_d": "#047857", "spring_l": "#D1FAE5", "spring_t": "#065F46",
    "violet":    "#5854E6", "violet_l": "#EDE9FE", "violet_50": "#F5F3FF", "violet_t": "#4C1D5F", "violet_a": "#A78BFA",
    "savannah":  "#E6C9A8", "savannah_d": "#A88E6E", "savannah_l": "#FAF5F0", "savannah_100": "#FDFAF7", "savannah_t": "#6B5845",
    "lightblue": "#6C83BC", "neutral": "#717171", "white": "#FFFFFF",
}

# ---- geometry --------------------------------------------------------------
SPINE_X = 40
SPINE_W = 1140
BAND_GAP = 48
TITLE_H = 36
BOX_H = 38
GAP_X = 14
GAP_Y = 14
PAD_X = 20
SIDE_X = 1220
SIDE_W = 260
TOP_Y = 96  # below the diagram title

cells = []   # (id, value, style, x, y, w, h, parent, vertex)
edges = []   # (id, value, style, source, target)
_uid = [1]

def nid():
    _uid[0] += 1
    return f"c{_uid[0]}"

def add(value, style, x, y, w, h, parent="1"):
    cid = nid()
    cells.append((cid, value, style, x, y, w, h, parent, True))
    return cid

def box_style(fill, stroke, font, dashed=False, fontsize=11):
    d = "dashed=1;" if dashed else ""
    return (f"rounded=1;whiteSpace=wrap;html=1;{d}fillColor={fill};strokeColor={stroke};"
            f"fontColor={font};fontSize={fontsize};spacingTop=2;")

def swimlane_style(header, body, stroke, dashed=False):
    d = "dashed=1;" if dashed else ""
    return (f"swimlane;startSize={TITLE_H};html=1;{d}fillColor={header};fontColor={PAL['white']};"
            f"swimlaneFillColor={body};strokeColor={stroke};fontStyle=1;fontSize=13;"
            f"verticalAlign=middle;align=left;spacingLeft=14;")

def layout_rows(labels, n_per_row, inner_w, top=52, box_h=BOX_H):
    rows = ceil(len(labels) / n_per_row)
    box_w = (inner_w - 2 * PAD_X - (n_per_row - 1) * GAP_X) / n_per_row
    placed = []
    for i, lab in enumerate(labels):
        r, c = divmod(i, n_per_row)
        x = PAD_X + c * (box_w + GAP_X)
        y = top + r * (box_h + GAP_Y)
        placed.append((lab, x, y, box_w, box_h))
    band_h = top + rows * box_h + (rows - 1) * GAP_Y + 16
    return placed, box_w, band_h

# ---- title -----------------------------------------------------------------
add("Fynla &#8212; Service Layers",
    "text;html=1;fontSize=24;fontStyle=1;align=left;verticalAlign=middle;fontColor=" + PAL["horizon"] + ";",
    SPINE_X, 20, 760, 30)
add("Request flow: Vue Component &#8594; API Service &#8594; Controller &#8594; Agent &#8594; Services &#8594; Models &#8594; DB",
    "text;html=1;fontSize=13;align=left;verticalAlign=middle;fontColor=" + PAL["neutral"] + ";",
    SPINE_X, 52, 1000, 24)

# ---- spine band definitions ------------------------------------------------
y = TOP_Y
band_ids = {}
band_bounds = {}  # key -> (y, h)

def emit_band(key, title, labels, n_per_row, header, body, stroke, font, box_fill, box_stroke):
    global y
    placed, _, band_h = layout_rows(labels, n_per_row, SPINE_W)
    bid = add(title, swimlane_style(header, body, stroke), SPINE_X, y, SPINE_W, band_h)
    band_ids[key] = bid
    band_bounds[key] = (y, band_h)
    for lab, bx, by, bw, bh in placed:
        add(lab, box_style(box_fill, box_stroke, font), bx, by, bw, bh, parent=bid)
    y += band_h + BAND_GAP
    return bid

# CLIENT
emit_band("client", "CLIENT &#183; browser &amp; device",
          ["Web SPA<br><font style='font-size:9px'>resources/js</font>",
           "Mobile /m SPA<br><font style='font-size:9px'>resources/mobile</font>"],
          2, PAL["savannah_d"], PAL["savannah_l"], PAL["savannah_d"], PAL["savannah_t"],
          PAL["savannah_l"], PAL["savannah_d"])

# FRONTEND
emit_band("frontend", "FRONTEND &#183; presentation &#183; resources/js + resources/mobile",
          ["Views &#215;33", "Components &#215;488", "Vuex Stores &#215;35",
           "API Services &#215;56", "Router + Guards", "Mixins &#183; Utils &#183; Directives"],
          3, PAL["lightblue"], "#EEF1F8", PAL["lightblue"], PAL["horizon"],
          "#EEF1F8", PAL["lightblue"])

# HTTP / API
emit_band("http", "HTTP / API &#183; app/Http",
          ["API Controllers &#215;75", "Form Requests &#215;38", "API Resources &#215;21", "Middleware"],
          4, PAL["violet"], PAL["violet_l"], PAL["violet"], PAL["violet_t"],
          PAL["violet_l"], PAL["violet"])

# AGENTS  -- Coordinating drawn as a distinct hub
agents_tail = ["ProtectionAgent", "SavingsAgent", "InvestmentAgent", "RetirementAgent",
               "EstateAgent", "GoalsAgent", "TaxOptimisationAgent", "BaseAgent"]
placed, bw, _ = layout_rows(agents_tail, 4, SPINE_W, top=52 + BOX_H + GAP_Y)
band_h = (52 + BOX_H + GAP_Y) + 2 * BOX_H + GAP_Y + 16
ag = add("AGENTS &#183; orchestration &#183; app/Agents &#183; 9", swimlane_style(PAL["raspberry"], PAL["raspberry_l"], PAL["raspberry"]),
         SPINE_X, y, SPINE_W, band_h)
band_ids["agents"] = ag
band_bounds["agents"] = (y, band_h)
# hub
hub_w = (SPINE_W - 2 * PAD_X - 3 * GAP_X) / 4
add("CoordinatingAgent<br><font style='font-size:9px'>cross-module orchestrator</font>",
    box_style(PAL["raspberry"], PAL["raspberry_d"], PAL["white"]), PAD_X, 52, hub_w * 2 + GAP_X, BOX_H, parent=ag)
for lab, bx, by, bx_w, bh in placed:
    add(lab, box_style(PAL["raspberry_l"], PAL["raspberry"], PAL["raspberry_t"]), bx, by, bx_w, bh, parent=ag)
y += band_h + BAND_GAP

# SERVICES (outer swimlane with two inner sub-bands)
product = ["Protection", "Savings", "Investment", "Retirement", "Estate", "Goals",
           "NetWorth", "Property", "Trust", "Chattel", "Plans", "WhatIf",
           "Coordination", "Benefits", "Business", "Tax", "Risk", "Insights"]
platform = ["AI", "Audit", "Auth", "Cache", "GDPR", "Gamification", "Eval", "Integrations",
            "Lifecycle", "LifeStage", "Marketing", "Mobile", "Onboarding", "Payment",
            "Settings", "Shared", "Stores", "Tiers", "UserProfile", "Dashboard",
            "Account", "Admin", "Advisor", "Documents"]
prod_placed, _, prod_h = layout_rows(product, 6, SPINE_W, top=44)
plat_placed, _, plat_h = layout_rows(platform, 6, SPINE_W, top=44)
inner_gap = 18
services_h = TITLE_H + 14 + prod_h + inner_gap + plat_h + 16
sv = add("SERVICES &#183; domain &#183; app/Services &#183; 42 modules",
         swimlane_style(PAL["spring"], "#F4FBF8", PAL["spring"]), SPINE_X, y, SPINE_W, services_h)
band_ids["services"] = sv
band_bounds["services"] = (y, services_h)
inner_w = SPINE_W - 2 * 14
# product sub-band
prod_sl = add("Product &amp; domain modules &#183; 18",
              swimlane_style(PAL["spring"], PAL["spring_l"], PAL["spring_d"]),
              14, TITLE_H + 14, inner_w, prod_h, parent=sv)
for lab, bx, by, bx_w, bh in prod_placed:
    add(lab, box_style(PAL["spring_l"], PAL["spring"], PAL["spring_t"], fontsize=10), bx, by, bx_w, bh, parent=prod_sl)
# platform sub-band
plat_sl = add("Platform &amp; cross-cutting infrastructure &#183; 24",
              swimlane_style(PAL["lightblue"], PAL["violet_50"], PAL["violet_a"]),
              14, TITLE_H + 14 + prod_h + inner_gap, inner_w, plat_h, parent=sv)
for lab, bx, by, bx_w, bh in plat_placed:
    add(lab, box_style(PAL["violet_50"], PAL["violet_a"], PAL["violet_t"], fontsize=10), bx, by, bx_w, bh, parent=plat_sl)
y += services_h + BAND_GAP

# MODELS
emit_band("models", "MODELS &#183; Eloquent ORM &#183; app/Models &#183; 106",
          ["User", "Account", "Policy", "Pension", "Investment", "Property",
           "Goal", "Trust", "EstatePlan", "Mortgage", "TaxConfiguration", "&#8230; 106 total"],
          6, PAL["horizon"], PAL["horizon_100"], PAL["horizon"], PAL["horizon_d"],
          PAL["horizon_200"], PAL["horizon"])

# DATABASE (cylinder)
db_h = TITLE_H + 16 + 90
db = add("DATABASE", swimlane_style(PAL["horizon_d"], "#F4F6FA", PAL["horizon_d"]), SPINE_X, y, SPINE_W, db_h)
band_ids["db"] = db
band_bounds["db"] = (y, db_h)
add("MySQL 8",
    f"shape=cylinder3;whiteSpace=wrap;html=1;fillColor={PAL['horizon']};strokeColor={PAL['horizon_d']};fontColor={PAL['white']};fontSize=14;fontStyle=1;",
    SPINE_W / 2 - 80, 52, 160, 80, parent=db)
y_total = y + db_h

# ---- SUPPORT side column (cross-cutting) -----------------------------------
ag_y = band_bounds["agents"][0]
mod_y, mod_h = band_bounds["models"]
side_y = ag_y
side_h = (mod_y + mod_h) - ag_y
support = ["Observers &#215;18", "Jobs &#215;7", "Events", "Listeners",
           "Traits", "Constants", "Enums", "DTOs / ValueObjects", "Exceptions", "Providers"]
sup = add("SUPPORT &#183; cross-cutting &#183; app",
          swimlane_style(PAL["neutral"], PAL["savannah_100"], PAL["neutral"], dashed=True),
          SIDE_X, side_y, SIDE_W, side_h)
# vertical stack
n = len(support)
avail = side_h - TITLE_H - 24
sb_h = min(BOX_H, (avail - (n - 1) * 10) / n)
for i, lab in enumerate(support):
    yy = TITLE_H + 12 + i * (sb_h + 10)
    add(lab, box_style(PAL["savannah_100"], PAL["neutral"], PAL["horizon"], fontsize=10),
        18, yy, SIDE_W - 36, sb_h, parent=sup)

# ---- edges -----------------------------------------------------------------
def edge(src, tgt, label="", dashed=False, color=PAL["horizon"], exitp=(0.5, 1), entryp=(0.5, 0)):
    d = "dashed=1;" if dashed else ""
    style = (f"edgeStyle=orthogonalEdgeStyle;rounded=1;orthogonalLoop=1;jettySize=auto;html=1;{d}"
             f"strokeColor={color};strokeWidth=2;fontColor={color};fontSize=11;fontStyle=1;"
             f"exitX={exitp[0]};exitY={exitp[1]};exitDx=0;exitDy=0;entryX={entryp[0]};entryY={entryp[1]};entryDx=0;entryDy=0;")
    edges.append((nid(), label, style, src, tgt))

edge(band_ids["client"], band_ids["frontend"])
edge(band_ids["frontend"], band_ids["http"], "HTTP &#183; Axios", color=PAL["lightblue"])
edge(band_ids["http"], band_ids["agents"], "validated request", color=PAL["violet"])
edge(band_ids["agents"], band_ids["services"], "orchestrate", color=PAL["raspberry"])
edge(band_ids["services"], band_ids["models"], "Eloquent ORM", color=PAL["spring_d"])
edge(band_ids["models"], band_ids["db"], "SQL", color=PAL["horizon"])
# cross-cutting (dashed) into support column
edge(band_ids["services"], sup, "events / jobs", dashed=True, color=PAL["neutral"],
     exitp=(1, 0.5), entryp=(0, 0.35))
edge(sup, band_ids["models"], "observers react", dashed=True, color=PAL["neutral"],
     exitp=(0, 0.85), entryp=(1, 0.5))

# ---- serialise -------------------------------------------------------------
parts = ['<?xml version="1.0" encoding="UTF-8"?>',
         '<mxfile host="drawio" version="26.0.0">',
         '  <diagram name="Service Layers">',
         f'    <mxGraphModel dx="1400" dy="{int(y_total)}" grid="0" gridSize="10" guides="1" '
         'tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" '
         f'pageWidth="{SIDE_X + SIDE_W + 40}" pageHeight="{int(y_total) + 40}" math="0" shadow="0">',
         '      <root>',
         '        <mxCell id="0" />',
         '        <mxCell id="1" parent="0" />']

def xv(s):
    # value holds prepared draw.io HTML: escape < and > so the attribute is
    # well-formed XML; leave & alone (every & is a valid entity: &amp; &#215; etc.).
    return s.replace("<", "&lt;").replace(">", "&gt;")

for cid, value, style, x, yy, w, h, parent, _ in cells:
    parts.append(
        f'        <mxCell id="{cid}" value="{xv(value)}" style="{escape(style, {chr(34): "&quot;"})}" '
        f'vertex="1" parent="{parent}">'
        f'<mxGeometry x="{x:.0f}" y="{yy:.0f}" width="{w:.0f}" height="{h:.0f}" as="geometry" /></mxCell>')

for eid, value, style, src, tgt in edges:
    parts.append(
        f'        <mxCell id="{eid}" value="{xv(value)}" style="{escape(style, {chr(34): "&quot;"})}" '
        f'edge="1" parent="1" source="{src}" target="{tgt}">'
        f'<mxGeometry relative="1" as="geometry" /></mxCell>')

parts += ['      </root>', '    </mxGraphModel>', '  </diagram>', '</mxfile>']

out = os.path.join(os.path.dirname(__file__), "fynla-service-layers.drawio")
with open(out, "w") as f:
    f.write("\n".join(parts))
print(f"wrote {out}  ({len(cells)} cells, {len(edges)} edges, height {int(y_total)})")
