;(function () {
  // Acceso rápido a un selector
  const $ = sel => document.querySelector(sel)

  // Al cargar la página, precargamos fecha y hora actuales
  const now = new Date()
  $('#date').value = now.toISOString().slice(0, 10)      // YYYY‑MM‑DD
  $('#time').value = now.toTimeString().slice(0, 5)      // HH:MM

  // --- QR Generator ----
  $('#btnMake').onclick = () => {
    const uid   = $('#uid').value.trim()
    const order = $('#order').value.trim()
    const date  = $('#date').value
    const time  = $('#time').value

    if (!uid || !order) return

    // Payload con fecha y hora
    const payload =
      `UID:${uid}` +
      `|ORDER:${order}` +
      `|DATETIME:${date}T${time}`

    // Genera el QR en el <canvas>
    QRCode.toCanvas($('#qrCanvas'), payload, { width: 240 }, err => {
      if (err) console.error(err)
    })
  }

  // --- Table ----
  const tbody = $('#tbl tbody')
  renderTable()

  function renderTable () {
    tbody.innerHTML = ''
    store.getAll().forEach(r => {
      const tr = document.createElement('tr')
      tr.innerHTML = [
        `<td>${r.userId}</td>`,
        `<td>${r.order}</td>`,
        `<td>${r.datetime.replace('T', ' ')}</td>`,
        `<td>${new Date(r.scannedAt).toLocaleString()}</td>`
      ].join('')
      tbody.appendChild(tr)
    })
  }

  // --- Excel ----
  $('#btnExcel').onclick = () => {
    const data = store.getAll().map(r => ({
      'User ID':       r.userId,
      'Pedido':        r.order,
      'Fecha‑Hora QR': r.datetime.replace('T', ' '),
      'Escaneado el':  new Date(r.scannedAt).toLocaleString()
    }))

    const wb = XLSX.utils.book_new()
    const ws = XLSX.utils.json_to_sheet(data)
    XLSX.utils.book_append_sheet(wb, ws, 'Scans')
    XLSX.writeFile(wb, 'scans.xlsx')
  }
})()
