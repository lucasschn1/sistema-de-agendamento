// Barras verticais — tendência ao longo do tempo (ex: faturamento por mês), série única
export default function VerticalBars({ items, color = 'var(--brand)', valueFormatter = (v) => v }) {
  const max = Math.max(...items.map((i) => i.value), 1)

  return (
    <div className="chart-vbars">
      {items.map((item) => {
        const pct = Math.max((item.value / max) * 100, item.value > 0 ? 2 : 0)

        return (
          <div className="chart-vbars-col" key={item.label}>
            <span className="chart-vbars-value">{item.value > 0 ? valueFormatter(item.value) : ''}</span>
            <div className="chart-vbars-track">
              <div
                className="chart-vbars-fill"
                style={{ height: `${pct}%`, background: color }}
                title={`${item.label}: ${valueFormatter(item.value)}`}
              />
            </div>
            <span className="chart-vbars-label">{item.label}</span>
          </div>
        )
      })}
    </div>
  )
}
