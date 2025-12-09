 document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const menuSearch = document.getElementById('menuSearch');
            const sortPrice = document.getElementById('sortPrice');
            const filterAvailability = document.getElementById('filterAvailability');
            const filterAllergens = document.getElementById('filterAllergens');
            const categoryButtons = document.querySelectorAll('.category-btn');
            const menuItemsContainer = document.getElementById('menu-items-container');
            const menuCards = document.querySelectorAll('.menu-card');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const emptyState = document.getElementById('emptyState');
            const resetFiltersBtn = document.getElementById('resetFilters');
            const quickViewModal = document.getElementById('quickViewModal');
            const closeModal = document.getElementById('closeModal');
            const quickViewBtns = document.querySelectorAll('.quick-view-btn');

            // Variables
            let activeCategory = 'all';
            let searchQuery = '';
            let priceSort = '';
            let availabilityFilter = '';
            let allergensFilter = '';

            // Function to filter menu items
            function filterMenuItems() {
                // Show loading spinner
                loadingSpinner.classList.remove('hidden');
                menuItemsContainer.classList.add('opacity-50');

                // Simulate loading delay for better UX
                setTimeout(() => {
                    let visibleCount = 0;

                    menuCards.forEach(card => {
                        // Get data attributes
                        const category = card.dataset.category;
                        const name = card.dataset.name;
                        const description = card.dataset.description;
                        const price = parseFloat(card.dataset.price);
                        const available = card.dataset.available === 'true';
                        const allergens = card.dataset.allergens;

                        // Apply category filter
                        const categoryMatch = activeCategory === 'all' || category === activeCategory;

                        // Apply search filter
                        const searchMatch = searchQuery === '' ||
                            name.includes(searchQuery) ||
                            description.includes(searchQuery);

                        // Apply availability filter
                        let availabilityMatch = true;
                        if (availabilityFilter === 'available') {
                            availabilityMatch = available;
                        } else if (availabilityFilter === 'unavailable') {
                            availabilityMatch = !available;
                        }

                        // Apply allergens filter
                        let allergensMatch = true;
                        if (allergensFilter === 'none') {
                            allergensMatch = !allergens || allergens === '';
                        } else if (allergensFilter !== '') {
                            allergensMatch = allergens && allergens.includes(allergensFilter);
                        }

                        // Show or hide card based on filters
                        if (categoryMatch && searchMatch && availabilityMatch && allergensMatch) {
                            card.classList.remove('hidden');
                            visibleCount++;
                        } else {
                            card.classList.add('hidden');
                        }
                    });

                    // Sort items by price if selected
                    if (priceSort !== '') {
                        const items = Array.from(menuCards);
                        items.sort((a, b) => {
                            const priceA = parseFloat(a.dataset.price);
                            const priceB = parseFloat(b.dataset.price);
                            return priceSort === 'asc' ? priceA - priceB : priceB - priceA;
                        });

                        // Reappend sorted items
                        items.forEach(item => {
                            menuItemsContainer.appendChild(item);
                        });
                    }

                    // Show empty state if no items match filters
                    if (visibleCount === 0) {
                        emptyState.classList.remove('hidden');
                    } else {
                        emptyState.classList.add('hidden');
                    }

                    // Hide loading spinner
                    loadingSpinner.classList.add('hidden');
                    menuItemsContainer.classList.remove('opacity-50');
                }, 300); // Short delay for loading animation
            }

            // Event Listeners
            menuSearch.addEventListener('input', function() {
                searchQuery = this.value.toLowerCase().trim();
                filterMenuItems();
            });

            sortPrice.addEventListener('change', function() {
                priceSort = this.value;
                filterMenuItems();
            });

            filterAvailability.addEventListener('change', function() {
                availabilityFilter = this.value;
                filterMenuItems();
            });

            filterAllergens.addEventListener('change', function() {
                allergensFilter = this.value;
                filterMenuItems();
            });

            categoryButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Update active state
                    categoryButtons.forEach(btn => {
                        btn.classList.remove('bg-amber-600', 'text-white');
                        btn.classList.add('bg-gray-100', 'text-gray-700');
                    });
                    this.classList.remove('bg-gray-100', 'text-gray-700');
                    this.classList.add('bg-amber-600', 'text-white');

                    // Update active category
                    activeCategory = this.dataset.category;
                    filterMenuItems();
                });
            });

            resetFiltersBtn.addEventListener('click', function() {
                // Reset all filters
                menuSearch.value = '';
                sortPrice.value = '';
                filterAvailability.value = '';
                filterAllergens.value = '';

                // Reset category buttons
                categoryButtons.forEach(btn => {
                    btn.classList.remove('bg-amber-600', 'text-white');
                    btn.classList.add('bg-gray-100', 'text-gray-700');
                    if (btn.dataset.category === 'all') {
                        btn.classList.remove('bg-gray-100', 'text-gray-700');
                        btn.classList.add('bg-amber-600', 'text-white');
                    }
                });

                // Reset variables
                activeCategory = 'all';
                searchQuery = '';
                priceSort = '';
                availabilityFilter = '';
                allergensFilter = '';

                // Re-filter items
                filterMenuItems();
            });

            // Quick View Modal functionality
            quickViewBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const itemId = this.dataset.itemId;
                    // In a real implementation, you would fetch the item details via AJAX
                    // For this demo, we'll use the existing card data
                    const card = this.closest('.menu-card');

                    // Populate modal with item details
                    const modalContent = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="rounded-lg overflow-hidden">
                        <img src="${card.querySelector('img').src}" 
                             alt="${card.querySelector('h3').textContent}" 
                             class="w-full h-64 object-cover">
                    </div>
                    <div>
                        <h4 class="text-xl font-bold mb-2">${card.querySelector('h3').textContent}</h4>
                        <p class="text-gray-600 mb-4">${card.querySelector('p').textContent}</p>
                        
                        <div class="mb-4">
                            <span class="text-2xl font-bold text-amber-600">₱${card.dataset.price}</span>
                            <span class="ml-2 px-2 py-1 rounded-full text-xs font-medium ${card.dataset.available === 'true' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                ${card.dataset.available === 'true' ? 'In Stock' : 'Out of Stock'}
                            </span>
                        </div>
                        
                        <div class="flex flex-wrap gap-2 mb-4">
                            ${Array.from(card.querySelectorAll('.inline-block')).map(el => el.outerHTML).join('')}
                        </div>
                        
                        ${card.dataset.allergens && card.dataset.allergens !== '' ? `
                        <div class="mb-4 p-3 bg-red-50 rounded-lg border border-red-100">
                            <h5 class="font-medium text-red-700 mb-1">Allergen Information</h5>
                            <p class="text-sm text-red-600">Contains: ${card.dataset.allergens}</p>
                        </div>
                        ` : ''}
                        
                        <form method="POST" action="menu.php" class="mt-4">
                            <input type="hidden" name="csrf_token" value="${document.querySelector('input[name="csrf_token"]').value}">
                            <input type="hidden" name="action" value="add_to_cart">
                            <input type="hidden" name="item_id" value="${itemId}">
                            <div class="flex items-center gap-4">
                                <div class="flex items-center border border-gray-300 rounded-full">
                                    <button type="button" class="quantity-minus px-3 py-1 text-gray-600 hover:text-amber-600">-</button>
                                    <input type="number" name="quantity" value="1" min="1" class="w-12 text-center border-0 focus:ring-0">
                                    <button type="button" class="quantity-plus px-3 py-1 text-gray-600 hover:text-amber-600">+</button>
                                </div>
                                <button type="submit" class="flex-1 bg-amber-600 hover:bg-amber-500 text-white font-semibold py-2 px-4 rounded-full transition duration-300 ${card.dataset.available === 'false' ? 'opacity-50 cursor-not-allowed' : ''}" ${card.dataset.available === 'false' ? 'disabled' : ''}>
                                    Add to Cart
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `;

                    document.querySelector('.modal-content').innerHTML = modalContent;
                    document.getElementById('modalItemName').textContent = card.querySelector('h3').textContent;

                    // Add event listeners for quantity buttons
                    document.querySelectorAll('.quantity-minus').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const input = this.nextElementSibling;
                            if (parseInt(input.value) > 1) {
                                input.value = parseInt(input.value) - 1;
                            }
                        });
                    });

                    document.querySelectorAll('.quantity-plus').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const input = this.previousElementSibling;
                            input.value = parseInt(input.value) + 1;
                        });
                    });

                    // Show modal
                    quickViewModal.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                });
            });

            // Close modal
            closeModal.addEventListener('click', function() {
                quickViewModal.classList.add('hidden');
                document.body.style.overflow = '';
            });

            // Close modal when clicking outside
            quickViewModal.addEventListener('click', function(e) {
                if (e.target === quickViewModal) {
                    quickViewModal.classList.add('hidden');
                    document.body.style.overflow = '';
                }
            });

            // Initialize with all items visible
            filterMenuItems();
        });