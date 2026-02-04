# Fynla Chart Pattern (ApexCharts)

## Overview

Fynla uses ApexCharts for all data visualizations. This document covers standard patterns for creating charts across all modules.

## Global Registration

ApexCharts is registered globally in `resources/js/app.js`:

```javascript
import VueApexCharts from 'vue3-apexcharts';
app.component('apexchart', VueApexCharts);
```

## Chart Types Used in Fynla

1. **Radial Bar (Gauge)** - Scores and percentages
2. **Donut** - Asset allocation and breakdowns
3. **Line** - Performance over time
4. **Area** - Projections and cumulative data
5. **Stacked Area** - Income sources over time
6. **Bar** - Comparisons and breakdowns
7. **Waterfall** - IHT calculation breakdown
8. **Heatmap** - Gap analysis
9. **RangeBar/Timeline** - Gifting strategy timeline

---

## Pattern 1: Radial Bar (Gauge) Chart

**Use case**: Coverage scores, readiness scores, progress indicators

```vue
<template>
  <div class="gauge-chart">
    <h3 class="text-lg font-semibold mb-4">{{ title }}</h3>
    <apexchart
      type="radialBar"
      :options="chartOptions"
      :series="series"
      height="300"
    ></apexchart>
  </div>
</template>

<script>
export default {
  name: 'GaugeChart',

  props: {
    title: {
      type: String,
      required: true,
    },
    score: {
      type: Number,
      required: true,
      validator: (value) => value >= 0 && value <= 100,
    },
    label: {
      type: String,
      default: 'Score',
    },
  },

  computed: {
    series() {
      return [this.score];
    },

    chartOptions() {
      return {
        chart: {
          type: 'radialBar',
        },
        plotOptions: {
          radialBar: {
            hollow: {
              size: '60%',
            },
            dataLabels: {
              name: {
                fontSize: '16px',
                color: '#6B7280',
              },
              value: {
                fontSize: '32px',
                fontWeight: 'bold',
                color: '#111827',
                formatter: (val) => `${Math.round(val)}%`,
              },
            },
          },
        },
        colors: [this.getColor(this.score)],
        labels: [this.label],
      };
    },
  },

  methods: {
    getColor(score) {
      // Traffic light system
      if (score >= 80) return '#10B981'; // Green
      if (score >= 60) return '#F59E0B'; // Amber
      return '#EF4444'; // Red
    },
  },
};
</script>
```

**Example usage**:
```vue
<GaugeChart
  title="Coverage Adequacy"
  :score="adequacyScore"
  label="Coverage Score"
/>
```

---

## Pattern 2: Donut Chart

**Use case**: Asset allocation, portfolio breakdown

```vue
<template>
  <div class="donut-chart">
    <h3 class="text-lg font-semibold mb-4">{{ title }}</h3>
    <apexchart
      type="donut"
      :options="chartOptions"
      :series="series"
      height="350"
    ></apexchart>
  </div>
</template>

<script>
export default {
  name: 'DonutChart',

  props: {
    title: {
      type: String,
      required: true,
    },
    data: {
      type: Array,
      required: true,
      // Example: [{ label: 'Equities', value: 50000 }]
    },
  },

  computed: {
    series() {
      return this.data.map((item) => item.value);
    },

    labels() {
      return this.data.map((item) => item.label);
    },

    chartOptions() {
      return {
        chart: {
          type: 'donut',
        },
        labels: this.labels,
        colors: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899'],
        dataLabels: {
          enabled: true,
          formatter: (val) => `${val.toFixed(1)}%`,
        },
        legend: {
          position: 'bottom',
          horizontalAlign: 'center',
        },
        plotOptions: {
          pie: {
            donut: {
              size: '65%',
              labels: {
                show: true,
                total: {
                  show: true,
                  label: 'Total',
                  formatter: () => this.formatCurrency(this.total),
                },
              },
            },
          },
        },
        tooltip: {
          y: {
            formatter: (value) => this.formatCurrency(value),
          },
        },
      };
    },

    total() {
      return this.data.reduce((sum, item) => sum + item.value, 0);
    },
  },

  methods: {
    formatCurrency(value) {
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },
  },
};
</script>
```

