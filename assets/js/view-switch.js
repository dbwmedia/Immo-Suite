document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('dbw-immo-suite');
    if (!container) return;

    // View Switcher Logic
    const gridBtn = document.getElementById('dbw-view-grid');
    const listBtn = document.getElementById('dbw-view-list');
    const propertyGrid = container.querySelector('.dbw-property-grid');

    if (gridBtn && listBtn && propertyGrid) {

        // Function to set view
        const setView = (view) => {
            // Remove active classes from buttons
            gridBtn.classList.remove('active');
            listBtn.classList.remove('active');

            // Remove view classes from grid
            propertyGrid.classList.remove('is-grid-view', 'is-list-view');

            if (view === 'list') {
                listBtn.classList.add('active');
                propertyGrid.classList.add('is-list-view');
            } else {
                gridBtn.classList.add('active');
                propertyGrid.classList.add('is-grid-view');
            }

            // Save to localStorage
            localStorage.setItem('dbw_immo_view', view);
        };

        // Load saved state
        const savedView = localStorage.getItem('dbw_immo_view') || 'grid';
        setView(savedView);

        // Animated switch via View Transition API (with fallback)
        const switchView = (view) => {
            if (document.startViewTransition) {
                document.startViewTransition(() => setView(view));
            } else {
                setView(view);
            }
        };

        // Click Handlers
        gridBtn.addEventListener('click', () => switchView('grid'));
        listBtn.addEventListener('click', () => switchView('list'));
    }
});
