const WEEKDAYS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']
const MONTH_NAMES = [
  'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
  'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
]

function toDateKey(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

// Gera os dias exibidos na grade (inclui dias do mês anterior/seguinte para completar as semanas)
function buildGridDays(year, month) {
  const firstOfMonth = new Date(year, month, 1)
  const startOffset = firstOfMonth.getDay() // 0 = domingo

  const gridStart = new Date(year, month, 1 - startOffset)

  return Array.from({ length: 42 }, (_, i) => {
    const date = new Date(gridStart)
    date.setDate(gridStart.getDate() + i)
    return date
  })
}

export default function MonthCalendar({ year, month, countsByDate, selectedDate, onSelectDate, onPrevMonth, onNextMonth, onToday }) {
  const days = buildGridDays(year, month)
  const todayKey = toDateKey(new Date())

  return (
    <div className="month-calendar">
      <div className="month-calendar-header">
        <h5 className="month-calendar-title">{MONTH_NAMES[month]} {year}</h5>
        <div className="month-calendar-nav">
          <Button icon="◀" label="Mês anterior" onClick={onPrevMonth} />
          <button className="month-calendar-today" onClick={onToday}>Hoje</button>
          <Button icon="▶" label="Próximo mês" onClick={onNextMonth} />
        </div>
      </div>

      <div className="month-calendar-weekdays">
        {WEEKDAYS.map((day) => (
          <div key={day} className="month-calendar-weekday">{day}</div>
        ))}
      </div>

      <div className="month-calendar-grid">
        {days.map((date) => {
          const key = toDateKey(date)
          const isCurrentMonth = date.getMonth() === month
          const isToday = key === todayKey
          const isSelected = key === selectedDate
          const count = countsByDate[key] ?? 0

          return (
            <button
              key={key}
              className={[
                'month-calendar-day',
                !isCurrentMonth && 'is-outside',
                isToday && 'is-today',
                isSelected && 'is-selected',
              ].filter(Boolean).join(' ')}
              onClick={() => onSelectDate(key)}
            >
              <span className="month-calendar-day-number">{date.getDate()}</span>
              {count > 0 && <span className="month-calendar-day-dot">{count}</span>}
            </button>
          )
        })}
      </div>
    </div>
  )
}

function Button({ icon, label, onClick }) {
  return (
    <button className="month-calendar-arrow" onClick={onClick} aria-label={label} title={label}>
      {icon}
    </button>
  )
}
