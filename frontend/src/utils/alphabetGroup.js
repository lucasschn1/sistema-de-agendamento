// Primeira letra (maiúscula, sem acento) de um nome — usada para agrupar
// listas já ordenadas alfabeticamente em seções "A", "B", "C"...
export function firstLetterOf(name) {
  const letter = (name || '').trim().charAt(0).toUpperCase()
  return letter.normalize('NFD').replace(/[̀-ͯ]/g, '') || '#'
}
