(function($) {
  $(document).ready(function() {

    // 1) WHEEL SCROLL
    $('.cpg-scrollable-desktop, .cpg-scrollable-mobile').on('wheel', function(e) {
      e.preventDefault();
      const $container = $(this);
      let isDesktop = $container.hasClass('cpg-scrollable-desktop');
      if (isDesktop) {
        this.scrollLeft += e.originalEvent.deltaY * 3;
      } else {
        this.scrollTop += e.originalEvent.deltaY * 3;
      }
    });

    // 2) Fade & Scale
    function applyEdgeFade($container) {
      let isDesktop = $container.hasClass('cpg-scrollable-desktop');
      let visibleSize  = isDesktop ? $container.innerWidth() : $container.innerHeight();
      let scrollPos    = isDesktop ? $container.scrollLeft() : $container.scrollTop();
      let maxScrollPos = isDesktop
        ? ($container[0].scrollWidth - visibleSize)
        : ($container[0].scrollHeight - visibleSize);

      let noFadeZone = visibleSize * 0.6;
      let containerCenter = visibleSize / 2;

      $container.find('.cpg-item').each(function() {
        let $item = $(this);
        let offsetPos  = isDesktop ? $item.offset().left : $item.offset().top;
        let size       = isDesktop ? $item.outerWidth() : $item.outerHeight();
        let containerOffset = isDesktop ? $container.offset().left : $container.offset().top;
        let itemCenter = (offsetPos - containerOffset) + (size / 2);

        let distance = Math.abs(containerCenter - itemCenter);
        let fadeBoundary = noFadeZone / 2;
        let scale = 1;
        let opacity = 1;

        if (distance > fadeBoundary) {
          let maxDist = visibleSize / 2;
          let ratioDist = (distance - fadeBoundary) / (maxDist - fadeBoundary);
          if (ratioDist > 1) ratioDist = 1;
          scale   = 1;
          opacity = 1 - 0.2 * ratioDist;
        }

        if (scrollPos === 0) {
          if (itemCenter < visibleSize / 2) {
            scale = 1;
            opacity = 1;
          }
        }
        if (scrollPos >= maxScrollPos - 2) {
          if (itemCenter > visibleSize / 2) {
            scale = 1;
            opacity = 1;
          }
        }

        $item.css({
          '--fade-scale': scale,
          opacity: opacity
        });
      });
    }

    // 3) INFINITE SCROLL
    $('.cpg-scrollable-desktop, .cpg-scrollable-mobile').each(function() {
      const $container = $(this);
      let currentPage = parseInt($container.data('current-page'), 10) || 1;

      $container.on('scroll', function() {
        applyEdgeFade($container);
      });
      applyEdgeFade($container);

      let sentinel = document.createElement('div');
      sentinel.className = 'cpg-infinite-scroll-sentinel';
      sentinel.style.width = '200px';
      $container.append(sentinel);

      let observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          //console.log('Intersection entry:', entry);
          if (entry.isIntersecting) {
            //console.log('Sentinel is intersecting, load next page');
            observer.unobserve(entry.target);
            loadNextPage();
          }
        });
      }, {
        root: $container[0],
        threshold: 0.0,
        rootMargin: '0px 200px 0px 0px'
      });

      observer.observe(sentinel);

      function loadNextPage() {
        currentPage++;
        const $wrapper = $container.closest('.cpg-wrapper');
        if (!$wrapper.length) {
          console.warn('No .cpg-wrapper for infinite scroll.');
          return;
        }
        let scenario = $wrapper.data('scenario') || 3;
        let atts = $wrapper.data('atts') || {};

        $.ajax({
          url: cpgPaginationData.ajaxUrl,
          type: 'POST',
          data: {
            action: 'cpg_load_more_posts',
            scenario: scenario,
            page: currentPage,
            atts: atts
          },
          success: function(response) {
            if (response.success) {
              let $newWrapper   = $(response.data.html);
              let $newContainer = $newWrapper.find('.cpg-scrollable-desktop, .cpg-scrollable-mobile').first();
              let $newItems     = $newContainer.find('.cpg-item');

              if ($newItems.length) {
                $newContainer.find('.cpg-infinite-scroll-sentinel').remove();
                $container.append($newItems);

                $container.append(sentinel);
                observer.observe(sentinel);

                applyEdgeFade($container);
              } else {
                //console.log('No more items.');
              }
            } else {
              console.warn('Infinite scroll: success=false');
            }
          },
          error: function() {
            console.error('Infinite scroll: AJAX error.');
          }
        });
      }
    });

    // 4) CLICKABLE PAGINATION
    let cpgCache = {};
    $(document).on('click', '.cpg-pagination a', function(e) {
      e.preventDefault();
      let $link   = $(this);
      let newPage = $link.data('page');
      if (!newPage) return;

      let $wrapper = $link.closest('.cpg-wrapper');
      if (!$wrapper.length) return;

      let scenario = $wrapper.data('scenario') || 3;
      let atts     = $wrapper.data('atts') || {};
      let cacheKey = scenario + ':' + newPage + ':' + JSON.stringify(atts);

      console.log('Pagination clicked:', { scenario, newPage, atts });

      if (cpgPaginationData.enableCache && cpgCache[cacheKey]) {
        let $cached = cpgCache[cacheKey];
        $wrapper.replaceWith($cached);
        $('html, body').animate({ scrollTop: $cached.offset().top }, 500);
        return;
      }

      $.ajax({
        url: cpgPaginationData.ajaxUrl,
        type: 'POST',
        data: {
          action: 'cpg_load_more_posts',
          scenario: scenario,
          page: newPage,
          atts: atts
        },
        success: function(response) {
          console.log('Pagination AJAX response:', response);

          if (response.success && response.html) {
            let $newWrapper = $(response.html);
            if (cpgPaginationData.enableCache) {
              cpgCache[cacheKey] = $newWrapper;
            }
            $wrapper.replaceWith($newWrapper);
            $('html, body').animate({ scrollTop: 0 }, 100);
          } else {
            alert('Could not load page ' + newPage);
          }
        },
        error: function() {
          alert('Server or network error.');
        }
      });
    });
  });
})(jQuery);
