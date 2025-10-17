console.log('gallery_gearicon_menu.js loaded at', new Date().toISOString());

(function($) {
    $.fn.gearmenu = function(config) {
        return this.each(function() {
            const $gear = $(this);

            // Create menu if not present
            let $menu = $gear.siblings('.gear-menu');
            if ($menu.length === 0) {
                $menu = $('<div class="gear-menu"></div>');

                config.forEach(item => {
                    const $btn = $('<button></button>').text(item.label);

                    if (typeof item.onClick === 'function') {
                        $btn.on('click', item.onClick);
                    }

                    $menu.append($btn);
                });

                $gear.after($menu);
            }

            // Toggle menu on gear click
            $gear.on('click', function(e) {
                e.stopPropagation();
                $menu.toggle();
            });

            // Hide menu when clicking outside
            $(document).off('click.gearmenu').on('click.gearmenu', function(e) {
                if (!$(e.target).closest('.gear-menu').length && !$(e.target).hasClass('gear-icon')) {
                    $('.gear-menu').hide();
                }
            });
        });
    };
})(jQuery);
