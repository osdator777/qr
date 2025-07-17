(() => {
  const form = document.getElementById('loginForm');
  const msg = document.getElementById('msg');

  form.onsubmit = (e) => {
    e.preventDefault();
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();
    if (!username || !password) return;

    const users = JSON.parse(localStorage.getItem('users') || '[]');
    const existing = users.find(u => u.username === username);
    if (existing) {
      if (existing.password !== password) {
        msg.textContent = 'Contraseña incorrecta';
        return;
      }
    } else {
      users.push({ username, password });
      localStorage.setItem('users', JSON.stringify(users));
    }
    localStorage.setItem('currentUser', JSON.stringify({ username }));
    location.href = 'index.html';
  };
})();
