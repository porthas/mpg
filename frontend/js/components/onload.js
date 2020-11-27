
import { shortCodeTabInit } from './shortcode.js';

import {
    mpgGetState,
    mpgUpdateState,
    getProjectIdFromUrl,
    urlStructureToDom,
    convertTimestampToDateTime,
    setHeaders,
    fillUrlStructureShortcodes
} from '../helper.js';

import { translate } from '../../lang/init.js';

import {
    fillCustomTypeDropdown,
    fillDataPreviewAndUrlGeneration,
} from '../models/page-builder-model.js';


(async function () {

    let projectId = getProjectIdFromUrl();

    if (projectId) {


        mpgUpdateState('projectId', projectId);

        jQuery('#mpg_project_id span').text(projectId);
        jQuery('.delete-project').show();

        let project = await jQuery.ajax({
            url: ajaxurl,
            method: 'post',
            data: {
                action: 'mpg_get_project',
                projectId
            },
            statusCode: {
                500: function (xhr) {
                    toastr.error(
                        translate['Looks like you attempt to use large source file, that reached memory allocated to PHP or reached max_post_size. Please, increase memory limit according to documentation for your web server. For additional information, check .log files of web server or'] + `<a target="_blank" style="text-decoration: underline" href="https://help.mpgwp.com/en-us/article/uploading-large-dataset-cb71r5/"> ${translate['read our article']}</a>.`,
                        translate['Server settings limitation'], { timeOut: 30000 });
                }
            }
        });

        let projectData = JSON.parse(project)

        if (!projectData.success) {
            toastr.error(projectData.error, translate['Can not get project data']);
            return;
        }

        // ====================  Заполняем данные на странице ====================

        jQuery('.project-builder .project-name').val(projectData.data.name); // input

        jQuery('.project-builder #mpg_entity_type_dropdown').val(projectData.data.entity_type);

        // checkbox
        jQuery('#mpg_exclude_template_in_robots').prop('checked', parseInt(projectData.data.exclude_in_robots));

        // Грузит список типов записей, и сами записи в них. (дропдауны сверху)
        fillCustomTypeDropdown(projectData);

        // Заполним значение для поля количества записей в БД для Спинтакс, для текущего проекта
        jQuery('.cache-info .num-rows').text(projectData.data.spintax_cached_records_count);

        if (projectData.data.sitemap_url) {
            jQuery('#mpg_sitemap_url').html(`<a target="_blank" href="${projectData.data.sitemap_url}">${projectData.data.sitemap_url}</a>`);
        }

        // ==================  Direct link & Schedule section ==================

        if (projectData.data.schedule_source_link) {
            jQuery('input[name="direct_link_input"]').val(projectData.data.schedule_source_link);
        }

        if (projectData.data.schedule_periodicity) {
            jQuery(`select[name="periodicity"] option[value="${projectData.data.schedule_periodicity}"]`).attr('selected', 'selected');
        }

        if (projectData.data.schedule_notificate_about) {
            jQuery(`select[name="notification_level"] option[value="${projectData.data.schedule_notificate_about}"]`).attr('selected', 'selected');
        }

        if (projectData.data.schedule_notification_email) {
            jQuery(`select[name="notification_email"] option[value="${projectData.data.schedule_notification_email}"]`).attr('selected', 'selected');
        }

        if (projectData.data.worksheet_id) {
            jQuery(`input[name="worksheet_id"]`).val(projectData.data.worksheet_id)
            jQuery('.worksheet-id').css({ opacity: 1, height: 'initial' });
        }


        // ====================  Ставим заголовки в стейт ====================

        if (setHeaders(projectData)) {

            // Блочим вкладки, чоть у пользователя и есть проект, но нет датафайла, поэтому нечего ему там делать
            jQuery('a[href="#shortcode"], a[href="#sitemap"],  a[href="#spintax"], a[href="#cache"],  a[href="#logs"]').removeClass('disabled');

            // Заголовки в стейте храню в чистом виде, а по надобности - модифицирую, скажем прибавляя mpg
            // Это потому, что например в блоке копирования шорткодов надо иметь их оригинальный вид.
            let headers = mpgGetState('headers');

            shortCodeTabInit();

            fillDataPreviewAndUrlGeneration(projectData, headers);
        }


        // Если в проекта уже есть файл с данными, то можно сразу показывать их.
        if (projectData.data.name && projectData.data.entity_type && projectData.data.template_id) {

            if (projectData.data.source_path) {
                let path = projectData.data.source_path.split('wp-content');
                jQuery('#mpg_in_use_dataset_link')
                    .attr('href', `${backendData.baseUrl}/wp-content${path[1]}`)
                    .removeClass('disabled')
                    .text('Download');
            }


            if (projectData.data.source_type) {

                // Открываем ту вкладку, которая соотвествует типа загрузки файла
                jQuery(`a[href="#${projectData.data.source_type}"]`).trigger('click');

                if (projectData.data.source_type === 'upload_file') {

                    jQuery('label[for="mpg_upload_file_input"]').text(projectData.data.original_file_url)

                } else if (projectData.data.source_type === 'direct_link') {

                    jQuery('input[name="direct_link_input"]').val(projectData.data.original_file_url);
                }
            }

            if (projectData.data.is_trimmed) {
                toastr.warning(translate['Due to Free plan limitation, your dataset was trimed to 50 row. For unlocking unlimited features upgrade to Pro'], translate['Free plan limitation']);
            }

            jQuery('.project-builder section[data-id="2"]').show();

            mpgUpdateState('separator', projectData.data.space_replacer);

            // Если есть - выводим время слкдующего выполнения крона
            if (projectData.data.nextExecutionTimestamp) {
                let dateTime = convertTimestampToDateTime(projectData.data.nextExecutionTimestamp);

                jQuery('#mpg_next_cron_execution').text(`Next scheduled execution: ${dateTime}`);
                jQuery('.use-direct-link-button').hide();
                jQuery('#mpg_next_cron_execution').parents('.row').show();
            } else {
                jQuery('#mpg_next_cron_execution').parents('.row').hide();
            }


            let urlStructureDom = projectData.data.url_structure;

            if (urlStructureDom) {
                // Берем с базы структкру УРЛа с шорткодами, и делаем из него DOM.
                jQuery('#mpg_url_constructor').html(urlStructureToDom(urlStructureDom)).trigger('mpg_render_urls', ['init']);

            } else {
                // Создает шорткоды из заголовков. Выполняется в том случае, если это первый визит после загрузки файла, и в БД нет стуркутры
                // Если же пользователь сохранил в базе свою структуру - то уже будет рендерится она, а не эта (дефолтная из первых столбцов

                const headers = mpgGetState('headers')
                if (headers) {
                    fillUrlStructureShortcodes(headers);
                }
            }

            // =========   Space replacer fill ===========
            jQuery('.spaces-replacer').removeClass('active');

            jQuery('.spaces-replacer').each((index, elem) => {
                if (jQuery(elem).html() === projectData.data.space_replacer) {
                    jQuery(elem).addClass('active');
                }
            });

            // =============== Sitemap ==========
            fillSitemapData(projectData);

            // Cache
            fillCacheData(projectData);

        } else {
            // Блочим вкладки, пока нет пользовательского файла
            jQuery('a[href="#shortcode"], a[href="#sitemap"], a[href="#spintax"], a[href="#logs"]').addClass('disabled');
        }

    } else {
        // Блочим вкладки, если нет преокта (т.е пользователь создает новый, только заполняет данные.)
        jQuery('a[href="#shortcode"], a[href="#sitemap"], a[href="#spintax"], a[href="#logs"]').addClass('disabled');
    }

})();

