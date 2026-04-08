document.addEventListener('DOMContentLoaded', function() {
  const infoButton = document.getElementById('infoButton');
  const infoPopup = document.getElementById('infoPopup');
  if (infoButton && infoPopup) {
    infoButton.addEventListener('click', function(event) {
      event.preventDefault();
      infoPopup.classList.toggle('show');
    });
    // Close popup when clicking outside
    document.addEventListener('click', function(event) {
      if (!infoButton.contains(event.target) && !infoPopup.contains(event.target)) {
        infoPopup.classList.remove('show');
      }
    });
    // Close popup with Escape key
    document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        infoPopup.classList.remove('show');
      }
    });
  }
});

