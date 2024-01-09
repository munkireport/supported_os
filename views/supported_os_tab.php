<h2>Supported OS  <a data-i18n="supported_os.recheck" class="btn btn-default btn-xs" href="<?php echo url('module/supported_os/recheck_highest_os/' . $serial_number);?>"></a></h2>

<div id="supported_os-msg" data-i18n="listing.loading" class="col-lg-12 text-center"></div>
    <div id="supported_os-view" class="row hide">
        <div class="col-md-3">
            <table class="table table-striped">
                <tr>
                    <th data-i18n="supported_os.current_os"></th>
                    <td id="supported_os-current_os"></td>
                </tr>
                <tr>
                    <th data-i18n="supported_os.highest_supported"></th>
                    <td id="supported_os-highest_supported"></td>
                </tr>
                <tr>
                    <th data-i18n="supported_os.shipping_os"></th>
                    <td id="supported_os-shipping_os"></td>
                </tr>
                <tr>
                    <th data-i18n="supported_os.machine_id"></th>
                    <td id="supported_os-machine_id"></td>
                </tr>
                <tr>
                    <th data-i18n="supported_os.last_touch"></th>
                    <td id="supported_os-last_touch"></td>
                </tr>
            </table>
        </div>
    </div>

<script>
$(document).on('appReady', function(e, lang) {

    // Get supported_os data
    $.getJSON( appUrl + '/module/supported_os/get_data/' + serialNumber, function( data ) {
        // Check if we have valid data
        if( ! data.current_os){
            $('#supported_os-msg').text(i18n.t('no_data'));
            $('#supported_os-cnt').text("");
        } else {

            // Hide
            $('#supported_os-msg').text('');
            $('#supported_os-view').removeClass('hide');

            // Add data
            $('#supported_os-cnt').text(mr.integerToVersion(data.highest_supported));
            $('#supported_os-current_os').text(mr.integerToVersion(data.current_os));
            $('#supported_os-shipping_os').text(mr.integerToVersion(data.shipping_os));
            $('#supported_os-highest_supported').text(mr.integerToVersion(data.highest_supported));
            $('#supported_os-machine_id').text(data.machine_id);

            // Format and fill date
            var colvar = data.last_touch;
            if(colvar > 0){
                var date = new Date(colvar * 1000);
                $('#supported_os-last_touch').html('<span title="'+moment(date).format('llll')+'">'+moment(date).fromNow()+'</span>');
            } else {
                $('#supported_os-last_touch').text(i18n.t('supported_os.never'));
            }
        }
    });
});

</script>