function fillSitemapData(projectData) {
    // Заполняем стейт, чтобы потом с него считать во вкладке Sitemap
    mpgUpdateState('sitemapUrl', projectData.data.sitemap_url);
    mpgUpdateState('sitemapFilename', projectData.data.sitemap_filename);
    mpgUpdateState('sitemapMaxUrlPerFile', projectData.data.sitemap_max_url);
    mpgUpdateState('sitemapFrequency', projectData.data.sitemap_update_frequency);
    mpgUpdateState('sitemapAddToRobotsTxt', projectData.data.sitemap_add_to_robots);
}

function fillCacheData(projectData) {

    const cacheType = projectData.data.cache_type;

    if (cacheType !== 'none') {
        jQuery('.cache-page .buttons .btn').attr('disabled', 'disabled');
        jQuery(`.cache-page .buttons[data-cache-type=${cacheType}] .enable-cache`)
            .removeAttr('disabled')
            .removeClass('btn-success enable-cache')
            .addClass('btn-warning disable-cache')
            .text('Disable');


        jQuery(`.cache-page .buttons[data-cache-type=${cacheType}] .flush-cache`)
            .removeAttr('disabled')
            .removeClass('btn-light')
            .addClass('btn-danger');

    } else {
        jQuery('.cache-page .buttons .enable-cache').removeAttr('disabled');
    }
}