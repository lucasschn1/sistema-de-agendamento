// Barra única empilhada — parte-do-todo (ex: receita por método de pagamento)
// Paleta categórica fixa (validada para CVD): azul, água, amarelo, verde, violeta
const CATEGORICAL_COLORS = ['#2a78d6', '#1baf7a', '#eda100', '#008300', '#4a3aa7']

export default function StackedBar({ items, valueFormatter = (v) => v }) {
  const total = items.reduce((sum, i) => sum + i.value, 0) || 1

  return (
    <div className="chart-stacked">
      <div className="chart-stacked-bar">
        {items.map((item, i) => {
          const pct = (item.value / total) * 100
          if (pct <= 0) return null
          return (
            <div
              key={item.label}
              className="chart-stacked-segment"
              style={{ width: `${pct}%`, background: CATEGORICAL_COLORS[i % CATEGORICAL_COLORS.length] }}
              title={`${item.label}: ${valueFormatter(item.value)} (${pct.toFixed(0)}%)`}
            />
          )
        })}
      </div>

      <div className="chart-stacked-legend">
        {items.map((item, i) => (
          <div className="chart-stacked-legend-item" key={item.label}>
            <span
              className="chart-stacked-legend-swatch"
              style={{ background: CATEGORICAL_COLORS[i % CATEGORICAL_COLORS.length] }}
            />
            <span className="chart-stacked-legend-label">{item.label}</span>
            <span className="chart-stacked-legend-value">{valueFormatter(item.value)}</span>
          </div>
        ))}
      </div>
    </div>
  )
}
