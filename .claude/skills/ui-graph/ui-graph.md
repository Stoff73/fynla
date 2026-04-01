# UI Graph Styling — Fynla Chart Conventions

Apply consistent chart styling across all Fynla ApexCharts components. This skill codifies the standard graph configuration so every chart looks unified.

## Core Principle

All charts import from `resources/js/constants/designSystem.js` — never hardcode font families, colours, or config.

## Required Import

Every chart component must import `CHART_DEFAULTS` and spread it as the base config:

```javascript
import { CHART_DEFAULTS } from '@/constants/designSystem';
```

Additional imports as needed:
- `CHART_COLORS` — multi-series ordered palette (8 colours)
- `SPENDING_COLORS` — expenditure donut palette (16 colours)
- `ASSET_COLORS` — wealth breakdown by asset type
- `TEXT_COLORS` — axis labels, legends, tooltips
- `BORDER_COLORS` — grid lines, borders
- `SECONDARY_COLORS` — horizon palette for sparklines
- `SUCCESS_COLORS`, `ERROR_COLORS`, `WARNING_COLORS` — semantic colours

## Chart Type Recipes

### Donut / Pie Charts

```javascript
chartOptions: {
  chart: {
    ...CHART_DEFAULTS.chart,
    type: 'donut',
  },
  labels: this.labels,
  colors: CHART_COLORS, // or SPENDING_COLORS for expenditure
  plotOptions: {
    pie: {
      donut: {
        size: '65%',
        labels: {
          show: true,
          name: {
            show: true,
            fontSize: '14px',
            fontWeight: 500,
            color: TEXT_COLORS.muted,
            offsetY: -10,
          },
          value: {
            show: true,
            fontSize: '24px',
            fontWeight: 700,
            color: TEXT_COLORS.primary,
            offsetY: 5,
            formatter: (val) => `£${parseFloat(val).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
          },
          total: {
            show: true,
            showAlways: true,
            label: 'Total',
            fontSize: '14px',
            fontWeight: 500,
            color: TEXT_COLORS.muted,
          },
        },
      },
    },
  },
  dataLabels: { enabled: false },
  legend: { show: false },
  tooltip: {
    enabled: true,
    y: { formatter: (val) => `£${val.toFixed(2)}` },
  },
}
```

### Sparkline / Dashboard Mini Line Charts

Used in `DashboardSparkline.vue` for module summary cards.

```javascript
chartOptions: {
  chart: {
    ...CHART_DEFAULTS.chart,
    type: 'line',
    toolbar: { show: false },
    zoom: { enabled: false },
    sparkline: { enabled: false },
  },
  colors: [this.color], // typically SECONDARY_COLORS[500]
  stroke: {
    curve: 'smooth',
    width: 2.5,
    lineCap: 'round',
  },
  markers: {
    size: 5,
    colors: [this.color],
    strokeColors: '#ffffff',
    strokeWidth: 2,
    hover: { sizeOffset: 2 },
  },
  fill: {
    type: 'gradient',
    gradient: {
      shade: 'light',
      type: 'vertical',
      opacityFrom: 0.15,
      opacityTo: 0.02,
    },
  },
  xaxis: {
    categories: this.data.map(d => d.label),
    labels: {
      style: { fontSize: '10px', colors: TEXT_COLORS.muted },
    },
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: {
    show: false,
    min: (min) => min * 0.95,
    max: (max) => max * 1.05,
  },
  grid: {
    show: false,
    padding: { left: 0, right: 0, top: -10, bottom: 0 },
  },
  tooltip: { enabled: false },
  legend: { show: false },
  dataLabels: { enabled: false },
}
```

### Full-Size Line / Area Charts

Used on module detail pages (projections, growth over time).

```javascript
chartOptions: {
  chart: {
    ...CHART_DEFAULTS.chart,
    type: 'area', // or 'line'
  },
  colors: CHART_COLORS,
  stroke: {
    curve: 'smooth',
    width: 2,
  },
  fill: {
    type: 'gradient',
    gradient: {
      shade: 'light',
      type: 'vertical',
      opacityFrom: 0.2,
      opacityTo: 0.05,
    },
  },
  xaxis: {
    ...CHART_DEFAULTS.xaxis,
    categories: this.labels,
  },
  yaxis: {
    ...CHART_DEFAULTS.yaxis,
    labels: {
      ...CHART_DEFAULTS.yaxis.labels,
      formatter: (val) => `£${(val / 1000).toFixed(0)}k`,
    },
  },
  grid: {
    ...CHART_DEFAULTS.grid,
  },
  dataLabels: { enabled: false },
  legend: {
    ...CHART_DEFAULTS.legend,
    position: 'top',
    horizontalAlign: 'left',
  },
  tooltip: {
    ...CHART_DEFAULTS.tooltip,
    y: { formatter: (val) => `£${val.toLocaleString('en-GB')}` },
  },
}
```

### Bar Charts

```javascript
chartOptions: {
  chart: {
    ...CHART_DEFAULTS.chart,
    type: 'bar',
  },
  colors: CHART_COLORS,
  plotOptions: {
    bar: {
      borderRadius: 4,
      columnWidth: '60%',
    },
  },
  xaxis: { ...CHART_DEFAULTS.xaxis },
  yaxis: { ...CHART_DEFAULTS.yaxis },
  grid: { ...CHART_DEFAULTS.grid },
  dataLabels: { enabled: false },
  legend: { ...CHART_DEFAULTS.legend },
}
```

## Rules

1. **Always spread `CHART_DEFAULTS.chart`** as the base chart config — this sets font family, disables toolbar and zoom.
2. **Never hardcode `fontFamily`** — it comes from `CHART_DEFAULTS.chart.fontFamily` (`'Segoe UI, Inter, system-ui, sans-serif'`).
3. **Never hardcode hex colours** in chart options — import from `designSystem.js` constants.
4. **Use `TEXT_COLORS.muted`** for axis labels and captions, `TEXT_COLORS.primary` for primary values.
5. **Use `BORDER_COLORS.default`** for grid lines.
6. **Delay chart render** with `setTimeout(() => this.chartReady = true, 100)` to avoid ApexCharts flash.
7. **Currency formatting** in tooltips/labels: `£${val.toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`.
8. **Responsive breakpoints**: Add `responsive: [{ breakpoint: 768, options: { chart: { height: 260 } } }]` for mobile.

## Checklist — Updating an Existing Chart

- [ ] Import `CHART_DEFAULTS` (and other needed constants) from `@/constants/designSystem`
- [ ] Replace `chart: { type: '...', fontFamily: '...', toolbar: {...} }` with `chart: { ...CHART_DEFAULTS.chart, type: '...' }`
- [ ] Replace hardcoded hex colours with design system constants
- [ ] Replace hardcoded `fontFamily` strings with spreads from `CHART_DEFAULTS`
- [ ] Verify axis labels use `TEXT_COLORS.muted`
- [ ] Verify grid uses `BORDER_COLORS.default` (or `show: false` for sparklines)
- [ ] Verify chart renders with delayed `chartReady` flag
