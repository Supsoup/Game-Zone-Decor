// เปิด/ปิดเมนูหมวดหมู่
document.addEventListener('click', (e) => {
  const dd = document.getElementById('catDropdown');
  const menu = document.getElementById('catMenu');
  if (!dd || !menu) return;

  if (dd.contains(e.target)) {
    dd.classList.toggle('open');
  } else {
    dd.classList.remove('open');
  }
});
