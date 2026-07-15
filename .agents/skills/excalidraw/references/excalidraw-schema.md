# Excalidraw JSON Schema (working reference)

Reference for the subset of the Excalidraw format this skill emits. The full types live in `/Users/CSJ/Desktop/excalidraw-master/packages/element/src/types.ts` if you need anything beyond this.

## File envelope

```json
{
  "type": "excalidraw",
  "version": 2,
  "source": "https://excalidraw.com",
  "elements": [ /* ExcalidrawElement[] */ ],
  "appState": {
    "gridSize": 20,
    "viewBackgroundColor": "#F7F6F4"
  },
  "files": {}
}
```

## Element base (all elements inherit these fields)

```
id: string            // stable unique id (compose.py uses "node-<key>" etc.)
type: "rectangle" | "diamond" | "ellipse" | "arrow" | "line" | "text" | "image" | "frame"
x, y: number          // top-left in canvas coordinates
width, height: number
angle: number         // rotation radians, usually 0
strokeColor: string   // hex
backgroundColor: string
fillStyle: "solid" | "hachure" | "cross-hatch" | "zigzag" | "dots"
strokeWidth: number   // 1 | 2 | 4 (thin/medium/thick in UI)
strokeStyle: "solid" | "dashed" | "dotted"
roughness: number     // 0 = precise, 1 = architect, 2 = cartoonist
opacity: number       // 0-100
groupIds: string[]
frameId: string | null
roundness: { type: number } | null   // { type: 3 } for rectangles gives rounded corners
seed: number          // stable randomness seed — same id ⇒ same hand-drawn jitter
version: number       // bump on edit; 1 is fine for skill-generated files
versionNonce: number  // same as seed behaviour
isDeleted: boolean
boundElements: { id: string, type: string }[] | null
updated: number
link: string | null
locked: boolean
```

## Rectangle / Diamond / Ellipse (container shapes)

Use the element base directly. Add `roundness: { type: 3 }` on rectangles for rounded corners.

## Text element

```
...base...,
text: string
fontSize: number          // 16 body, 20 section, 24+ title
fontFamily: 1 | 2 | 3 | 5  // 5 = Helvetica clean sans
textAlign: "left" | "center" | "right"
verticalAlign: "top" | "middle" | "bottom"
baseline: number          // ~= fontSize * 1.1
containerId: string | null  // set to the container's id if this text sits inside a box
originalText: string
lineHeight: number
```

## Arrow

```
...base...,
type: "arrow",
points: [[0,0], [dx, dy]],            // relative to arrow's x,y origin
lastCommittedPoint: [x,y] | null,
startBinding: { elementId, focus, gap } | null,
endBinding:   { elementId, focus, gap } | null,
startArrowhead: "arrow" | "triangle" | "dot" | null,
endArrowhead:   "arrow" | "triangle" | "dot" | null,
elbowed: boolean
```

**Critical:** whenever an arrow binds to a container, the container's own `boundElements` array must include `{ id: <arrow-id>, type: "arrow" }`. `compose.py` handles this automatically. Hand-edits that skip the reverse-binding render as broken arrows that snap off when the diagram opens.

## Frame (optional grouping header)

```
...base...,
type: "frame",
name: string
```

Elements become "members" of a frame by setting their `frameId` to the frame's `id`.

## Minimum viable new element

For `compose.py` use:

```python
{
  "id": "node-my-thing",
  "type": "rectangle",
  "x": 100, "y": 100, "width": 180, "height": 60,
  "angle": 0,
  "strokeColor": "#E83E6D",
  "backgroundColor": "#FCE7EF",
  "fillStyle": "solid",
  "strokeWidth": 2,
  "strokeStyle": "solid",
  "roughness": 1,
  "opacity": 100,
  "groupIds": [],
  "frameId": None,
  "roundness": {"type": 3},
  "seed": 123456,
  "version": 1,
  "versionNonce": 789012,
  "isDeleted": False,
  "boundElements": [],
  "updated": 1,
  "link": None,
  "locked": False,
}
```

Missing any of these fields risks the diagram failing to load (silent blank canvas). The helper guarantees completeness.

## Links

`link` accepts:
- HTTP URLs (opens in browser)
- `[[Wikilink]]` — renders as clickable when opened inside a vault with the Obsidian Excalidraw plugin
- `file:///absolute/path` — opens local files

Wikilinks inside Excalidraw canvases are how the Fynla vault's graph stays connected when diagrams live in `fynlaBrain/Diagrams/`.