**Example usage**:
```vue
<DonutChart
  title="Asset Allocation"
  :data="allocationData"
/>
```

---

## Pattern 3: Line Chart

**Use case**: Performance over time, historical data

```vue
<template>
  <div class="line-chart">
    <h3 class="text-lg font-semibold mb-4">{{ title }}</h3>
    <apexchart
      type="line"
      :options="chartOptions"
      :series="series"
      height="350"
    ></apexchart>
  </div>
</template>

<script>
export default {
  name: 'LineChart',

  props: {
    title: {
      type: String,
      required: true,
    },
    data: {
      type: Object,
      required: true,
      // Example: { series: [...], categories: [...] }
    },
  },

  computed: {
    series() {
      return this.data.series || [];
    },

    chartOptions() {
      return {
        chart: {
          type: 'line',
          height: 350,
          toolbar: {
            show: true,
            tools: {
              download: true,
              zoom: true,
              zoomin: true,
              zoomout: true,
              pan: true,
              reset: true,
            },
          },
        },
        stroke: {
          curve: 'smooth',
          width: 2,
        },
        xaxis: {
          categories: this.data.categories || [],
          title: {
            text: this.data.xAxisLabel || 'Date',
          },
        },
        yaxis: {
          title: {
            text: this.data.yAxisLabel || 'Value',
          },
          labels: {
            formatter: (value) => this.formatCurrency(value),
          },
        },
        colors: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444'],
        legend: {
          position: 'top',
          horizontalAlign: 'right',
        },
        tooltip: {
          y: {
            formatter: (value) => this.formatCurrency(value),
          },
        },
      };
    },
  },

  methods: {
    formatCurrency(value) {
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },
  },
};
</script>
```

**Example usage**:
```vue
<LineChart
  title="Portfolio Performance"
  :data="performanceData"
/>
```

---

## Pattern 4: Area Chart (Stacked)

**Use case**: Retirement income projections, cash flow over time

```vue
<template>
  <div class="area-chart">
    <h3 class="text-lg font-semibold mb-4">{{ title }}</h3>
    <apexchart
      type="area"
      :options="chartOptions"
      :series="series"
      height="400"
    ></apexchart>
  </div>
</template>

<script>
export default {
  name: 'AreaChart',

  props: {
    title: {
      type: String,
      required: true,
    },
    data: {
      type: Object,
      required: true,
      // Example: { series: [...], categories: [...] }
    },
    stacked: {
      type: Boolean,
      default: true,
    },
  },

  computed: {
    series() {
      return this.data.series || [];
    },

    chartOptions() {
      return {
        chart: {
          type: 'area',
          height: 400,
          stacked: this.stacked,
          toolbar: {
            show: true,
          },
        },
        dataLabels: {
          enabled: false,
        },
        stroke: {
          curve: 'smooth',
          width: 2,
        },
        fill: {
          type: 'gradient',
          gradient: {
            opacityFrom: 0.6,
            opacityTo: 0.1,
          },
        },
        xaxis: {
          categories: this.data.categories || [],
          title: {
            text: 'Age',
          },
        },
        yaxis: {
          title: {
            text: 'Annual Income (£)',
          },
          labels: {
            formatter: (value) => this.formatCurrency(value),
          },
        },
        colors: ['#3B82F6', '#10B981', '#F59E0B', '#8B5CF6'],
        legend: {
          position: 'top',
          horizontalAlign: 'center',
        },
        tooltip: {
          y: {
            formatter: (value) => this.formatCurrency(value),
          },
        },
      };
    },
  },

  methods: {
    formatCurrency(value) {
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },
  },
};
</script>
```

**Example usage**:
```vue
<AreaChart
  title="Retirement Income Projection"
  :data="incomeProjectionData"
  :stacked="true"
/>
```

---

## Pattern 5: Bar Chart

**Use case**: Comparisons, net worth breakdown

