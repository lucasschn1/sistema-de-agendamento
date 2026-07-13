// Linhas de esqueleto exibidas dentro de um <Table> enquanto os dados carregam,
// mantendo o cabeçalho real visível (evita o "salto" de layout do spinner centralizado)
export default function TableSkeleton({ columns = 4, rows = 5 }) {
  return (
    <tbody>
      {Array.from({ length: rows }).map((_, r) => (
        <tr key={r}>
          {Array.from({ length: columns }).map((_, c) => (
            <td key={c}>
              <div className="skeleton-bar" />
            </td>
          ))}
        </tr>
      ))}
    </tbody>
  )
}
