export type CalendarDayCell = {
  date: Date
  inMonth: boolean
  isToday: boolean
  key: string
}

export function startOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth(), 1)
}

export function endOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth() + 1, 0, 23, 59, 59, 999)
}

export function startOfWeek(date: Date): Date {
  const d = new Date(date)
  d.setDate(d.getDate() - d.getDay())
  d.setHours(0, 0, 0, 0)
  return d
}

export function endOfWeek(date: Date): Date {
  const d = startOfWeek(date)
  d.setDate(d.getDate() + 6)
  d.setHours(23, 59, 59, 999)
  return d
}

export function addMonths(date: Date, months: number): Date {
  return new Date(date.getFullYear(), date.getMonth() + months, 1)
}

export function addWeeks(date: Date, weeks: number): Date {
  const d = new Date(date)
  d.setDate(d.getDate() + weeks * 7)
  return d
}

export function toDateKey(date: Date): string {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

export function buildMonthGrid(anchor: Date): CalendarDayCell[] {
  const first = startOfMonth(anchor)
  const last = endOfMonth(anchor)
  const gridStart = startOfWeek(first)
  const cells: CalendarDayCell[] = []
  const todayKey = toDateKey(new Date())

  for (let i = 0; i < 42; i++) {
    const date = new Date(gridStart)
    date.setDate(gridStart.getDate() + i)
    const key = toDateKey(date)
    cells.push({
      date,
      inMonth: date.getMonth() === anchor.getMonth(),
      isToday: key === todayKey,
      key,
    })
    if (i >= 27 && date >= last && date.getDay() === 6) break
  }

  return cells
}

export function buildWeekDays(anchor: Date): CalendarDayCell[] {
  const start = startOfWeek(anchor)
  const todayKey = toDateKey(new Date())
  return Array.from({ length: 7 }, (_, i) => {
    const date = new Date(start)
    date.setDate(start.getDate() + i)
    const key = toDateKey(date)
    return {
      date,
      inMonth: true,
      isToday: key === todayKey,
      key,
    }
  })
}

export function toIsoDate(date: Date): string {
  return date.toISOString()
}
