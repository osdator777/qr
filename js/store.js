const store = (() => {
  const KEY = 'qr-scans'
  const load = () => JSON.parse(localStorage.getItem(KEY) || '[]')
  const save = (arr) => localStorage.setItem(KEY, JSON.stringify(arr))

  let items = load()

  return {
    exists: ({ userId, order, date }) =>
      items.some(r => r.userId === userId && r.order === order && r.date === date),

    add: (rec) => {
      items.push(rec)
      save(items)
    },

    getAll: () => items
  }
})()
