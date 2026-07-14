import { Fragment, useCallback, useEffect, useState } from 'react'
import { Button, Form, Alert, Spinner, Modal } from 'react-bootstrap'
import { listRentalRooms, listRentalBookings, cancelRentalBooking, releaseRentalRecurrence } from '../../api/rentals'
import { parseApiError } from '../../utils/apiError'
import ReasonModal from '../Appointments/ReasonModal'
import RentalBookingFormModal from './RentalBookingFormModal'
import RentalRecurrenceFormModal from './RentalRecurrenceFormModal'

// Linhas de hora em hora, das 08:00 às 20:00 (a última linha cobre 20h-21h, o avulso)
const HOURS = Array.from({ length: 13 }, (_, i) => 8 + i)

const PERIOD_LABELS = { manha: 'Manhã', tarde: 'Tarde', noite: 'Noite', avulso: 'Avulso' }

const DAY_LABELS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']

function toDateKey(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

// Domingo da semana corrente + deslocamento em semanas
function weekStart(weekOffset) {
  const now = new Date()
  const start = new Date(now.getFullYear(), now.getMonth(), now.getDate() - now.getDay() + weekOffset * 7)
  return start
}

// A que bloco fechado uma hora pertence — usado pra saber se o clique num
// horário livre deve oferecer escolha de período ou já ser avulso direto
// (08-11→manhã, 12-15→tarde, 16-19→noite, 20→avulso)
function periodForHour(hour) {
  if (hour < 12) return 'manha'
  if (hour < 16) return 'tarde'
  if (hour < 20) return 'noite'
  return 'avulso'
}

function hourOf(datetime) {
  return Number(datetime.slice(11, 13))
}

export default function RoomScheduleTab({ showToast }) {
  const [rooms, setRooms] = useState([])
  const [roomId, setRoomId] = useState('')
  const [weekOffset, setWeekOffset] = useState(0)
  const [bookings, setBookings] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const [cancelling, setCancelling] = useState(null)
  const [releasing, setReleasing] = useState(null) // reserva recorrente a liberar
  const [choosingSlot, setChoosingSlot] = useState(null) // { date, hour, period } — bloco livre, aguardando escolha avulsa/fixa
  const [creatingSlot, setCreatingSlot] = useState(null) // { date, period, hour } — reserva avulsa (esporádica, hora específica)
  const [creatingRecurrence, setCreatingRecurrence] = useState(null) // { date, period } — sublocação fixa (bloco inteiro)

  useEffect(() => {
    listRentalRooms(true).then((data) => {
      setRooms(data)
      if (data.length > 0) setRoomId(String(data[0].id))
    }).catch(() => {})
  }, [])

  const start = weekStart(weekOffset)
  const days = Array.from({ length: 7 }, (_, i) => {
    const d = new Date(start)
    d.setDate(start.getDate() + i)
    return d
  })

  const load = useCallback(async () => {
    if (!roomId) return
    setLoading(true)
    setError('')
    try {
      const endDate = new Date(start)
      endDate.setDate(start.getDate() + 6)
      const data = await listRentalBookings(toDateKey(start), toDateKey(endDate))
      setBookings(data.filter((b) => String(b.rental_room_id ?? b.room?.id) === roomId && b.status === 'scheduled'))
    } catch (err) {
      setError(parseApiError(err))
    } finally {
      setLoading(false)
    }
  }, [roomId, weekOffset]) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => { load() }, [load])

  // Reserva (se houver) que cobre essa hora específica desse dia — funciona
  // tanto pra bloco de período (4h) quanto pra avulso (1h), sem precisar
  // tratar os dois casos separadamente: é só checar se a hora cai no intervalo
  const findBookingForHour = (dateKey, hour) =>
    bookings.find((b) => {
      if (b.booking_date !== dateKey) return false
      const startHour = hourOf(b.start_time)
      const endHour = hourOf(b.end_time)
      return hour >= startHour && hour < endHour
    })

  const handleCellClick = (dateKey, hour) => {
    const booking = findBookingForHour(dateKey, hour)
    if (booking) {
      if (booking.is_recurring) setReleasing(booking)
      else setCancelling(booking)
      return
    }

    const blockPeriod = periodForHour(hour)
    if (blockPeriod === 'avulso') {
      setCreatingSlot({ date: dateKey, period: 'avulso', hour })
    } else {
      setChoosingSlot({ date: dateKey, hour, period: blockPeriod })
    }
  }

  const handleCancel = (reason) =>
    cancelRentalBooking(cancelling.id, reason)
      .then(() => { showToast('Reserva cancelada'); load() })
      .catch((err) => showToast(parseApiError(err), 'danger'))

  const handleRelease = (reason) =>
    releaseRentalRecurrence(releasing.rental_recurrence_id, reason)
      .then(() => { showToast('Sublocação fixa liberada'); load() })
      .catch((err) => showToast(parseApiError(err), 'danger'))

  const weekLabel = `${days[0].toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })} — ${days[6].toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })}`

  return (
    <>
      <div className="patients-toolbar">
        <Form.Select style={{ maxWidth: 220 }} value={roomId} onChange={(e) => setRoomId(e.target.value)}>
          {rooms.map((r) => (
            <option key={r.id} value={r.id}>{r.name}</option>
          ))}
        </Form.Select>

        <Button size="sm" variant="outline-secondary" onClick={() => setWeekOffset((v) => v - 1)}>← Semana anterior</Button>
        <span className="fw-bold">{weekLabel}</span>
        <Button size="sm" variant="outline-secondary" onClick={() => setWeekOffset((v) => v + 1)}>Próxima semana →</Button>
        <Button size="sm" variant="outline-secondary" className="ms-auto" onClick={() => setWeekOffset(0)}>Hoje</Button>
      </div>

      {error && <Alert variant="danger" onClose={() => setError('')} dismissible>{error}</Alert>}

      {!roomId ? (
        <p className="text-muted text-center py-4">Cadastre uma sala para ver a agenda.</p>
      ) : loading ? (
        <div className="text-center py-4"><Spinner animation="border" size="sm" /></div>
      ) : (
        <div className="room-schedule">
          <div className="room-schedule-grid">
            <div className="room-schedule-corner" />
            {days.map((d) => (
              <div key={toDateKey(d)} className="room-schedule-day-header">
                <span>{DAY_LABELS[d.getDay()]}</span>
                <span className="room-schedule-day-date">{d.getDate()}</span>
              </div>
            ))}

            {HOURS.map((hour) => (
              <Fragment key={hour}>
                <div className="room-schedule-time-label">
                  <span>{String(hour).padStart(2, '0')}:00</span>
                </div>

                {days.map((d) => {
                  const dateKey = toDateKey(d)
                  const booking = findBookingForHour(dateKey, hour)
                  const isFree = !booking
                  const isPeriod = booking && booking.period !== 'avulso'

                  return (
                    <button
                      type="button"
                      key={`${hour}-${dateKey}`}
                      className={`room-schedule-cell ${isFree ? 'is-free' : isPeriod ? 'is-period' : 'is-booked'}`}
                      onClick={() => handleCellClick(dateKey, hour)}
                      title={
                        booking
                          ? `${isPeriod ? `Período (${PERIOD_LABELS[booking.period]})${booking.is_recurring ? ' — fixo' : ''}` : 'Avulso'} — ${booking.tenant?.name} — clique para ${booking.is_recurring ? 'liberar' : 'cancelar'}`
                          : 'Livre — clique para reservar'
                      }
                    >
                      {isFree ? (
                        'Livre'
                      ) : (
                        <>
                          <span className="room-schedule-cell-tag">{isPeriod ? 'Período' : 'Avulso'}</span>
                          <span className="room-schedule-cell-name">{booking.tenant?.name ?? 'Reservado'}</span>
                        </>
                      )}
                    </button>
                  )
                })}
              </Fragment>
            ))}
          </div>

          <div className="room-schedule-legend">
            <span><span className="room-schedule-legend-dot is-free" /> Livre</span>
            <span><span className="room-schedule-legend-dot is-period" /> Período (fixo)</span>
            <span><span className="room-schedule-legend-dot is-booked" /> Avulso</span>
          </div>
        </div>
      )}

      {/* Escolha ao clicar num horário livre de bloco (manhã/tarde/noite): avulsa ou fixa */}
      <Modal show={!!choosingSlot} onHide={() => setChoosingSlot(null)} centered>
        <Modal.Header closeButton>
          <Modal.Title>Reservar {choosingSlot && PERIOD_LABELS[choosingSlot.period]}</Modal.Title>
        </Modal.Header>
        <Modal.Body>
          <p className="text-muted mb-3">
            {choosingSlot && new Date(`${choosingSlot.date}T00:00:00`).toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long' })}
          </p>
          <div className="rentals-slot-choice">
            <button
              type="button"
              className="rentals-slot-choice-option"
              onClick={() => {
                setCreatingSlot({ date: choosingSlot.date, period: 'avulso', hour: choosingSlot.hour })
                setChoosingSlot(null)
              }}
            >
              <span className="rentals-slot-choice-title">Sublocação avulsa</span>
              <span className="rentals-slot-choice-desc">
                {choosingSlot && `Só das ${String(choosingSlot.hour).padStart(2, '0')}:00 às ${String(choosingSlot.hour + 1).padStart(2, '0')}:00, nesta data`} — cobrada no fechamento do mês
              </span>
            </button>
            <button
              type="button"
              className="rentals-slot-choice-option is-highlight"
              onClick={() => {
                setCreatingRecurrence({ date: choosingSlot.date, period: choosingSlot.period })
                setChoosingSlot(null)
              }}
            >
              <span className="rentals-slot-choice-title">Sublocação fixa</span>
              <span className="rentals-slot-choice-desc">O período {choosingSlot && PERIOD_LABELS[choosingSlot.period]} inteiro, toda semana — mensalidade paga antecipada</span>
            </button>
          </div>
        </Modal.Body>
      </Modal>

      <RentalBookingFormModal
        show={!!creatingSlot}
        defaultDate={creatingSlot?.date}
        defaultRoomId={roomId}
        defaultPeriod={creatingSlot?.period}
        defaultHour={creatingSlot?.hour}
        onClose={() => setCreatingSlot(null)}
        onCreated={() => { showToast('Reserva criada com sucesso'); load() }}
      />

      <RentalRecurrenceFormModal
        show={!!creatingRecurrence}
        defaultDate={creatingRecurrence?.date}
        defaultRoomId={roomId}
        defaultPeriod={creatingRecurrence?.period}
        onClose={() => setCreatingRecurrence(null)}
        onCreated={(result) => {
          showToast(`Sublocação fixa criada: ${result.bookings_created} sessão(ões) geradas`)
          load()
        }}
      />

      <ReasonModal
        show={!!cancelling}
        title="Cancelar reserva"
        required={false}
        onClose={() => setCancelling(null)}
        onConfirm={handleCancel}
      />

      <ReasonModal
        show={!!releasing}
        title="Liberar sublocação fixa"
        required={false}
        onClose={() => setReleasing(null)}
        onConfirm={handleRelease}
      />
    </>
  )
}
