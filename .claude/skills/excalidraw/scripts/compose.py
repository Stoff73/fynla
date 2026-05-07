#!/usr/bin/env python3
"""
Compose Fynla Excalidraw diagrams from a simple node/edge DSL.

Usage (as a library):

    from compose import Diagram, FYNLA_PALETTE

    d = Diagram(title="Fynla Architecture")
    d.node("vue", "Vue Component", x=100, y=100, kind="view")
    d.node("api", "API Service", x=100, y=220, kind="service")
    d.arrow("vue", "api", label="HTTP")
    d.save("docs/diagrams/architecture.excalidraw")

Kinds drive colour + shape:

    view        → raspberry  rectangle   (CTAs / user-facing surfaces)
    controller  → horizon    rectangle   (HTTP layer)
    agent       → raspberry  diamond     (module orchestrators)
    service     → spring     rectangle   (domain calculators)
    model       → horizon    ellipse     (data)
    warning     → violet     rectangle   (risky / important)
    note        → savannah   rectangle   (context)
    highlight   → raspberry  rectangle   (emphasis)

This script is the engine for the `excalidraw` skill. It produces
deterministic, valid Excalidraw JSON that opens cleanly in the local
Excalidraw web app (drag-and-drop or File → Open).
"""
from __future__ import annotations
import json
import random
from dataclasses import dataclass, field
from pathlib import Path

# -----------------------------------------------------------------------------
# Fynla palette → Excalidraw stroke/background hex values
# (derived from fynlaDesignGuide.md v1.3.0)
# -----------------------------------------------------------------------------
FYNLA_PALETTE = {
    "raspberry":     {"stroke": "#E83E6D", "bg": "#FCE7EF"},
    "horizon":       {"stroke": "#1F2A44", "bg": "#E4E8F0"},
    "spring":        {"stroke": "#20B486", "bg": "#D6F3E8"},
    "violet":        {"stroke": "#5854E6", "bg": "#E1E0FB"},
    "savannah":      {"stroke": "#C9C2B7", "bg": "#FDFAF7"},
    "neutral":       {"stroke": "#717171", "bg": "#F5F5F5"},
    "eggshell":      {"stroke": "#D4D2CD", "bg": "#F7F6F4"},
}

# Kind → (palette_key, shape)
KIND_MAP = {
    "view":       ("raspberry", "rectangle"),
    "controller": ("horizon",   "rectangle"),
    "agent":      ("raspberry", "diamond"),
    "service":    ("spring",    "rectangle"),
    "model":      ("horizon",   "ellipse"),
    "warning":    ("violet",    "rectangle"),
    "note":       ("savannah",  "rectangle"),
    "highlight":  ("raspberry", "rectangle"),
    "default":    ("neutral",   "rectangle"),
}


def _rand_seed(key: str) -> int:
    """Deterministic seed derived from element id → stable re-open layout."""
    rng = random.Random(key)
    return rng.randint(1, 2_000_000_000)


