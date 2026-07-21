import { useEffect, useRef, useState } from 'react'
import { Button } from 'react-bootstrap'
import { statusClassName } from './statusMeta'
import AppointmentExpandedContent from './AppointmentExpandedContent'

const DAY_LABELS = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo']

// Janela padrão de horários exibidos — estendida dinamicamente se algum
// agendamento da semana começar ou terminar fora desse intervalo (ver `hours` abaixo)
const DEFAULT_MIN_HOUR = 7
const DEFAULT_MAX_HOUR = 20

// Altura de uma hora na grade — todo o posicionamento (topo/altura dos cards)
// é derivado deste valor, em pixels por minuto
const HOUR_HEIGHT = 64
const PX_PER_MINUTE = HOUR_HEIGHT / 60

// Abaixo dessas alturas o card não tem espaço para todas as linhas de texto —
// vai reduzindo o conteúdo (ver renderEventContent) mas nunca fica menor que isso
const MIN_EVENT_HEIGHT = 26
const COMPACT_HEIGHT_THRESHOLD = 46 // esconde o procedimento
const TINY_HEIGHT_THRESHOLD = 30    // esconde também o paciente (só horário)

// Largura do painel flutuante de detalhes (overlay) e distância mínima até a borda
const OVERLAY_WIDTH = 320
const OVERLAY_MARGIN = 8

// Paleta pra identificar profissionais na Semana quando há mais de um agendamento
// simultâneo — cores escolhidas pra não colidir com as já usadas por status
// (azul=agendado, verde=confirmado, âmbar=falta, vermelho=perigo/exclusão)
const PROFESSIONAL_COLORS = [
  '#8b5cf6', // violeta
  '#14b8a6', // teal
  '#d946ef', // fúcsia
  '#6366f1', // índigo
  '#0ea5e9', // azul-céu
  '#84cc16', // lima
  '#f43f5e', // rosa
  '#78716c', // grafite
]

function professionalColor(professionalId) {
  if (!professionalId) return null
  return PROFESSIONAL_COLORS[professionalId % PROFESSIONAL_COLORS.length]
}

// Iniciais do profissional pro avatar do card — ignora prefixos de tratamento
// (Dr./Dra.) pra não virar "D" pra todo mundo
const TITLE_PREFIXES = ['dr', 'dra', 'dr.', 'dra.']
function professionalInitials(name) {
  if (!name) return '?'
  const words = name.split(' ').filter((w) => !TITLE_PREFIXES.includes(w.toLowerCase()))
  const source = words.length ? words : name.split(' ')
  return source.slice(0, 2).map((w) => w[0]).join('').toUpperCase()
}

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

function dateKeyOf(datetime) {
  return datetime.slice(0, 10)
}

// Minutos desde a meia-noite, a partir de uma string "YYYY-MM-DD HH:MM:SS"
function minutesOf(datetime) {
  return Number(datetime.slice(11, 13)) * 60 + Number(datetime.slice(14, 16))
}

// Última linha de hora que precisa existir na grade para caber um horário de término
// (ex: termina às 21:00 em ponto não precisa da linha das 21h; términa às 21:30 precisa)
function lastHourNeededFor(endMinutes) {
  return Math.floor((endMinutes - 1) / 60)
}

// =============================================
// LAYOUT DOS EVENTOS DE UM DIA — posição (top/height) pelo horário exato e
// divisão em colunas quando dois ou mais agendamentos se sobrepõem no tempo
// (mesmo algoritmo usado por Google/Outlook/Apple Calendar)
// =============================================
function layoutDayEvents(dayAppointments, minHour) {
  const items = dayAppointments
    .map((apt) => {
      const start = minutesOf(apt.start_time)
      const end = Math.max(minutesOf(apt.end_time), start + 1)
      return { apt, start, end }
    })
    .sort((a, b) => a.start - b.start || a.end - b.end)

  // 1. Agrupa em "clusters" de agendamentos mutuamente sobrepostos
  const clusters = []
  let current = []
  let clusterEnd = -Infinity
  for (const item of items) {
    if (current.length && item.start >= clusterEnd) {
      clusters.push(current)
      current = []
      clusterEnd = -Infinity
    }
    current.push(item)
    clusterEnd = Math.max(clusterEnd, item.end)
  }
  if (current.length) clusters.push(current)

  // 2. Dentro de cada cluster, distribui em colunas (greedy: usa a primeira coluna livre)
  const positioned = []
  for (const cluster of clusters) {
    const columns = [] // guarda o horário de término do último item de cada coluna
    for (const item of cluster) {
      let colIndex = columns.findIndex((endTime) => item.start >= endTime)
      if (colIndex === -1) {
        colIndex = columns.length
        columns.push(item.end)
      } else {
        columns[colIndex] = item.end
      }
      positioned.push({ ...item, colIndex, colCount: null })
    }
    for (const item of positioned.slice(-cluster.length)) {
      item.colCount = columns.length
    }
  }

  const minMinutes = minHour * 60

  return positioned.map(({ apt, start, end, colIndex, colCount }) => ({
    apt,
    top: (start - minMinutes) * PX_PER_MINUTE,
    height: Math.max((end - start) * PX_PER_MINUTE, MIN_EVENT_HEIGHT),
    leftPct: (colIndex / colCount) * 100,
    widthPct: 100 / colCount,
  }))
}

