document.addEventListener('DOMContentLoaded', function() {
  const infoButton = document.getElementById('infoButton');
  const infoPopup = document.getElementById('infoPopup');
  if (infoButton && infoPopup) {
    // Cache the state in a local variable
    let isPopupShown = true;

    // Restore infoPopup state from localStorage
    const savedState = localStorage.getItem('infoPopupState');
    if (savedState === 'false') {
      isPopupShown = false;
    } else {
      infoPopup.classList.add('show');
    }

    infoButton.addEventListener('click', function(event) {
      event.preventDefault();
      isPopupShown = !isPopupShown;
      infoPopup.classList.toggle('show', isPopupShown);
      localStorage.setItem('infoPopupState', isPopupShown);
    });

    // Close popup when clicking outside
    document.addEventListener('click', function(event) {
      const target = event.target;
      const currentTarget = event.currentTarget;
      // const isButton = target.classList.contains('fa') || target.classList.contains('nav-button');
      if (isPopupShown && !infoButton.contains(event.target) && !infoPopup.contains(event.target)) {
        isPopupShown = false;
        infoPopup.classList.remove('show');
        // Update cached state and save to localStorage
        // localStorage.setItem('infoPopupState', isPopupShown);
      }
    });

    // Close popup with Escape key
    document.addEventListener('keydown', function(event) {
      if (isPopupShown && event.key === 'Escape') {
        isPopupShown = false;
        infoPopup.classList.remove('show');
        // Update cached state and save to localStorage
        // localStorage.setItem('infoPopupState', isPopupShown);
      }
    });
  }
});
