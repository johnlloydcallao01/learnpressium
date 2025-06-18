/**
 * Learnpressium Trainees Admin JavaScript
 * Handles table interactions including horizontal swipe functionality
 */

(function($) {
    'use strict';

    var TraineesAdmin = {

        currentSearchTerm: '',
        currentSortValue: 'id-asc',

        init: function() {
            this.initTableSwipe();
            this.initLinkProtection();
            this.initSearch();
            this.initSort();
            this.initDropdown();
            console.log('Trainees Admin JS loaded');
        },

        /**
         * Initialize horizontal swipe functionality for the trainees table
         */
        initTableSwipe: function() {
            const tableContainer = document.querySelector('.modern-table-container');

            if (!tableContainer) {
                return; // No table container found
            }

            let isDown = false;
            let startX;
            let startY;
            let scrollLeft;
            let scrollTop;
            let isScrollingHorizontal = false;
            let isScrollingVertical = false;
            let hasMoved = false;
            let dragThreshold = 5; // Minimum pixels to move before considering it a drag
            let scrollDirection = null; // 'horizontal', 'vertical', or null

            // Initialize table setup
            this.initializeTable(tableContainer);

            // Mouse events for desktop
            tableContainer.addEventListener('mousedown', function(e) {
                // Only handle left mouse button
                if (e.button !== 0) return;

                // Don't interfere with links and buttons
                if (e.target.closest('a, button, .action-link')) {
                    return;
                }

                isDown = true;
                hasMoved = false;
                startX = e.pageX - tableContainer.offsetLeft;
                startY = e.pageY - tableContainer.offsetTop;
                scrollLeft = tableContainer.scrollLeft;
                scrollTop = tableContainer.scrollTop;
                isScrollingHorizontal = false;
                isScrollingVertical = false;
                scrollDirection = null;

                // Don't change cursor or add dragging class until we actually move
                // This allows clicks to work normally
            });

            tableContainer.addEventListener('mouseleave', function() {
                isDown = false;
                hasMoved = false;
                tableContainer.style.cursor = 'grab';
                tableContainer.classList.remove('dragging');
            });

            tableContainer.addEventListener('mouseup', function(e) {
                isDown = false;
                tableContainer.style.cursor = 'grab';
                tableContainer.classList.remove('dragging');

                // If we haven't moved much, allow the click to proceed normally
                if (!hasMoved) {
                    // This was just a click, not a drag - let it through
                    return;
                }

                // If we did drag, prevent the click event
                e.preventDefault();
                e.stopPropagation();
                hasMoved = false;
            });

            tableContainer.addEventListener('mousemove', function(e) {
                if (!isDown) return;

                const x = e.pageX - tableContainer.offsetLeft;
                const y = e.pageY - tableContainer.offsetTop;
                const walkX = (x - startX) * 1.5; // Horizontal scroll speed multiplier
                const walkY = (y - startY) * 1.5; // Vertical scroll speed multiplier

                // Check if we've moved enough to consider this a drag
                if (!hasMoved && (Math.abs(walkX) > dragThreshold || Math.abs(walkY) > dragThreshold)) {
                    hasMoved = true;

                    // Determine scroll direction based on initial movement
                    if (Math.abs(walkX) > Math.abs(walkY)) {
                        scrollDirection = 'horizontal';
                        isScrollingHorizontal = true;
                    } else {
                        scrollDirection = 'vertical';
                        isScrollingVertical = true;
                    }

                    // Now we know it's a drag, so set up dragging state
                    tableContainer.style.cursor = 'grabbing';
                    tableContainer.classList.add('dragging');
                }

                // Only proceed with scrolling if we've determined this is a drag
                if (!hasMoved) return;

                e.preventDefault();

                // Apply scrolling based on determined direction
                if (scrollDirection === 'horizontal') {
                    tableContainer.scrollLeft = scrollLeft - walkX;
                } else if (scrollDirection === 'vertical') {
                    tableContainer.scrollTop = scrollTop - walkY;
                }
            });

            // Touch events for mobile
            let touchStartX = 0;
            let touchStartY = 0;
            let touchScrollLeft = 0;
            let touchScrollTop = 0;
            let isTouchScrollingHorizontal = false;
            let isTouchScrollingVertical = false;
            let touchHasMoved = false;
            let touchScrollDirection = null;

            tableContainer.addEventListener('touchstart', function(e) {
                if (e.touches.length !== 1) return;

                // Don't interfere with links and buttons
                if (e.target.closest('a, button, .action-link')) {
                    return;
                }

                const touch = e.touches[0];
                touchStartX = touch.pageX;
                touchStartY = touch.pageY;
                touchScrollLeft = tableContainer.scrollLeft;
                touchScrollTop = tableContainer.scrollTop;
                isTouchScrollingHorizontal = false;
                isTouchScrollingVertical = false;
                touchHasMoved = false;
                touchScrollDirection = null;
            }, { passive: true });

            tableContainer.addEventListener('touchmove', function(e) {
                if (e.touches.length !== 1) return;

                const touch = e.touches[0];
                const deltaX = touchStartX - touch.pageX;
                const deltaY = touchStartY - touch.pageY;

                // Check if we've moved enough to consider this a swipe
                if (!touchHasMoved && (Math.abs(deltaX) > dragThreshold || Math.abs(deltaY) > dragThreshold)) {
                    touchHasMoved = true;

                    // Determine scroll direction based on initial movement
                    if (Math.abs(deltaX) > Math.abs(deltaY)) {
                        touchScrollDirection = 'horizontal';
                        isTouchScrollingHorizontal = true;
                    } else {
                        touchScrollDirection = 'vertical';
                        isTouchScrollingVertical = true;
                    }
                }

                // Only proceed if we've determined this is a swipe
                if (!touchHasMoved) return;

                // Apply scrolling based on determined direction
                if (touchScrollDirection === 'horizontal') {
                    // Prevent vertical scrolling when horizontally swiping
                    e.preventDefault();
                    tableContainer.scrollLeft = touchScrollLeft + deltaX;
                } else if (touchScrollDirection === 'vertical') {
                    // Prevent horizontal scrolling when vertically swiping
                    e.preventDefault();
                    tableContainer.scrollTop = touchScrollTop + deltaY;
                }
            }, { passive: false });

            tableContainer.addEventListener('touchend', function(e) {
                // If we haven't moved much, allow the touch to proceed normally (for taps on links)
                if (!touchHasMoved) {
                    // This was just a tap, not a swipe - let it through
                    return;
                }

                isTouchScrollingHorizontal = false;
                isTouchScrollingVertical = false;
                touchHasMoved = false;
                touchScrollDirection = null;
            }, { passive: true });

            // Set initial cursor style
            tableContainer.style.cursor = 'grab';

            // Add smooth scrolling behavior
            tableContainer.style.scrollBehavior = 'auto';
        },

        /**
         * Initialize table setup
         */
        initializeTable: function(tableContainer) {
            if (!tableContainer) return;

            // Set initial cursor style
            tableContainer.style.cursor = 'grab';
        },

        /**
         * Ensure links and buttons remain clickable
         */
        initLinkProtection: function() {
            const tableContainer = document.querySelector('.modern-table-container');
            if (!tableContainer) return;

            // Add click event listener to ensure action links work
            tableContainer.addEventListener('click', function(e) {
                const actionLink = e.target.closest('.action-link');
                if (actionLink && actionLink.href) {
                    // Force navigation for action links
                    e.stopPropagation();
                    window.location.href = actionLink.href;
                }
            }, true); // Use capture phase to ensure we get the event first
        },

        /**
         * Initialize search functionality
         */
        initSearch: function() {
            const searchInput = document.getElementById('trainees-search');
            const clearButton = document.getElementById('clear-search');
            const resultsInfo = document.getElementById('search-results-info');
            const resultsCount = document.getElementById('search-results-count');
            const totalCount = document.getElementById('total-trainees-count');
            const tableBody = document.querySelector('#trainees-table tbody');

            if (!searchInput || !tableBody) return;

            let searchTimeout;

            // Search input event
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    TraineesAdmin.performSearch(this.value.trim());
                }, 300); // Debounce search
            });

            // Re-apply current sort when search is performed
            TraineesAdmin.currentSearchTerm = '';

            // Initialize placeholder state
            if (searchInput.value) {
                searchInput.classList.add('has-content');
            }

            // Clear search button
            if (clearButton) {
                clearButton.addEventListener('click', function() {
                    searchInput.value = '';
                    TraineesAdmin.performSearch('');
                    searchInput.focus();
                });
            }

            // Show/hide clear button and manage placeholder based on input
            searchInput.addEventListener('input', function() {
                if (clearButton) {
                    clearButton.style.display = this.value ? 'block' : 'none';
                }

                // Add/remove class to control placeholder visibility
                if (this.value) {
                    this.classList.add('has-content');
                } else {
                    this.classList.remove('has-content');
                }
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + F to focus search
                if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }

                // Escape to clear search
                if (e.key === 'Escape' && document.activeElement === searchInput) {
                    searchInput.value = '';
                    TraineesAdmin.performSearch('');
                    if (clearButton) clearButton.style.display = 'none';
                }
            });
        },

        /**
         * Perform search filtering
         */
        performSearch: function(searchTerm) {
            this.currentSearchTerm = searchTerm;

            const tableBody = document.querySelector('#trainees-table tbody');
            const rows = tableBody.querySelectorAll('.modern-row');
            const resultsInfo = document.getElementById('search-results-info');
            const resultsCount = document.getElementById('search-results-count');
            const noResultsDiv = document.getElementById('no-search-results');
            const tableContainer = document.querySelector('.modern-table-container');

            if (!searchTerm) {
                // Show all rows and hide search info
                rows.forEach(row => {
                    row.style.display = '';
                    this.removeHighlights(row);
                });
                if (resultsInfo) resultsInfo.style.display = 'none';
                if (noResultsDiv) noResultsDiv.style.display = 'none';
                if (tableContainer) tableContainer.style.display = 'block';
                return;
            }

            let visibleCount = 0;
            const searchTermLower = searchTerm.toLowerCase();

            rows.forEach(row => {
                // Get all text content from the row
                const rowText = this.getRowSearchText(row).toLowerCase();

                if (rowText.includes(searchTermLower)) {
                    row.style.display = '';
                    this.highlightSearchTerm(row, searchTerm);
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                    this.removeHighlights(row);
                }
            });

            // Update search results info and no results state
            if (resultsInfo && resultsCount) {
                resultsCount.textContent = visibleCount;
                resultsInfo.style.display = 'block';
            }

            // Show/hide no results state
            if (noResultsDiv && tableContainer) {
                if (visibleCount === 0) {
                    tableContainer.style.display = 'none';
                    noResultsDiv.style.display = 'block';
                } else {
                    tableContainer.style.display = 'block';
                    noResultsDiv.style.display = 'none';
                }
            }
        },

        /**
         * Get searchable text from a table row
         */
        getRowSearchText: function(row) {
            const searchableElements = [
                '.trainee-name',
                '.column-username',
                '.column-email',
                '.course-tag',
                '.modern-badge'
            ];

            let text = '';
            searchableElements.forEach(selector => {
                const elements = row.querySelectorAll(selector);
                elements.forEach(el => {
                    text += ' ' + el.textContent.trim();
                });
            });

            return text;
        },

        /**
         * Highlight search terms in the row
         */
        highlightSearchTerm: function(row, searchTerm) {
            if (!searchTerm) return;

            const searchableSelectors = ['.trainee-name', '.column-username', '.column-email'];
            const regex = new RegExp(`(${this.escapeRegex(searchTerm)})`, 'gi');

            searchableSelectors.forEach(selector => {
                const elements = row.querySelectorAll(selector);
                elements.forEach(element => {
                    if (element.dataset.originalText) {
                        element.innerHTML = element.dataset.originalText;
                    } else {
                        element.dataset.originalText = element.innerHTML;
                    }

                    element.innerHTML = element.innerHTML.replace(regex, '<span class="search-highlight">$1</span>');
                });
            });
        },

        /**
         * Remove search highlights from a row
         */
        removeHighlights: function(row) {
            const highlightedElements = row.querySelectorAll('[data-original-text]');
            highlightedElements.forEach(element => {
                if (element.dataset.originalText) {
                    element.innerHTML = element.dataset.originalText;
                    delete element.dataset.originalText;
                }
            });
        },

        /**
         * Escape special regex characters
         */
        escapeRegex: function(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        },

        /**
         * Initialize sorting functionality
         */
        initSort: function() {
            const sortSelect = document.getElementById('sort-select');
            const tableBody = document.querySelector('#trainees-table tbody');

            if (!sortSelect || !tableBody) return;

            // Sort change event
            sortSelect.addEventListener('change', function() {
                TraineesAdmin.performSort(this.value);
            });
        },

        /**
         * Perform table sorting
         */
        performSort: function(sortValue) {
            this.currentSortValue = sortValue;

            const tableBody = document.querySelector('#trainees-table tbody');
            const rows = Array.from(tableBody.querySelectorAll('.modern-row'));

            if (!rows.length) return;

            const [field, direction] = sortValue.split('-');
            const isAscending = direction === 'asc';

            // Sort the rows
            rows.sort((a, b) => {
                let aValue, bValue;

                switch (field) {
                    case 'id':
                        aValue = parseInt(a.querySelector('.column-id').textContent.trim());
                        bValue = parseInt(b.querySelector('.column-id').textContent.trim());
                        break;
                    case 'name':
                        aValue = a.querySelector('.trainee-name').textContent.trim().toLowerCase();
                        bValue = b.querySelector('.trainee-name').textContent.trim().toLowerCase();
                        break;
                    case 'username':
                        aValue = a.querySelector('.column-username').textContent.trim().toLowerCase();
                        bValue = b.querySelector('.column-username').textContent.trim().toLowerCase();
                        break;
                    case 'email':
                        aValue = a.querySelector('.column-email').textContent.trim().toLowerCase();
                        bValue = b.querySelector('.column-email').textContent.trim().toLowerCase();
                        break;
                    default:
                        return 0;
                }

                // Handle numeric vs string comparison
                if (field === 'id') {
                    return isAscending ? aValue - bValue : bValue - aValue;
                } else {
                    if (aValue < bValue) return isAscending ? -1 : 1;
                    if (aValue > bValue) return isAscending ? 1 : -1;
                    return 0;
                }
            });

            // Clear the table body and append sorted rows
            tableBody.innerHTML = '';
            rows.forEach(row => tableBody.appendChild(row));

            // Add visual feedback
            this.addSortFeedback(field, direction);

            // Reapply current search if there is one
            if (this.currentSearchTerm) {
                this.performSearch(this.currentSearchTerm);
            }
        },

        /**
         * Add visual feedback for sorting
         */
        addSortFeedback: function(field, direction) {
            // Remove existing sort indicators
            const headers = document.querySelectorAll('.modern-table th');
            headers.forEach(header => {
                header.classList.remove('sorted-asc', 'sorted-desc');
            });

            // Add sort indicator to the appropriate header
            let headerSelector;
            switch (field) {
                case 'id':
                    headerSelector = '.column-id';
                    break;
                case 'name':
                    headerSelector = '.column-name';
                    break;
                case 'username':
                    headerSelector = '.column-username';
                    break;
                case 'email':
                    headerSelector = '.column-email';
                    break;
            }

            if (headerSelector) {
                const header = document.querySelector(`.modern-table th${headerSelector}`);
                if (header) {
                    header.classList.add(direction === 'asc' ? 'sorted-asc' : 'sorted-desc');
                }
            }
        },

        /**
         * Initialize dropdown menu functionality
         */
        initDropdown: function() {
            const dropdownTrigger = document.getElementById('settings-menu-trigger');
            const dropdownContent = document.getElementById('settings-dropdown');

            if (!dropdownTrigger || !dropdownContent) return;

            // Toggle dropdown on click
            dropdownTrigger.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownContent.classList.toggle('show');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!dropdownTrigger.contains(e.target) && !dropdownContent.contains(e.target)) {
                    dropdownContent.classList.remove('show');
                }
            });

            // Close dropdown on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    dropdownContent.classList.remove('show');
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        TraineesAdmin.init();
    });

    // Make TraineesAdmin globally available if needed
    window.TraineesAdmin = TraineesAdmin;

})(jQuery);
