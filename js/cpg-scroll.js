(function($){
    $(document).ready(function() {

        // ----- HORIZONTAL SCROLL WHEEL (Desktop) -----
        // If container has cpg-scrollable-desktop, allow wheel-based horizontal scroll
        $('.cpg-scrollable-desktop').on('wheel', function(e) {
            e.preventDefault();
            // For horizontal scroll, just use deltaY
            this.scrollLeft += e.originalEvent.deltaY;
        });

        // ----- INFINITE SCROLL LOGIC -----
        // We'll track containers that have data-allow-scroll="true"
        $('.cpg-grid, .cpg-list').each(function() {
            const $container = $(this);
            const allowScroll = $container.data('allow-scroll');

            if (allowScroll !== 'true') {
                return; // no infinite scroll if not set
            }

            let currentPage   = parseInt($container.data('current-page'), 10) || 1;
            let isLoading     = false;
            let preloadPosts  = null; 
            let preloadedPage = currentPage + 1; 

            // Read the sortBy setting to preserve random or date
            const sortBy = $container.data('sortby') || 'date';

            // 1) Preload the next page initially
            preloadNextPage();

            // Create a sentinel for IntersectionObserver
            let sentinel = document.createElement('div');
            sentinel.className = 'cpg-infinite-scroll-sentinel';
            $container.append(sentinel);

            let observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !isLoading) {
                        isLoading = true;
                        // If we have any preloaded posts, append them
                        if (preloadPosts) {
                            $container.find('.cpg-infinite-scroll-sentinel').before(preloadPosts);
                            currentPage++;
                        }
                        // Immediately preload the next set
                        preloadPosts = null;
                        preloadedPage = currentPage + 1;
                        preloadNextPage();
                        isLoading = false;
                    }
                });
            }, {
                root: null,
                threshold: 0.1
            });

            observer.observe(sentinel);

            // ------------------------
            // HELPER: Preload next page
            function preloadNextPage() {
                if (!preloadedPage) return;

                $.ajax({
                    url: cpgScrollData.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'cpg_load_more_posts',
                        next_page: preloadedPage,
                        container_view: $container.hasClass('cpg-list') ? 'list' : 'grid',
                        sortby: sortBy
                        // If you have category/tag, pass them here too
                    },
                    success: function(response) {
                        if (response.success) {
                            // Store the HTML in memory
                            preloadPosts = $(response.data.html);
                        }
                    }
                });
            }
        });

    });
})(jQuery);
