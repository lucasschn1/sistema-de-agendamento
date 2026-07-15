import { useEffect, useRef, useState } from 'react'
import { Button } from 'react-bootstrap'
import { statusLabel, statusClassName } from './statusMeta'
import AppointmentExpandedContent from './AppointmentExpandedContent'

const DAY_LABELS = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo']

// Janela padrão de horários exibidos — estendida dinamicamente se algum
// agendamento da semana cair fora desse intervalo (ver hoursRange abaixo)
const DEFAULT_MIN_HOUR = 7
const DEFAULT_MAX_HOUR = 20

// Largura do painel flutuante de detalhes (overlay) e distância mínima até a borda
const OVERLAY_WIDTH = 320
const OVERLAY_MARGIN = 8

function toDateKey(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

// Segunda-feira da semana corrente + deslocamento em semanas — exportada para
// que o componente pai possa buscar os agendamentos exatamente dessa semana
export function mondayOf(weekOffset) {
  const now = new Date()
  const day = now.getDay() // 0 (dom) .. 6 (sáb)
  const diffToMonday = day === 0 ? -6 : 1 - day
  return new Date(now.getFullYear(), now.getMonth(), now.getDate() + diffToMonday + weekOffset * 7)
}

function hourOf(datetime) {
  return Number(datetime.slice(11, 13))
}

function dateKeyOf(datetime) {
  return datetime.slice(0, 10)
}

// =============================================
// CALENDÁRIO SEMANAL — visão tipo Google Calendar
// (mesma lógica de dados da lista; só muda a apresentação)
//
// Clicar num agendamento abre os detalhes num painel flutuante (overlay)
// ancorado ao card — a grade (dias, horários, outros agendamentos) nunca
// muda de tamanho ou posição, diferente da Agenda em Lista que expande
// o próprio card in-place
// =============================================

export default function WeekCalendar({
  appointments, weekOffset, onPrevWeek, onNextWeek, onToday,
  expandedId, onToggle, onEdit, onConfirm, onComplete, onCancel, onNoShow, onCancelRecurrence, onDelete,
}) {
  const wrapperRef = useRef(null)
  const cardRefs = useRef({})
  const [overlayRect, setOverlayRect] = useState(null)

  const monday = mondayOf(weekOffset)
  const days = Array.from({ length: 7 }, (_, i) => {
    const d = new Date(monday)
    d.setDate(monday.getDate() + i)
    return d
  })

  const weekDateKeys = days.map(toDateKey)
  const weekAppointments = appointments.filter((apt) => weekDateKeys.includes(dateKeyOf(apt.start_time)))
  const expandedAppointment = weekAppointments.find((apt) => apt.id === expandedId) ?? null

  const hours = (() => {
    const appointmentHours = weekAppointments.map((apt) => hourOf(apt.start_time))
    const minHour = Math.min(DEFAULT_MIN_HOUR, ...appointmentHours)
    const maxHour = Math.max(DEFAULT_MAX_HOUR, ...appointmentHours)
    return Array.from({ length: maxHour - minHour + 1 }, (_, i) => minHour + i)
  })()

  const weekLabel = `${days[0].toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })} — ${days[6].toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })}`

  const appointmentsFor = (dateKey, hour) =>
    weekAppointments.filter((apt) => dateKeyOf(apt.start_time) === dateKey && hourOf(apt.start_time) === hour)

  // Mede a posição do card clicado (relativa ao próprio calendário) pra ancorar
  // o painel flutuante bem em cima dele, sem afetar a altura das linhas da grade
  useEffect(() => {
    if (!expandedId) {
      setOverlayRect(null)
      return
    }

    const cardEl = cardRefs.current[expandedId]
    const wrapperEl = wrapperRef.current
    if (!cardEl || !wrapperEl) return

    const cardBox = cardEl.getBoundingClientRect()
    const wrapperBox = wrapperEl.getBoundingClientRect()

    let left = cardBox.left - wrapperBox.left
    left = Math.min(left, wrapperBox.width - OVERLAY_WIDTH - OVERLAY_MARGIN)
    left = Math.max(left, OVERLAY_MARGIN)

    setOverlayRect({
      top: cardBox.bottom - wrapperBox.top + 6,
      left,
    })
  }, [expandedId])

  // Fecha com Esc, igual a qualquer overlay/popover
  useEffect(() => {
    if (!expandedId) return
    const handleKeyDown = (e) => { if (e.key === 'Escape') onToggle(expandedId) }
    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [expandedId, onToggle])

  return (
    <div className="week-calendar" ref={wrapperRef}>
      <div className="patients-toolbar">
        <Button size="sm" variant="outline-secondary" onClick={onPrevWeek}>← Semana anterior</Button>
        <span className="fw-bold">{weekLabel}</span>
        <Button size="sm" variant="outline-secondary" onClick={onNextWeek}>Próxima semana →</Button>
        <Button size="sm" variant="outline-secondary" className="ms-auto" onClick={onToday}>Hoje</Button>
      </div>

      <div className="week-calendar-scroll">
        <div className="week-calendar-grid" style={{ gridTemplateRows: `auto repeat(${hours.length}, minmax(64px, auto))` }}>
          <div className="week-calendar-corner" style={{ gridRow: 1, gridColumn: 1 }} />
          {days.map((d, dayIndex) => {
            const isToday = toDateKey(d) === toDateKey(new Date())
            return (
              <div
                key={toDateKey(d)}
                className={`week-calendar-day-header${isToday ? ' is-today' : ''}`}
                style={{ gridRow: 1, gridColumn: dayIndex + 2 }}
              >
                <span>{DAY_LABELS[dayIndex]}</span>
                <span className="week-calendar-day-date">{d.getDate()}</span>
              </div>
            )
          })}

          {hours.map((hour, hourIndex) => (
            <div key={`label-${hour}`} className="week-calendar-time-label" style={{ gridRow: hourIndex + 2, gridColumn: 1 }}>
              <span>{String(hour).padStart(2, '0')}:00</span>
            </div>
          ))}

          {days.map((d, dayIndex) => {
            const dateKey = toDateKey(d)
            return hours.map((hour, hourIndex) => {
              const cellAppointments = appointmentsFor(dateKey, hour)

              return (
                <div
                  key={`${dateKey}-${hour}`}
                  className="week-calendar-cell"
                  style={{ gridRow: hourIndex + 2, gridColumn: dayIndex + 2 }}
                >
                  {cellAppointments.map((apt) => {
                    const isExpanded = expandedId === apt.id
                    return (
                      <div
                        key={apt.id}
                        ref={(el) => { cardRefs.current[apt.id] = el }}
                        className={`week-calendar-appointment ${statusClassName(apt.status)}${isExpanded ? ' is-expanded' : ''}`}
                        onClick={() => onToggle(apt.id)}
                        role="button"
                        tabIndex={0}
                        aria-expanded={isExpanded}
                        onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onToggle(apt.id) } }}
                      >
                        <div className="week-calendar-appointment-header">
                          <span className="week-calendar-appointment-time">{apt.start_time.slice(11, 16)}</span>
                          <span className="week-calendar-appointment-status">
                            {statusLabel(apt.status)}
                          </span>
                        </div>
                        <p className="week-calendar-appointment-patient">{apt.patient?.name}</p>
                        <p className="week-calendar-appointment-service">{apt.service?.name}</p>
                      </div>
                    )
                  })}
                </div>
              )
            })
          })}
        </div>
      </div>

      {expandedAppointment && overlayRect && (
        <>
          <div className="week-calendar-overlay-backdrop" onClick={() => onToggle(expandedAppointment.id)} />
          <div
            className="week-calendar-overlay"
            style={{ top: overlayRect.top, left: overlayRect.left, width: OVERLAY_WIDTH }}
            onClick={(e) => e.stopPropagation()}
          >
            <div className="week-calendar-overlay-header">
              <div>
                <span className="week-calendar-overlay-time">{expandedAppointment.start_time.slice(11, 16)}</span>
                <strong className="week-calendar-overlay-patient">{expandedAppointment.patient?.name}</strong>
              </div>
              <button
                type="button"
                className="week-calendar-overlay-close"
                aria-label="Fechar detalhes"
                onClick={() => onToggle(expandedAppointment.id)}
              >
                ×
              </button>
            </div>

            <AppointmentExpandedContent
              appointment={expandedAppointment}
              onEdit={onEdit}
              onConfirm={onConfirm}
              onComplete={onComplete}
              onCancel={onCancel}
              onNoShow={onNoShow}
              onCancelRecurrence={onCancelRecurrence}
              onDelete={onDelete}
            />
          </div>
        </>
      )}
    </div>
  )
}
