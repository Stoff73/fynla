# Journey Map Fix — Working Notes

## Approved Mockup (v6) — 6 steps
- viewBox: 0 0 900 540
- Horizontal section: 3 nodes (steps 1-3) alternating y=90/y=260
- Vertical drop: node 4 at x=790,y=280 then node 5 at x=770,y=450
- Return curve: node 6 at x=530,y=370
- Destination: x=350,y=430

## Scaling for different step counts
- 6 steps: exact v6 coordinates
- 7 steps: extend horizontal to 4 nodes, vertical 2, return 1
- 8 steps: extend horizontal to 4 nodes, vertical 2, return 2

## Node position pattern
1. First ~60% of steps: horizontal meander (top y=90, bottom y=260, spacing ~240px)
2. Next ~25%: vertical section on right side (x near 790, y increasing ~170px per step)
3. Last ~15%: return curve going left (x decreasing ~240px, y slightly varying)
4. Destination: continues left and slightly down from last node

## Label positioning rules (28px gap from node edge, r=22)
- Top row nodes (y=90): label BELOW, labelY = y + 22 + 28 = y + 50 → 140
- Bottom row nodes (y=260): label ABOVE, last line at y - 22 - 28 = y - 50 → 210
- Vertical section: label LEFT, labelX = x - 22 - 28 = x - 50
- Return section: label ABOVE or LEFT depending on position

## Status: IN PROGRESS
