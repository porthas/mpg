
import("../lang/init.js")

import("./components/onload.js");

import("./components/page-builder.js");
import("./components/shortcode.js");
import("./components/sitemap.js");
import("./components/spintax.js");
import("./components/cache.js");
import("./components/logs.js");

import("./dataset-library.js");

import('./init.js');

jQuery(document).ready(function () {

    function getProjectIdFromUrl() {
        if (location.href.includes('mpg-project-builder&id=')) {

            const url = new URL(location.href);
            return url.searchParams.get('id');
        }

        return null;
    }


    const openedProjectId = getProjectIdFromUrl();

    if(openedProjectId){
        jQuery(`#toplevel_page_mpg-dataset-library .wp-submenu a`).each((index, elem) => {
            if(jQuery(elem).attr('href').includes(`mpg-project-builder&id=${openedProjectId}`)){
                jQuery(elem).parent().addClass('current');
            }
        })
    }
   
});


