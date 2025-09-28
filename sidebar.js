// sidebar.js - Centralized sidebar functionality

document.addEventListener('DOMContentLoaded', function() {
    
    /**
     * Handles the hamburger menu click to show/hide the sidebar.
     * It adapts for mobile (slide in/out) and desktop (collapse/expand).
     */
    const initializeSidebarToggle = () => {
        const hamburger = document.getElementById('hamburger');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        
        if (hamburger && sidebar && mainContent) {
            hamburger.addEventListener('click', function() {
              if (window.innerWidth <= 992) {
                  // On smaller screens, slide the sidebar in and out
                  sidebar.classList.toggle('show');
              } else {
                  // On larger screens, collapse/expand the sidebar
                  sidebar.classList.toggle('collapsed');
                  mainContent.classList.toggle('expanded');
              }
            });
        }
    };

    /**
     * Handles the logic for the collapsible dropdown menus in the sidebar.
     */
    const initializeDropdowns = () => {
        // Find the dropdown corresponding to the current active page and open it automatically.
        const activeDropdown = document.querySelector('.sidebar .dropdown.active');
        if (activeDropdown) {
            activeDropdown.classList.add('open');
            const menu = activeDropdown.querySelector('.dropdown-menu');
            if (menu) {
                // Set max-height to its scroll height to show all items smoothly.
                menu.style.maxHeight = menu.scrollHeight + 'px';
            }
        }

        // Add a click event listener to each dropdown toggle button.
        document.querySelectorAll('.sidebar .dropdown-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                const parent = this.closest('.dropdown');
                if (!parent) return;

                const menu = parent.querySelector('.dropdown-menu');
                if (!menu) return;

                // This part ensures only one dropdown is open at a time.
                document.querySelectorAll('.sidebar .dropdown.open').forEach(function(otherDropdown) {
                    // If it's a different dropdown than the one we clicked, close it.
                    if (otherDropdown !== parent) {
                        otherDropdown.classList.remove('open');
                        const otherMenu = otherDropdown.querySelector('.dropdown-menu');
                        if (otherMenu) {
                            otherMenu.style.maxHeight = '0';
                        }
                    }
                });

                // Toggle the 'open' class on the clicked dropdown.
                parent.classList.toggle('open');
                if (parent.classList.contains('open')) {
                    // If it's open, set its max-height to its content's height.
                    menu.style.maxHeight = menu.scrollHeight + 'px';
                } else {
                    // If it's closed, set max-height to 0.
                    menu.style.maxHeight = '0';
                }
            });
        });
    };

    // Run both initialization functions when the page is loaded.
    initializeSidebarToggle();
    initializeDropdowns();
});