```vue
<template>
  <div class="bar-chart">
    <h3 class="text-lg font-semibold mb-4">{{ title }}</h3>
    <apexchart
      type="bar"
      :options="chartOptions"
      :series="series"
      height="350"
    ></apexchart>
  </div>
</template>

<script>
export default {
  name: 'BarChart',

  props: {
    title: {
      type: String,
      required: true,
    },
    data: {
      type: Object,
      required: true,
      // Example: { categories: [...], series: [...] }
    },
    horizontal: {
      type: Boolean,
      default: false,
    },
  },

  computed: {
    series() {
      return this.data.series || [];
    },

    chartOptions() {
      return {
        chart: {
          type: 'bar',
          height: 350,
        },
        plotOptions: {
          bar: {
            horizontal: this.horizontal,
            dataLabels: {
              position: 'top',
            },
          },
        },
        dataLabels: {
          enabled: true,
          formatter: (val) => this.formatCurrency(val),
          offsetY: -20,
          style: {
            fontSize: '12px',
            colors: ['#304758'],
          },
        },
        xaxis: {
          categories: this.data.categories || [],
        },
        yaxis: {
          labels: {
            formatter: (value) => this.formatCurrency(value),
          },
        },
        colors: ['#3B82F6'],
        tooltip: {
          y: {
            formatter: (value) => this.formatCurrency(value),
          },
        },
      };
    },
  },

  methods: {
    formatCurrency(value) {
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },
  },
};
</script>
```

---

## Pattern 6: Waterfall Chart (IHT Breakdown)

**Use case**: IHT calculation breakdown showing how liability is calculated

```vue
<template>
  <div class="waterfall-chart">
    <h3 class="text-lg font-semibold mb-4">{{ title }}</h3>
    <apexchart
      type="bar"
      :options="chartOptions"
      :series="series"
      height="400"
    ></apexchart>
  </div>
</template>

<script>
export default {
  name: 'WaterfallChart',

  props: {
    title: {
      type: String,
      required: true,
    },
    data: {
      type: Object,
      required: true,
      // Example: { categories: [...], values: [...] }
    },
  },

  computed: {
    series() {
      return [
        {
          name: 'IHT Calculation',
          data: this.data.values || [],
        },
      ];
    },

    chartOptions() {
      return {
        chart: {
          type: 'bar',
          height: 400,
        },
        plotOptions: {
          bar: {
            horizontal: false,
            columnWidth: '50%',
            colors: {
              ranges: [
                {
                  from: 0,
                  to: Infinity,
                  color: '#10B981', // Green for positive
                },
                {
                  from: -Infinity,
                  to: 0,
                  color: '#EF4444', // Red for negative
                },
              ],
            },
          },
        },
        dataLabels: {
          enabled: true,
          formatter: (val) => this.formatCurrency(Math.abs(val)),
        },
        xaxis: {
          categories: this.data.categories || [],
        },
        yaxis: {
          labels: {
            formatter: (value) => this.formatCurrency(value),
          },
        },
        tooltip: {
          y: {
            formatter: (value) => this.formatCurrency(value),
          },
        },
      };
    },
  },

  methods: {
    formatCurrency(value) {
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },
  },
};
</script>
```

**Example data structure**:
```javascript
{
  categories: [
    'Gross Estate',
    'Less: Liabilities',
    'Less: NRB',
    'Less: RNRB',
    'Taxable Estate',
    'IHT (40%)',
  ],
  values: [1000000, -200000, -325000, -175000, 300000, 120000],
}
```

---

## Pattern 7: Heatmap Chart

**Use case**: Coverage gap analysis, risk assessment

```vue
<template>
  <div class="heatmap-chart">
    <h3 class="text-lg font-semibold mb-4">{{ title }}</h3>
    <apexchart
      type="heatmap"
      :options="chartOptions"
      :series="series"
      height="300"
    ></apexchart>
  </div>
</template>

<script>
export default {
  name: 'HeatmapChart',

  props: {
    title: {
      type: String,
      required: true,
    },
    data: {
      type: Array,
      required: true,
      // Example: [{ name: 'Life', data: [...] }]
    },
  },

  computed: {
    series() {
      return this.data;
    },

    chartOptions() {
      return {
        chart: {
          type: 'heatmap',
          height: 300,
        },
        dataLabels: {
          enabled: true,
        },
        colors: ['#EF4444'], // Red gradient
        plotOptions: {
          heatmap: {
            colorScale: {
              ranges: [
                {
                  from: 0,
                  to: 30,
                  color: '#10B981',
                  name: 'Low Gap',
                },
                {
                  from: 31,
                  to: 70,
                  color: '#F59E0B',
                  name: 'Medium Gap',
                },
                {
                  from: 71,
                  to: 100,
                  color: '#EF4444',
                  name: 'High Gap',
                },
              ],
            },
          },
        },
      };
    },
  },
};
</script>
```

