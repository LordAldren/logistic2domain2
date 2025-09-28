// sidebar.js - Centralized sidebar dropdown functionality

document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar dropdowns
    initializeSidebarDropdowns();
    
    // Initialize sidebar toggle
    initializeSidebarToggle();
});

function initializeSidebarDropdowns() {
    // Keep dropdowns closed by default - clean look
    // Only open the active dropdown if there is one
    const activeDropdown = document.querySelector('.sidebar .dropdown.active');
    if (activeDropdown) {
        activeDropdown.classList.add('open');
    }

    // Handle dropdown toggle clicks
    document.querySelectorAll('.sidebar .dropdown-toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            let parent = this.closest('.dropdown');
            let menu = parent.querySelector('.dropdown-menu');
            
            // Close other dropdowns
            document.querySelectorAll('.sidebar .dropdown.open').forEach(function(otherDropdown) {
                if (otherDropdown !== parent) {
                    otherDropdown.classList.remove('open');
                    const otherMenu = otherDropdown.querySelector('.dropdown-menu');
                    if (otherMenu) {
                        otherMenu.style.maxHeight = '0';
                    }
                }
            });

            // Toggle current dropdown
            parent.classList.toggle('open');
        });
    });
}

function initializeSidebarToggle() {
    // Sidebar toggle functionality
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            
            // Close all dropdowns when sidebar is collapsed
            if (sidebar.classList.contains('collapsed')) {
                document.querySelectorAll('.sidebar .dropdown.open').forEach(function(dropdown) {
                    dropdown.classList.remove('open');
                    const menu = dropdown.querySelector('.dropdown-menu');
                    if (menu) {
                        menu.style.maxHeight = '0';
                    }
                });
            }
        });
    }
}

// Function to close all dropdowns
function closeAllDropdowns() {
    document.querySelectorAll('.sidebar .dropdown.open').forEach(function(dropdown) {
        dropdown.classList.remove('open');
        const menu = dropdown.querySelector('.dropdown-menu');
        if (menu) {
            menu.style.maxHeight = '0';
        }
    });
}

// Function to open specific dropdown
function openDropdown(dropdownSelector) {
    closeAllDropdowns();
    const dropdown = document.querySelector(dropdownSelector);
    if (dropdown) {
        dropdown.classList.add('open');
        const menu = dropdown.querySelector('.dropdown-menu');
        if (menu) {
            menu.style.maxHeight = menu.scrollHeight + 'px';
        }
    }
}
