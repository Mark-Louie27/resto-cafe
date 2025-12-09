// Navigation Highlighting Script
document.addEventListener('DOMContentLoaded', function() {
  // Get all navigation links
  const navLinks = document.querySelectorAll('nav a');
  
  // Get current URL path
  const currentPath = window.location.pathname;
  const currentHash = window.location.hash;
  
  // Mobile menu toggle
  const mobileMenuButton = document.querySelector('.mobile-menu-button');
  const mobileMenu = document.querySelector('.mobile-menu');
  
  if (mobileMenuButton) {
    mobileMenuButton.addEventListener('click', function() {
      mobileMenu.classList.toggle('hidden');
    });
  }
  
  // Function to update active link
  function updateActiveLink() {
    const currentHash = window.location.hash;
    
    // Remove active class from all links
    navLinks.forEach(link => {
      link.classList.remove('text-amber-600', 'border-b-4', 'border-amber-600');
      if (!link.classList.contains('hover:text-amber-600')) {
        link.classList.add('text-gray-500', 'hover:text-amber-600');
      }
    });
    
    // Add active class to current link based on path
    navLinks.forEach(link => {
      const linkPath = link.getAttribute('href');
      
      // Check if we're on the homepage
      if (currentPath === '/' || currentPath === '/index.php') {
        // If we have a hash, activate the corresponding link
        if (currentHash && linkPath.includes(currentHash)) {
          setActive(link);
        } 
        // If we're on the homepage with no hash, activate the Home link
        else if (!currentHash && (linkPath === '/' || linkPath === '/index.php')) {
          setActive(link);
        }
      } 
      // For other pages, check if the current path matches the link path
      else if (currentPath.includes(linkPath) && !linkPath.includes('#')) {
        setActive(link);
      }
    });
  }
  
  // Function to set a link as active
  function setActive(link) {
    link.classList.remove('text-gray-500', 'hover:text-amber-600');
    link.classList.add('text-amber-600', 'border-b-4', 'border-amber-600');
  }
  
  // Initial call to update active link
  updateActiveLink();
  
  // Update active link when hash changes (for single page navigation)
  window.addEventListener('hashchange', updateActiveLink);
  
  // Add click event listeners to all links that have hash
  document.querySelectorAll('a[href*="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      const targetId = this.getAttribute('href').split('#')[1];
      const targetElement = document.getElementById(targetId);
      
      if (targetElement) {
        e.preventDefault();
        
        // Smooth scroll to element
        window.scrollTo({
          top: targetElement.offsetTop - 100, // Offset for fixed header
          behavior: 'smooth'
        });
        
        // Update URL hash
        history.pushState(null, null, `#${targetId}`);
        
        // Update active link
        updateActiveLink();
      }
    });
  });
});

 document.addEventListener('DOMContentLoaded', function() {
            // Category filtering
            const categoryButtons = document.querySelectorAll('.category-btn');
            const menuItems = document.querySelectorAll('.menu-card');
            const noResults = document.getElementById('no-results');
            const searchInput = document.getElementById('menu-search');
            const availableOnly = document.getElementById('available-only');
            const sortSelect = document.getElementById('sort-menu');

            // Quick view functionality
            const quickViewButtons = document.querySelectorAll('.quick-view-btn');
            const quickViewModal = document.getElementById('quick-view-modal');
            const closeModalButton = document.getElementById('close-modal');

            // Function to filter and search menu items
            function filterMenuItems() {
                const selectedCategory = document.querySelector('.category-btn.bg-amber-600').getAttribute('data-category');
                const searchTerm = searchInput.value.toLowerCase();
                const onlyAvailable = availableOnly.checked;

                let visibleCount = 0;

                menuItems.forEach(item => {
                    const itemCategory = item.getAttribute('data-category');
                    const itemName = item.querySelector('h3').textContent.toLowerCase();
                    const itemDescription = item.querySelector('p').textContent.toLowerCase();
                    const isAvailable = !item.querySelector('button[type="submit"]')?.hasAttribute('disabled');

                    const matchesCategory = selectedCategory === 'all' || itemCategory === selectedCategory;
                    const matchesSearch = itemName.includes(searchTerm) || itemDescription.includes(searchTerm);
                    const matchesAvailability = !onlyAvailable || isAvailable;

                    if (matchesCategory && matchesSearch && matchesAvailability) {
                        item.classList.remove('hidden');
                        visibleCount++;
                    } else {
                        item.classList.add('hidden');
                    }
                });

                // Show/hide no results message
                if (visibleCount === 0) {
                    noResults.classList.remove('hidden');
                } else {
                    noResults.classList.add('hidden');
                }
            }

            // Function to sort menu items
            function sortMenuItems() {
                const container = document.getElementById('menu-items-container');
                const items = Array.from(container.children);
                const sortBy = sortSelect.value;

                items.sort((a, b) => {
                    if (sortBy === 'name') {
                        const nameA = a.querySelector('h3').textContent;
                        const nameB = b.querySelector('h3').textContent;
                        return nameA.localeCompare(nameB);
                    } else if (sortBy === 'price-low') {
                        const priceA = parseFloat(a.querySelector('.text-amber-600').textContent.replace('₱', '').replace(',', ''));
                        const priceB = parseFloat(b.querySelector('.text-amber-600').textContent.replace('₱', '').replace(',', ''));
                        return priceA - priceB;
                    } else if (sortBy === 'price-high') {
                        const priceA = parseFloat(a.querySelector('.text-amber-600').textContent.replace('₱', '').replace(',', ''));
                        const priceB = parseFloat(b.querySelector('.text-amber-600').textContent.replace('₱', '').replace(',', ''));
                        return priceB - priceA;
                    } else if (sortBy === 'category') {
                        const catA = a.querySelector('.bg-amber-100').textContent;
                        const catB = b.querySelector('.bg-amber-100').textContent;
                        return catA.localeCompare(catB);
                    }
                });

                // Remove all existing items
                items.forEach(item => container.removeChild(item));

                // Append sorted items
                items.forEach(item => container.appendChild(item));

                // Apply current filters after sorting
                filterMenuItems();
            }

            // Event listeners for category buttons
            categoryButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    categoryButtons.forEach(btn => {
                        btn.classList.remove('bg-amber-600', 'text-white');
                        btn.classList.add('bg-white', 'text-gray-700', 'hover:bg-amber-600', 'hover:text-white');
                    });

                    // Add active class to clicked button
                    this.classList.remove('bg-white', 'text-gray-700', 'hover:bg-amber-600', 'hover:text-white');
                    this.classList.add('bg-amber-600', 'text-white');

                    // Show loading spinner
                    document.getElementById('loadingSpinner').classList.remove('hidden');

                    // Filter items with slight delay for visual feedback
                    setTimeout(() => {
                        filterMenuItems();
                        document.getElementById('loadingSpinner').classList.add('hidden');
                    }, 300);
                });
            });

            // Event listeners for search and filters
            searchInput.addEventListener('input', filterMenuItems);
            availableOnly.addEventListener('change', filterMenuItems);
            sortSelect.addEventListener('change', sortMenuItems);

            // Quick view functionality
            quickViewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const itemId = this.getAttribute('data-item-id');
                    const itemCard = this.closest('.menu-card');

                    // Populate modal with item data
                    document.getElementById('modal-item-id').value = itemId;
                    document.getElementById('modal-item-name').textContent = itemCard.querySelector('h3').textContent;
                    document.getElementById('modal-item-price').textContent = itemCard.querySelector('.text-amber-600').textContent;
                    document.getElementById('modal-item-description').textContent = itemCard.querySelector('p').textContent;
                    document.getElementById('modal-item-image').src = itemCard.querySelector('img').src;
                    document.getElementById('modal-item-category').textContent = itemCard.querySelector('.bg-amber-100').textContent;

                    // Handle availability
                    const isAvailable = !itemCard.querySelector('button[type="submit"]')?.hasAttribute('disabled');
                    const availabilityEl = document.getElementById('modal-item-availability');
                    if (isAvailable) {
                        availabilityEl.textContent = 'Available';
                        availabilityEl.className = 'inline-block bg-green-500 text-white px-3 py-1 rounded-full text-sm font-medium';
                        document.getElementById('modal-add-btn').removeAttribute('disabled');
                    } else {
                        availabilityEl.textContent = 'Sold Out';
                        availabilityEl.className = 'inline-block bg-red-500 text-white px-3 py-1 rounded-full text-sm font-medium';
                        document.getElementById('modal-add-btn').setAttribute('disabled', true);
                    }

                    // Optional nutritional info
                    const caloriesEl = itemCard.querySelector('.bg-blue-100');
                    if (caloriesEl) {
                        document.getElementById('modal-item-calories').textContent = caloriesEl.textContent;
                        document.getElementById('modal-item-nutrition-section').classList.remove('hidden');
                    } else {
                        document.getElementById('modal-item-nutrition-section').classList.add('hidden');
                    }

                    // Optional allergens
                    const allergensEl = itemCard.querySelector('.bg-red-100');
                    if (allergensEl) {
                        document.getElementById('modal-item-allergens').textContent = allergensEl.textContent;
                        document.getElementById('modal-item-allergens-section').classList.remove('hidden');
                    } else {
                        document.getElementById('modal-item-allergens-section').classList.add('hidden');
                    }

                    // Optional prep time
                    const prepTimeEl = itemCard.querySelector('.bg-purple-100');
                    if (prepTimeEl) {
                        document.getElementById('modal-item-preptime').textContent = prepTimeEl.textContent;
                        document.getElementById('modal-item-preptime-section').classList.remove('hidden');
                    } else {
                        document.getElementById('modal-item-preptime-section').classList.add('hidden');
                    }

                    // Show modal
                    quickViewModal.classList.remove('hidden');
                });
            });

            // Close modal
            closeModalButton.addEventListener('click', function() {
                quickViewModal.classList.add('hidden');
            });

            // Close modal when clicking outside
            quickViewModal.addEventListener('click', function(e) {
                if (e.target === quickViewModal) {
                    quickViewModal.classList.add('hidden');
                }
            });

            // Quantity buttons functionality
            document.querySelectorAll('.qty-btn, .modal-qty-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const input = this.parentElement.querySelector('input');
                    const currentValue = parseInt(input.value);

                    if (this.getAttribute('data-action') === 'increase') {
                        if (currentValue < parseInt(input.getAttribute('max'))) {
                            input.value = currentValue + 1;
                        }
                    } else {
                        if (currentValue > parseInt(input.getAttribute('min'))) {
                            input.value = currentValue - 1;
                        }
                    }
                });
            });

            // Initialize with default filter (All)
            filterMenuItems();
        });

        