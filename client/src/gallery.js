import PhotoSwipeLightbox from 'photoswipe/lightbox';

document.querySelectorAll('[data-reapit-gallery]').forEach((gallery) => {
  const lightbox = new PhotoSwipeLightbox({
    gallery,
    children: 'a[data-pswp-width]',
    pswpModule: () => import('photoswipe'),
  });

  lightbox.init();
});
