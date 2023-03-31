(function ($) {
    'use strict';

    $(document).ready(function () {
        $(".migrate-button").click((e) => {
            e.preventDefault(); // prevent the default action of the button
            if (confirm("All custom post types will be copied to the new table and removed from the original. You can revert this at any time.\n\nAre you sure you want to continue?")) {
                const url = $(e.currentTarget).data("url"); // retrieve the URL from the data-url attribute
                window.location.href = url; // redirect to the specified URL
            }
        });

        $(".revert-button").click((e) => {
            e.preventDefault(); // prevent the default action of the button

            if (confirm("All entries of the custom post type table will be moved back to the original posts table and the custom post type table will be removed.\n\nAre you sure you want to continue?")) {
                const url = $(e.currentTarget).data("url"); // retrieve the URL from the data-url attribute
                window.location.href = url; // redirect to the specified URL
            }
        });
    });
})(jQuery);
