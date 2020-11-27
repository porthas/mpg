
import {translate} from '../lang/init.js';

jQuery(document).ready(function () {

    if (jQuery('.dataset-library')) {
        // Фильтр-плагин для страницы Create New;
        let filter = jQuery('input#filterinput'), clearfilter = jQuery('input#clearfilter');
        let counter = jQuery('#mpg_result_count');

        jQuery('ul#dataset_list').listfilter({
            'filter': filter,
            'clearlink': clearfilter,
            'count': counter
        });

        jQuery('#dataset_list li a[data-dataset-id]').on('click', async function (e) {

            e.preventDefault();

            // Делаем так, чтобы после первого клика, человек не смог еще кликать (пока деплоится датасет)
            jQuery('#dataset_list li a').css('pointer-events', 'none');

            const datasetId = jQuery(this).attr('data-dataset-id');

            toastr.info('Dataset deployment started...', 'Info');



            let dataset = await jQuery.ajax({
                url: ajaxurl,
                method: 'post',
                data: {
                    action: 'mpg_deploy_dataset',
                    datasetId: datasetId
                },
                statusCode: {
                    500: function (xhr) {
                        toastr.error(
                            translate['Looks like you attempt to use large source file, that reached memory allocated to PHP or reached max_post_size. Please, increase memory limit according to documentation for your web server. For additional information, check .log files of web server or'] + `<a target="_blank" style="text-decoration: underline" href="https://help.mpgwp.com/en-us/article/uploading-large-dataset-cb71r5/"> ${translate['read our article']}</a>.`,
                            translate['Server settings limitation'], { timeOut: 30000 });
                    }
                }
            });



            let datasetResponse = JSON.parse(dataset)

            if (!datasetResponse.success) {
                toastr.error(datasetResponse.error, 'Can\'t deploy dataset. Details: ' + datasetResponse.error);
                // Раз произошла ошибка с этим датасетом, то снова дадим возможность кликать по другим
                jQuery('#dataset_list li a').css('pointer-events', 'unset');
                return false;
            }

            toastr.success('Dataset was successfully deployed. Wait few seconds', 'Deployed!')

            setTimeout(() => {
                location.href = `${backendData.projectPage}&id=${datasetResponse.data.projectId}`;
            }, 3000);

        });
    }
});