@dataclass
class Diagram:
    title: str
    elements: list[dict] = field(default_factory=list)
    _bindings: dict[str, list[str]] = field(default_factory=dict)

    # ------------------------------------------------------------------
    def node(
        self,
        id: str,
        text: str,
        *,
        x: int,
        y: int,
        w: int = 180,
        h: int = 60,
        kind: str = "default",
        link: str | None = None,
        font_size: int = 16,
    ) -> None:
        palette_key, shape = KIND_MAP.get(kind, KIND_MAP["default"])
        colours = FYNLA_PALETTE[palette_key]
        box_id = f"node-{id}"
        text_id = f"text-{id}"

        # Container shape
        container = {
            "id": box_id,
            "type": shape,
            "x": x,
            "y": y,
            "width": w,
            "height": h,
            "angle": 0,
            "strokeColor": colours["stroke"],
            "backgroundColor": colours["bg"],
            "fillStyle": "solid",
            "strokeWidth": 2,
            "strokeStyle": "solid",
            "roughness": 1,
            "opacity": 100,
            "groupIds": [],
            "frameId": None,
            "roundness": {"type": 3} if shape == "rectangle" else None,
            "seed": _rand_seed(box_id),
            "version": 1,
            "versionNonce": _rand_seed(box_id + "-vn"),
            "isDeleted": False,
            "boundElements": [{"id": text_id, "type": "text"}],
            "updated": 1,
            "link": link,
            "locked": False,
        }
        # Bound text (sits centred inside the container)
        label = {
            "id": text_id,
            "type": "text",
            "x": x + 8,
            "y": y + h / 2 - font_size * 0.7,
            "width": w - 16,
            "height": font_size * 1.4,
            "angle": 0,
            "strokeColor": "#1F2A44",  # horizon-500 for all body text
            "backgroundColor": "transparent",
            "fillStyle": "solid",
            "strokeWidth": 1,
            "strokeStyle": "solid",
            "roughness": 1,
            "opacity": 100,
            "groupIds": [],
            "frameId": None,
            "roundness": None,
            "seed": _rand_seed(text_id),
            "version": 1,
            "versionNonce": _rand_seed(text_id + "-vn"),
            "isDeleted": False,
            "boundElements": None,
            "updated": 1,
            "link": None,
            "locked": False,
            "text": text,
            "fontSize": font_size,
            "fontFamily": 5,   # 5 = Excalidraw "Helvetica" sans (closest readable)
            "textAlign": "center",
            "verticalAlign": "middle",
            "baseline": int(font_size * 1.1),
            "containerId": box_id,
            "originalText": text,
            "lineHeight": 1.25,
        }
        self.elements.extend([container, label])
        self._bindings.setdefault(box_id, []).append(text_id)

    # ------------------------------------------------------------------
    def label(
        self,
        id: str,
        text: str,
        *,
        x: int,
        y: int,
        font_size: int = 20,
        color: str = "#1F2A44",
    ) -> None:
        """Free-floating text (diagram title, section labels)."""
        tid = f"label-{id}"
        self.elements.append({
            "id": tid,
            "type": "text",
            "x": x,
            "y": y,
            "width": len(text) * font_size * 0.6,
            "height": font_size * 1.4,
            "angle": 0,
            "strokeColor": color,
            "backgroundColor": "transparent",
            "fillStyle": "solid",
            "strokeWidth": 1,
            "strokeStyle": "solid",
            "roughness": 1,
            "opacity": 100,
            "groupIds": [],
            "frameId": None,
            "roundness": None,
            "seed": _rand_seed(tid),
            "version": 1,
            "versionNonce": _rand_seed(tid + "-vn"),
            "isDeleted": False,
            "boundElements": None,
            "updated": 1,
            "link": None,
            "locked": False,
            "text": text,
            "fontSize": font_size,
            "fontFamily": 5,
            "textAlign": "left",
            "verticalAlign": "top",
            "baseline": int(font_size * 1.1),
            "containerId": None,
            "originalText": text,
            "lineHeight": 1.25,
        })

    # ------------------------------------------------------------------
    def arrow(
        self,
        from_id: str,
        to_id: str,
        *,
        label: str | None = None,
        style: str = "solid",        # "solid" | "dashed" | "dotted"
        colour: str = "horizon",     # palette key
        thick: bool = False,
    ) -> None:
        """Bound arrow from one node to another."""
        fb = f"node-{from_id}"
        tb = f"node-{to_id}"
        stroke = FYNLA_PALETTE[colour]["stroke"]
        arr_id = f"arrow-{from_id}-to-{to_id}"

        from_el = next(e for e in self.elements if e["id"] == fb)
        to_el   = next(e for e in self.elements if e["id"] == tb)

        # Approximate connection points — anchor on nearest side centres
        fx, fy = _anchor_point(from_el, to_el)
        tx, ty = _anchor_point(to_el, from_el)

        arrow = {
            "id": arr_id,
            "type": "arrow",
            "x": fx,
            "y": fy,
            "width": tx - fx,
            "height": ty - fy,
            "angle": 0,
            "strokeColor": stroke,
            "backgroundColor": "transparent",
            "fillStyle": "solid",
            "strokeWidth": 3 if thick else 2,
            "strokeStyle": style,
            "roughness": 1,
            "opacity": 100,
            "groupIds": [],
            "frameId": None,
            "roundness": {"type": 2},
            "seed": _rand_seed(arr_id),
            "version": 1,
            "versionNonce": _rand_seed(arr_id + "-vn"),
            "isDeleted": False,
            "boundElements": [],
            "updated": 1,
            "link": None,
            "locked": False,
            "points": [[0, 0], [tx - fx, ty - fy]],
            "lastCommittedPoint": None,
            "startBinding": {"elementId": fb, "focus": 0, "gap": 4},
            "endBinding":   {"elementId": tb, "focus": 0, "gap": 4},
            "startArrowhead": None,
            "endArrowhead": "arrow",
            "elbowed": False,
        }
        self.elements.append(arrow)

        # Bind the arrow to both nodes (Excalidraw sync requirement)
        from_el.setdefault("boundElements", []).append({"id": arr_id, "type": "arrow"})
        to_el.setdefault("boundElements", []).append({"id": arr_id, "type": "arrow"})

        if label:
            # Mid-arrow label (free text, not container-bound — Excalidraw's
            # arrow-text binding is fiddly; a free label reads fine)
            mx = (fx + tx) / 2
            my = (fy + ty) / 2 - 10
            lid = f"arrow-label-{from_id}-to-{to_id}"
            self.elements.append({
                "id": lid,
                "type": "text",
                "x": mx - len(label) * 4,
                "y": my,
                "width": len(label) * 8,
                "height": 20,
                "angle": 0,
                "strokeColor": stroke,
                "backgroundColor": "transparent",
                "fillStyle": "solid",
                "strokeWidth": 1,
                "strokeStyle": "solid",
                "roughness": 1,
                "opacity": 100,
                "groupIds": [],
                "frameId": None,
                "roundness": None,
                "seed": _rand_seed(lid),
                "version": 1,
                "versionNonce": _rand_seed(lid + "-vn"),
                "isDeleted": False,
                "boundElements": None,
                "updated": 1,
                "link": None,
                "locked": False,
                "text": label,
                "fontSize": 14,
                "fontFamily": 5,
                "textAlign": "center",
                "verticalAlign": "middle",
                "baseline": 14,
                "containerId": None,
                "originalText": label,
                "lineHeight": 1.25,
            })

    # ------------------------------------------------------------------
    def to_json(self) -> dict:
        return {
            "type": "excalidraw",
            "version": 2,
            "source": "https://excalidraw.com",
            "elements": self.elements,
            "appState": {
                "gridSize": 20,
                "gridStep": 5,
                "gridModeEnabled": False,
                "viewBackgroundColor": "#F7F6F4",  # eggshell-500
            },
            "files": {},
        }

    def save(self, path: str | Path) -> Path:
        p = Path(path)
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text(json.dumps(self.to_json(), indent=2))
        return p


def _anchor_point(src: dict, other: dict) -> tuple[float, float]:
    """Centre anchor on the side of src closest to other's centre."""
    sx, sy = src["x"] + src["width"] / 2, src["y"] + src["height"] / 2
    ox, oy = other["x"] + other["width"] / 2, other["y"] + other["height"] / 2
    dx, dy = ox - sx, oy - sy

    if abs(dx) > abs(dy):  # horizontal dominant → connect at L/R edge
        x = src["x"] + src["width"] if dx > 0 else src["x"]
        y = sy
    else:                  # vertical dominant → connect at T/B edge
        x = sx
        y = src["y"] + src["height"] if dy > 0 else src["y"]
    return x, y


# -----------------------------------------------------------------------------
# CLI: compose [--spec <file>]  (optional; hand-writing Diagram() is easier)
# -----------------------------------------------------------------------------
if __name__ == "__main__":
    import argparse
    ap = argparse.ArgumentParser(description="Fynla Excalidraw composer")
    ap.add_argument("command", choices=["palette"], help="Show the Fynla palette mapping")
    args = ap.parse_args()
    if args.command == "palette":
        for k, v in FYNLA_PALETTE.items():
            print(f"{k:12} stroke={v['stroke']}   bg={v['bg']}")
