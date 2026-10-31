import 'trix';

// Images go through the CMS media library, not inline Trix uploads.
document.addEventListener('trix-file-accept', (event) => event.preventDefault());
