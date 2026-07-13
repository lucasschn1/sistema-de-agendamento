// Barras horizontais — comparação de magnitude entre categorias (ex: profissionais, status)
// `colors`: cor única (string) para todas as barras, ou um array de cores (uma por item)
export default function HorizontalBars({ items, colors = 'var(--brand)', valueFormatter = (v) => v }) {
  const max = Math.max(...items.map((i) => i.value), 1)

  return (
    <div className="chart-hbars">
      {items.map((item, i) => {
        const color = Array.isArray(colors) ? colors[i % colors.length] : colors
        const pct = Math.max((item.value / max) * 100, item.value > 0 ? 2 : 0)

        return (
          <div className="chart-hbars-row" key={item.label}>
            <span className="chart-hbars-label" title={item.label}>{item.label}</span>
            <div className="chart-hbars-track">
              <div
                className="chart-hbars-fill"
                style={{ width: `${pct}%`, background: color }}
                title={`${item.label}: ${valueFormatter(item.value)}`}
              />
            </div>
            <span className="chart-hbars-value">{valueFormatter(item.value)}</span>
          </div>
        )
      })}
    </div>
  )
}
