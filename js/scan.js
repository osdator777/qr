;(function () {
  const { createApp, ref } = Vue
  const { QrcodeStream }   = VueQrcodeReader

  createApp({
    components: { QrcodeStream },
    setup () {
      const msg       = ref({ text: '', ok: true })
      const pauseScan = ref(false)

      // Lógica al decodificar un QR
      async function onDecode (text) {
        const data = parsePayload(text)
        if (!data) {
          return flash('Formato inválido', false)
        }
        if (store.exists(data)) {
          return flash('Duplicado – ya registrado', false)
        }
        await store.add(data)
        flash('¡Escaneo guardado!', true)
      }

      // Muestra mensaje y pausa brevemente el escaneo
      function flash (text, ok) {
        msg.value = { text, ok }
        pauseScan.value = true
        setTimeout(() => {
          msg.value.text = ''
          pauseScan.value = false
        }, 1500)
        if (navigator.vibrate) navigator.vibrate(ok ? 100 : [150, 50, 150])
      }

      return { onDecode, msg, pauseScan }
    }
  }).mount('#app')

  // Helper para extraer UID, ORDER y DATETIME
  function parsePayload (raw) {
    const m = /UID:(\w+)\|ORDER:(\d+)\|DATETIME:(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})/.exec(raw)
    if (!m) return null
    return {
      userId:    m[1],
      order:     m[2],
      datetime:  m[3],
      scannedAt: new Date().toISOString()
    }
  }
})()
