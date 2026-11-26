import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

// Menu samping punya scroll sendiri: pastikan menu aktif terlihat saat halaman dibuka.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('nav a[aria-current="page"]')?.scrollIntoView({ block: 'nearest' });
});
