import type { ApexOptions } from 'apexcharts'
import type { DashboardActivityPoint, DashboardChartCount } from '@/types'

export const CHART = {
  teal: '#0A4F5E',
  tealLight: '#4DAABA',
  gold: '#B1915A',
  goldLight: '#D4B87A',
  green: '#16A34A',
  greenLight: '#86EFAC',
  orange: '#EA580C',
  orangeLight: '#FDBA74',
  purple: '#7C6BF0',
  purpleLight: '#C4B5FD',
  muted: '#E6E9EC',
} as const

export function monthOverMonthTrend(points: DashboardActivityPoint[]): {
  pct: number
  positive: boolean
} {
  if (points.length < 2) return { pct: 0, positive: true }
  const current = points[points.length - 1]?.count ?? 0
  const previous = points[points.length - 2]?.count ?? 0
  if (previous === 0) {
    return { pct: current > 0 ? 100 : 0, positive: current >= 0 }
  }
  const pct = Math.round(((current - previous) / previous) * 100)
  return { pct: Math.abs(pct), positive: pct >= 0 }
}

export function aggregateCaseStatusGroups(rows: DashboardChartCount[]): {
  label: string
  count: number
  color: string
}[] {
  const closed = new Set(['closed', 'archived', 'settled'])
  const pending = new Set(['awaiting_client_response'])

  let open = 0
  let pendingCount = 0
  let closedCount = 0

  for (const row of rows) {
    const status = row.status.toLowerCase()
    if (closed.has(status)) closedCount += row.count
    else if (pending.has(status)) pendingCount += row.count
    else open += row.count
  }

  return [
    { label: 'Open', count: open, color: CHART.teal },
    { label: 'Pending', count: pendingCount, color: CHART.gold },
    { label: 'Closed', count: closedCount, color: CHART.green },
  ].filter((g) => g.count > 0)
}

export function sparklineOptions(color: string, height = 48): ApexOptions {
  return {
    chart: {
      type: 'area',
      sparkline: { enabled: true },
      animations: { enabled: true, speed: 400 },
    },
    stroke: { curve: 'smooth', width: 2, colors: [color] },
    fill: {
      type: 'gradient',
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.35,
        opacityTo: 0.05,
        stops: [0, 100],
        colorStops: [
          { offset: 0, color, opacity: 0.35 },
          { offset: 100, color, opacity: 0.02 },
        ],
      },
    },
    tooltip: { enabled: false },
    grid: { padding: { top: 4, bottom: 0, left: 0, right: 0 } },
  }
}

export function miniBarOptions(colors: string[], height = 56): ApexOptions {
  return {
    chart: {
      type: 'bar',
      sparkline: { enabled: true },
      animations: { enabled: true, speed: 400 },
    },
    plotOptions: {
      bar: {
        borderRadius: 4,
        columnWidth: '55%',
        distributed: true,
      },
    },
    colors,
    dataLabels: { enabled: false },
    tooltip: { enabled: false },
    grid: { padding: { top: 0, bottom: 0, left: 4, right: 4 } },
    xaxis: { labels: { show: false }, axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: { labels: { show: false } },
  }
}

export function radialGaugeOptions(color: string, height = 100): ApexOptions {
  return {
    chart: { type: 'radialBar', sparkline: { enabled: true }, animations: { speed: 400 } },
    plotOptions: {
      radialBar: {
        hollow: { size: '58%' },
        track: { background: CHART.muted, strokeWidth: '100%' },
        dataLabels: {
          name: { show: false },
          value: {
            fontSize: '14px',
            fontWeight: 700,
            color: '#1F2937',
            offsetY: 4,
            formatter: (val: number) => `${Math.round(val)}%`,
          },
        },
      },
    },
    colors: [color],
    stroke: { lineCap: 'round' },
  }
}

export function baseAreaChartOptions(categories: string[]): ApexOptions {
  return {
    chart: {
      type: 'area',
      toolbar: { show: false },
      zoom: { enabled: false },
      fontFamily: 'inherit',
    },
    colors: [CHART.teal],
    stroke: { curve: 'smooth', width: 3 },
    fill: {
      type: 'gradient',
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.4,
        opacityTo: 0.05,
        stops: [0, 90, 100],
        colorStops: [
          { offset: 0, color: CHART.tealLight, opacity: 0.45 },
          { offset: 100, color: CHART.teal, opacity: 0.03 },
        ],
      },
    },
    dataLabels: { enabled: false },
    grid: {
      borderColor: CHART.muted,
      strokeDashArray: 4,
      padding: { left: 8, right: 8 },
    },
    xaxis: {
      categories,
      axisBorder: { show: false },
      axisTicks: { show: false },
      labels: { style: { colors: '#6B7280', fontSize: '12px' } },
    },
    yaxis: {
      labels: {
        style: { colors: '#6B7280', fontSize: '12px' },
        formatter: (val: number) => String(Math.round(val)),
      },
    },
    tooltip: {
      theme: 'light',
      y: { formatter: (val: number) => `${val} matters` },
    },
  }
}

export function donutChartOptions(labels: string[], colors: string[]): ApexOptions {
  return {
    chart: { type: 'donut', fontFamily: 'inherit' },
    colors,
    labels,
    stroke: { width: 2, colors: ['#ffffff'] },
    dataLabels: { enabled: false },
    legend: {
      position: 'bottom',
      fontSize: '13px',
      markers: { size: 8, offsetX: -2 },
      itemMargin: { horizontal: 10, vertical: 4 },
    },
    plotOptions: {
      pie: {
        donut: {
          size: '72%',
          labels: {
            show: true,
            name: { show: true, fontSize: '13px', color: '#6B7280' },
            value: {
              show: true,
              fontSize: '22px',
              fontWeight: 700,
              color: '#1F2937',
              formatter: (val: string) => val,
            },
            total: {
              show: true,
              label: 'Total',
              fontSize: '12px',
              color: '#6B7280',
              formatter: (w) => {
                const total = w.globals.seriesTotals.reduce((a: number, b: number) => a + b, 0)
                return String(total)
              },
            },
          },
        },
      },
    },
  }
}

export function semiGaugeOptions(labels: string[], colors: string[]): ApexOptions {
  return {
    chart: { type: 'donut', fontFamily: 'inherit' },
    colors,
    labels,
    stroke: { width: 2, colors: ['#ffffff'] },
    dataLabels: { enabled: false },
    legend: {
      position: 'bottom',
      fontSize: '12px',
      markers: { size: 7 },
    },
    plotOptions: {
      pie: {
        startAngle: -90,
        endAngle: 90,
        offsetY: 8,
        donut: {
          size: '68%',
          labels: {
            show: true,
            name: { show: false },
            value: { show: false },
            total: {
              show: true,
              label: 'Filings',
              fontSize: '12px',
              color: '#6B7280',
              formatter: (w) => {
                const total = w.globals.seriesTotals.reduce((a: number, b: number) => a + b, 0)
                return String(total)
              },
            },
          },
        },
      },
    },
  }
}