// =============================================
// CALENDÁRIO SEMANAL — visão tipo Google Calendar
// (mesma lógica de dados da lista; só muda a apresentação)
//
// Os cards são posicionados de forma absoluta sobre a grade de horários —
// a posição vertical e a altura refletem o horário exato de início e a
// duração real do agendamento (inclusive horários fracionados como 08:30,
// 09:15 etc.), igual a calendários como Google/Outlook/Apple.
//
// Clicar num agendamento abre os detalhes num painel flutuante (overlay)
// ancorado ao card — a grade (dias, horários, outros agendamentos) nunca
// muda de tamanho ou posição, diferente da Agenda em Lista que expande
// o próprio card in-place
// =============================================

export default function WeekCalendar({
  appointments, weekOffset, onPrevWeek, onNextWeek, onToday,
  expandedId, onToggle, onEdit, onConfirm, onComplete, onNoShow, onDelete,
}) {
  const wrapperRef = useRef(null)
  const cardRefs = useRef({})
  const headerScrollRef = useRef(null)
  const bodyScrollRef = useRef(null)
  const [overlayRect, setOverlayRect] = useState(null)

  // O cabeçalho (dias da semana) e o corpo (grade de horários) são dois
  // elementos horizontais separados — só assim o cabeçalho consegue ficar
  // sticky de verdade em relação ao scroll real da página (.app-main) em vez
  // de precisar de uma "viewport" interna só para isso. Sincronizamos o
  // scroll horizontal de um pro outro via JS para as colunas continuarem
  // alinhadas quando a grade é mais larga que a tela.
  const handleBodyScroll = () => {
    if (headerScrollRef.current && bodyScrollRef.current) {
      headerScrollRef.current.scrollLeft = bodyScrollRef.current.scrollLeft
    }
  }

  // "Relógio" da grade — só existe pra forçar recálculo da linha do horário
  // atual a cada minuto; o resto do componente não depende disso
  const [now, setNow] = useState(() => new Date())
  useEffect(() => {
    const id = setInterval(() => setNow(new Date()), 60_000)
    return () => clearInterval(id)
  }, [])

  const monday = mondayOf(weekOffset)
  const days = Array.from({ length: 7 }, (_, i) => {
    const d = new Date(monday)
    d.setDate(monday.getDate() + i)
    return d
  })

  const weekDateKeys = days.map(toDateKey)
  const weekAppointments = appointments.filter((apt) => weekDateKeys.includes(dateKeyOf(apt.start_time)))
  const expandedAppointment = weekAppointments.find((apt) => apt.id === expandedId) ?? null

  // A identificação visual do profissional (faixa colorida + iniciais) só aparece
  // quando faz sentido — ou seja, quando há mais de um profissional na semana
  // exibida. Numa agenda de um profissional só, ela só poluiria a interface à toa
  const showProfessionalIdentity = new Set(weekAppointments.map((apt) => apt.professional?.id)).size > 1

  // Janela de horas exibida — estendida se algum agendamento começar antes ou
  // terminar depois da janela padrão (07h–20h), pra nunca cortar um card
  const hours = (() => {
    let minH = DEFAULT_MIN_HOUR
    let maxH = DEFAULT_MAX_HOUR
    for (const apt of weekAppointments) {
      minH = Math.min(minH, Math.floor(minutesOf(apt.start_time) / 60))
      maxH = Math.max(maxH, lastHourNeededFor(minutesOf(apt.end_time)))
    }
    return Array.from({ length: maxH - minH + 1 }, (_, i) => minH + i)
  })()

  const gridHeight = hours.length * HOUR_HEIGHT

  // Linha do horário atual — só aparece no dia de hoje (se estiver na semana
  // exibida) e só dentro da janela de horas visível na grade
  const todayKey = toDateKey(now)
  const nowMinutes = now.getHours() * 60 + now.getMinutes()
  const gridStartMinutes = hours[0] * 60
  const gridEndMinutes = (hours[hours.length - 1] + 1) * 60
  const showNowLine = weekDateKeys.includes(todayKey)
    && nowMinutes >= gridStartMinutes
    && nowMinutes < gridEndMinutes
  const nowTop = (nowMinutes - gridStartMinutes) * PX_PER_MINUTE

  const weekLabel = `${days[0].toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })} — ${days[6].toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })}`

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

      <div className="week-calendar-header-sticky">
        <div className="week-calendar-header-scroll" ref={headerScrollRef}>
          <div className="week-calendar-grid week-calendar-header-grid">
            <div className="week-calendar-corner" />
            {days.map((d, dayIndex) => {
              const isToday = toDateKey(d) === todayKey
              return (
                <div
                  key={toDateKey(d)}
                  className={`week-calendar-day-header${isToday ? ' is-today' : ''}`}
                >
                  <span>{DAY_LABELS[dayIndex]}</span>
                  <span className="week-calendar-day-date">{d.getDate()}</span>
                </div>
              )
            })}
          </div>
        </div>
      </div>

      <div className="week-calendar-body-scroll" ref={bodyScrollRef} onScroll={handleBodyScroll}>
        <div className="week-calendar-grid week-calendar-body-grid">
          <div className="week-calendar-time-col" style={{ height: gridHeight }}>
            {hours.map((hour) => (
              <div key={hour} className="week-calendar-hour-row">
                <span className="week-calendar-time-label">{String(hour).padStart(2, '0')}:00</span>
              </div>
            ))}
          </div>

          {days.map((d) => {
            const dateKey = toDateKey(d)
            const isToday = dateKey === toDateKey(new Date())
            const dayAppointments = weekAppointments.filter((apt) => dateKeyOf(apt.start_time) === dateKey)
            const positionedEvents = layoutDayEvents(dayAppointments, hours[0])

            return (
              <div
                key={dateKey}
                className={`week-calendar-day-col${isToday ? ' is-today' : ''}`}
                style={{ height: gridHeight }}
              >
                {hours.map((hour) => (
                  <div key={hour} className="week-calendar-hour-row" />
                ))}

                {isToday && showNowLine && (
                  <div className="week-calendar-now-line" style={{ top: nowTop }} />
                )}

                {positionedEvents.map(({ apt, top, height, leftPct, widthPct }) => {
                  const isExpanded = expandedId === apt.id
                  const isCompact = height < COMPACT_HEIGHT_THRESHOLD
                  const isTiny = height < TINY_HEIGHT_THRESHOLD
                  const timeRange = `${apt.start_time.slice(11, 16)}–${apt.formatted_end}`
                  const profColor = showProfessionalIdentity ? professionalColor(apt.professional?.id) : null

                  return (
                    <div
                      key={apt.id}
                      ref={(el) => { cardRefs.current[apt.id] = el }}
                      className={`week-calendar-appointment ${statusClassName(apt.status)}${isExpanded ? ' is-expanded' : ''}${isCompact ? ' is-compact' : ''}${profColor ? ' has-professional-color' : ''}`}
                      style={{
                        top,
                        height,
                        left: `${leftPct}%`,
                        width: `calc(${widthPct}% - 4px)`,
                        '--prof-color': profColor ?? 'transparent',
                      }}
                      title={showProfessionalIdentity
                        ? `${apt.professional?.name} · ${apt.patient?.name} · ${apt.service?.name} · ${timeRange}`
                        : `${apt.patient?.name} · ${apt.service?.name} · ${timeRange}`}
                      onClick={() => onToggle(apt.id)}
                      role="button"
                      tabIndex={0}
                      aria-expanded={isExpanded}
                      onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onToggle(apt.id) } }}
                    >
                      {profColor && !isTiny && (
                        <span className="week-calendar-appointment-avatar" style={{ background: profColor }}>
                          {professionalInitials(apt.professional?.name)}
                        </span>
                      )}
                      {!isTiny && <p className="week-calendar-appointment-patient">{apt.patient?.name}</p>}
                      {!isCompact && <p className="week-calendar-appointment-service">{apt.service?.name}</p>}
                      <p className="week-calendar-appointment-time">{isTiny ? `${apt.patient?.name} · ${timeRange}` : timeRange}</p>
                    </div>
                  )
                })}
              </div>
            )
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
              onNoShow={onNoShow}
              onDelete={onDelete}
            />
          </div>
        </>
      )}
    </div>
  )
}