---

## Pattern 8: RangeBar (Timeline) Chart

**Use case**: Gifting strategy timeline showing PETs and CLTs

```vue
<template>
  <div class="timeline-chart">
    <h3 class="text-lg font-semibold mb-4">{{ title }}</h3>
    <apexchart
      type="rangeBar"
      :options="chartOptions"
      :series="series"
      height="400"
    ></apexchart>
  </div>
</template>

<script>
export default {
  name: 'TimelineChart',

  props: {
    title: {
      type: String,
      required: true,
    },
    data: {
      type: Array,
      required: true,
      // Example: [{ name: 'Gift 1', data: [{ x: 'PET', y: [startDate, endDate] }] }]
    },
  },

  computed: {
    series() {
      return this.data;
    },

    chartOptions() {
      return {
        chart: {
          type: 'rangeBar',
          height: 400,
        },
        plotOptions: {
          bar: {
            horizontal: true,
            rangeBarGroupRows: true,
          },
        },
        colors: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444'],
        xaxis: {
          type: 'datetime',
        },
        yaxis: {
          show: true,
        },
        tooltip: {
          custom: ({ seriesIndex, dataPointIndex, w }) => {
            const data = w.config.series[seriesIndex].data[dataPointIndex];
            return `<div class="p-2">
              <strong>${data.x}</strong><br/>
              ${new Date(data.y[0]).toLocaleDateString()} - ${new Date(data.y[1]).toLocaleDateString()}
            </div>`;
          },
        },
      };
    },
  },
};
</script>
```

---

## Chart Component Best Practices

### 1. Responsive Design

Make charts responsive:

```vue
<apexchart
  :options="chartOptions"
  :series="series"
  height="350"
  :width="chartWidth"
></apexchart>
```

```javascript
computed: {
  chartWidth() {
    if (window.innerWidth < 640) return '100%';
    if (window.innerWidth < 1024) return '90%';
    return '100%';
  },
}
```

### 2. Loading State

Show loading indicator while data loads:

```vue
<div v-if="loading" class="flex justify-center items-center h-64">
  <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
</div>
<apexchart v-else :options="chartOptions" :series="series"></apexchart>
```

### 3. Empty State

Handle no data gracefully:

```vue
<div v-if="!hasData" class="text-center py-12 text-gray-500">
  <p>No data available</p>
</div>
<apexchart v-else :options="chartOptions" :series="series"></apexchart>
```

### 4. Formatting Consistency

Use consistent formatting across all charts:

```javascript
methods: {
  formatCurrency(value) {
    return new Intl.NumberFormat('en-GB', {
      style: 'currency',
      currency: 'GBP',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(value);
  },

  formatPercent(value) {
    return `${value.toFixed(1)}%`;
  },

  formatDate(value) {
    return new Date(value).toLocaleDateString('en-GB');
  },
}
```

### 5. Color Consistency

Use Fynla color scheme (traffic light system):

```javascript
const Fynla_COLORS = {
  primary: '#3B82F6',    // Blue
  success: '#10B981',    // Green
  warning: '#F59E0B',    // Amber
  danger: '#EF4444',     // Red
  purple: '#8B5CF6',
  pink: '#EC4899',
};
```

### 6. Tooltips

Provide informative tooltips:

```javascript
tooltip: {
  y: {
    formatter: (value) => this.formatCurrency(value),
    title: {
      formatter: (seriesName) => `${seriesName}:`,
    },
  },
}
```

### 7. Export Functionality

Enable chart exports:

```javascript
chart: {
  toolbar: {
    show: true,
    tools: {
      download: true,
      zoom: true,
      pan: true,
      reset: true,
    },
    export: {
      csv: {
        filename: 'fps_chart_data',
      },
      svg: {
        filename: 'fps_chart',
      },
      png: {
        filename: 'fps_chart',
      },
    },
  },
}
```
