// =============================================
// FLUXO FINANCEIRO — barra segmentada mostrando:
// - Previsto: receita das sessões concluídas NO MÊS selecionado;
// - Recebido: pagamentos registrados NO MÊS selecionado (payment_date);
// - Aguardando: TOTAL de sessões concluídas ainda não pagas, de qualquer mês
//   (mesmo número da tabela "Sessões aguardando registro" — não existe
//   vencimento, então um pagamento antigo em aberto continua contando aqui
//   até ser registrado, mesmo que não pertença ao mês selecionado).
// Por isso Recebido + Aguardando não soma exatamente o Previsto quando há
// pendências de meses anteriores — isso é esperado, não um bug.
// =============================================

function formatCurrency(value) {
  return (value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

export default function PaymentFlowWidget({ previsto, recebido, aguardando }) {
  const total = Math.max(previsto ?? 0, recebido ?? 0, aguardando ?? 0, 1)
  const recebidoPct = Math.min(100, ((recebido ?? 0) / total) * 100)
  const aguardandoPct = Math.min(100 - recebidoPct, ((aguardando ?? 0) / total) * 100)

  return (
    <div className="chart-card financial-flow-widget">
      <div className="chart-card-title">Fluxo financeiro do mês</div>
      <div className="chart-card-subtitle">Previsto do mês vs. recebido no mês vs. total aguardando registro</div>

      <div className="financial-flow-bar" role="img" aria-label="Progresso de recebimento do mês">
        <div className="financial-flow-segment is-recebido" style={{ width: `${recebidoPct}%` }} />
        <div className="financial-flow-segment is-aguardando" style={{ width: `${aguardandoPct}%` }} />
      </div>

      <div className="financial-flow-legend">
        <div className="financial-flow-legend-item">
          <span className="financial-flow-legend-dot is-recebido" />
          Recebido no mês — {formatCurrency(recebido)}
        </div>
        <div className="financial-flow-legend-item">
          <span className="financial-flow-legend-dot is-aguardando" />
          Aguardando registro (total) — {formatCurrency(aguardando)}
        </div>
        <div className="financial-flow-legend-item">
          <span className="financial-flow-legend-dot is-previsto" />
          Previsto do mês — {formatCurrency(previsto)}
        </div>
      </div>
    </div>
  )
}
