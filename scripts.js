if (typeof lucide !== 'undefined') {
  lucide.createIcons();
}

// LQIP (Low Quality Image Placeholder) - Fade-in effect for thumbnails
document.addEventListener('DOMContentLoaded', function() {
  const imageWrappers = document.querySelectorAll('.image-wrapper[data-lqip]');

  imageWrappers.forEach(function(wrapper) {
    const thumb = wrapper.querySelector('.image-thumb');

    if (thumb) {
      // Check if image is already cached/loaded
      if (thumb.complete) {
        thumb.classList.add('loaded');
      } else {
        // Wait for image to load
        thumb.addEventListener('load', function() {
          thumb.classList.add('loaded');
        });
        // Handle loading errors
        thumb.addEventListener('error', function() {
          // If thumbnail fails to load, keep LQIP visible
          console.warn('Failed to load thumbnail:', thumb.src);
        });
      }
    }
  });
});
